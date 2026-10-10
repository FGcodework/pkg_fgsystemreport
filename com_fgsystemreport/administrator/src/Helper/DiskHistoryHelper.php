<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Disk Usage snapshots - one compact row per real scan (not per page view
 * served from cache), so a scan can be compared with the previous scan that
 * used the same exclude list. The table is created lazily, like the
 * Security History one.
 *
 * Only the folders that were listed (top N) are stored, so a folder that is
 * missing from the previous snapshot is shown as "new in the list" - its
 * previous size is unknown, only known to have been below the smallest
 * listed folder.
 *
 * @since  1.5.0
 */
class DiskHistoryHelper
{
	private const TABLE = '#__fgsystemreport_diskscan';

	/** Keep at most this many snapshots per exclude list. */
	private const MAX_PER_HASH = 60;

	/** A repeat scan within this many seconds replaces the last snapshot. */
	private const REPLACE_WITHIN = 300;

	/** @var bool|null */
	private static $tableReady = null;

	public static function hashFor(array $exclude): string
	{
		$e = array_map('strval', $exclude);
		sort($e);

		return md5(implode(',', $e));
	}

	public static function ensureTable(): bool
	{
		if (self::$tableReady !== null) {
			return self::$tableReady;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			if (stripos($db->getServerType(), 'postgre') !== false) {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '"id" SERIAL PRIMARY KEY, "scanned_at" TIMESTAMP NOT NULL, "hash" VARCHAR(32) NOT NULL, '
					. '"total" BIGINT NOT NULL DEFAULT 0, "dirs" TEXT NOT NULL)';
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `scanned_at` DATETIME NOT NULL, '
					. '`hash` VARCHAR(32) NOT NULL, `total` BIGINT UNSIGNED NOT NULL DEFAULT 0, '
					. '`dirs` MEDIUMTEXT NOT NULL, PRIMARY KEY (`id`), KEY `idx_hash` (`hash`, `scanned_at`)'
					. ') DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
			}

			$db->setQuery($sql)->execute();
			self::$tableReady = true;
		} catch (\Throwable $e) {
			self::$tableReady = false;
		}

		return self::$tableReady;
	}

	/**
	 * Store the result of a real scan. Paths are stored relative to the
	 * site root so the snapshot survives a move of the site.
	 *
	 * @param   array  $exclude  Exclude list used
	 * @param   array  $scan     ['dirs' => [path => bytes], 'total' => int]
	 */
	public static function record(array $exclude, array $scan): void
	{
		if (empty($scan['dirs']) || !self::ensureTable()) {
			return;
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$hash = self::hashFor($exclude);
			$root = rtrim(JPATH_ROOT, '/\\');
			$rel  = [];

			foreach ($scan['dirs'] as $path => $bytes) {
				$rel[(string) (substr($path, strlen($root)) ?: '/')] = (int) $bytes;
			}

			$json = json_encode($rel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$now  = Factory::getDate()->toSql();

			$last = $db->setQuery(
				$db->getQuery(true)->select(['id', 'scanned_at'])->from($db->quoteName(self::TABLE))
					->where($db->quoteName('hash') . ' = ' . $db->quote($hash))
					->order($db->quoteName('id') . ' DESC'),
				0,
				1
			)->loadObject();

			$row = (object) ['scanned_at' => $now, 'hash' => $hash, 'total' => (int) ($scan['total'] ?? 0), 'dirs' => $json];

			if ($last && (time() - strtotime($last->scanned_at . ' UTC')) < self::REPLACE_WITHIN) {
				$row->id = (int) $last->id;
				$db->updateObject(self::TABLE, $row, 'id');
			} else {
				$db->insertObject(self::TABLE, $row);
			}

			self::prune($hash);
		} catch (\Throwable $e) {
			// Best effort - the page works without history.
		}
	}

	/**
	 * Latest two snapshots for this exclude list: [current|null, previous|null].
	 * Each has ->scanned_at (UTC), ->total, ->dirs (relative path => bytes).
	 */
	public static function getLatestTwo(array $exclude): array
	{
		if (!self::ensureTable()) {
			return [null, null];
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$rows = $db->setQuery(
				$db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
					->where($db->quoteName('hash') . ' = ' . $db->quote(self::hashFor($exclude)))
					->order($db->quoteName('id') . ' DESC'),
				0,
				2
			)->loadObjectList() ?: [];

			foreach ($rows as $r) {
				$r->dirs  = json_decode((string) $r->dirs, true) ?: [];
				$r->total = (int) $r->total;
			}

			return [$rows[0] ?? null, $rows[1] ?? null];
		} catch (\Throwable $e) {
			return [null, null];
		}
	}

	/**
	 * Compare the current scan with a previous snapshot.
	 *
	 * @param   array        $dirs   Current scan: absolute path => bytes
	 * @param   int          $total  Current total bytes
	 * @param   object|null  $prev   Previous snapshot
	 *
	 * @return  array|null  ['total_delta' => int, 'deltas' => [absPath => int|null]]
	 *                      (null delta = folder not in previous snapshot)
	 */
	public static function compare(array $dirs, int $total, ?object $prev): ?array
	{
		if ($prev === null) {
			return null;
		}

		$root   = rtrim(JPATH_ROOT, '/\\');
		$deltas = [];

		foreach ($dirs as $path => $bytes) {
			$key = (string) (substr($path, strlen($root)) ?: '/');
			$deltas[$path] = array_key_exists($key, $prev->dirs) ? ((int) $bytes - (int) $prev->dirs[$key]) : null;
		}

		return ['total_delta' => $total - (int) $prev->total, 'deltas' => $deltas];
	}

	private static function prune(string $hash): void
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		// Age limit (same retention as the Security History)
		$cutoff = Factory::getDate('-' . HistoryHelper::getRetentionDays() . ' days')->toSql();
		$db->setQuery(
			$db->getQuery(true)->delete($db->quoteName(self::TABLE))
				->where($db->quoteName('scanned_at') . ' < ' . $db->quote($cutoff))
		)->execute();

		// Count limit per exclude list
		$ids = $db->setQuery(
			$db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName(self::TABLE))
				->where($db->quoteName('hash') . ' = ' . $db->quote($hash))
				->order($db->quoteName('id') . ' DESC'),
			self::MAX_PER_HASH,
			1000
		)->loadColumn() ?: [];

		if ($ids) {
			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName(self::TABLE))
					->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')')
			)->execute();
		}
	}
}

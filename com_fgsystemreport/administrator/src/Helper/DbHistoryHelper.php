<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Snapshots of table sizes (data + index) so the Database page can show
 * growth and the growth alert can react to a table that suddenly balloons
 * (typically a log, session or cache table).
 *
 * @since  1.8.0
 */
final class DbHistoryHelper
{
	private const TABLE         = '#__fgsystemreport_dbscan';
	private const REPLACE_WITHIN = 300;
	private const MAX_ROWS       = 60;

	private static $tableReady = null;

	public static function ensureTable(): bool
	{
		if (self::$tableReady !== null) {
			return self::$tableReady;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			if (stripos($db->getServerType(), 'postgre') !== false) {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '"id" SERIAL PRIMARY KEY, "scanned_at" TIMESTAMP NOT NULL, '
					. '"total" BIGINT NOT NULL DEFAULT 0, "tables" TEXT NOT NULL)';
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `scanned_at` DATETIME NOT NULL, '
					. '`total` BIGINT UNSIGNED NOT NULL DEFAULT 0, `tables` MEDIUMTEXT NOT NULL, '
					. 'PRIMARY KEY (`id`), KEY `idx_scanned` (`scanned_at`)'
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
	 * @param   array  $sizes  table name (without prefix) => bytes
	 */
	public static function record(array $sizes): void
	{
		if (!$sizes || !self::ensureTable()) {
			return;
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$row  = (object) [
				'scanned_at' => Factory::getDate()->toSql(),
				'total'      => (int) array_sum($sizes),
				'tables'     => json_encode($sizes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			];
			$last = $db->setQuery(
				$db->getQuery(true)->select(['id', 'scanned_at'])->from($db->quoteName(self::TABLE))
					->order($db->quoteName('id') . ' DESC'),
				0,
				1
			)->loadObject();

			if ($last && (time() - strtotime($last->scanned_at . ' UTC')) < self::REPLACE_WITHIN) {
				$row->id = (int) $last->id;
				$db->updateObject(self::TABLE, $row, 'id');
			} else {
				$db->insertObject(self::TABLE, $row);
			}

			self::prune();
		} catch (\Throwable $e) {
			// Best effort.
		}
	}

	/** @return  array  [current|null, previous|null] each with ->tables (array) and ->total */
	public static function getLatestTwo(): array
	{
		if (!self::ensureTable()) {
			return [null, null];
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$rows = $db->setQuery(
				$db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
					->order($db->quoteName('id') . ' DESC'),
				0,
				2
			)->loadObjectList() ?: [];

			foreach ($rows as $r) {
				$r->tables = json_decode((string) $r->tables, true) ?: [];
				$r->total  = (int) $r->total;
			}

			return [$rows[0] ?? null, $rows[1] ?? null];
		} catch (\Throwable $e) {
			return [null, null];
		}
	}

	private static function prune(): void
	{
		$db     = Factory::getContainer()->get('DatabaseDriver');
		$cutoff = Factory::getDate('-' . HistoryHelper::getRetentionDays() . ' days')->toSql();
		$db->setQuery(
			$db->getQuery(true)->delete($db->quoteName(self::TABLE))
				->where($db->quoteName('scanned_at') . ' < ' . $db->quote($cutoff))
		)->execute();

		$ids = $db->setQuery(
			$db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName(self::TABLE))
				->order($db->quoteName('id') . ' DESC'),
			self::MAX_ROWS,
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

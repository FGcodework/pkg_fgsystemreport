<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Security Check history - one compact row per run (score, counts and the
 * per-check status), written by both manual re-checks and the scheduled
 * task plugin, so the score and individual findings can be followed over
 * time and any two runs compared.
 *
 * The table is created lazily (CREATE TABLE IF NOT EXISTS) the first time
 * it is needed, so it also appears on sites that upgrade in place from an
 * older version of the component, where Joomla would not run install SQL.
 *
 * Checks are identified by their (translated) label, because the Security
 * Check has no stable keys. If runs were recorded in different languages
 * (e.g. scheduler in the site language, manual in the admin language) the
 * comparison falls back to matching by position.
 *
 * @since  1.4.0
 */
class HistoryHelper
{
	private const TABLE = '#__fgsystemreport_history';

	/** Do not store an identical result again within this many seconds. */
	private const DEDUPE_SECONDS = 3600;

	/** @var bool|null */
	private static $tableReady = null;

	/**
	 * Create the history table if it does not exist yet.
	 */
	public static function ensureTable(): bool
	{
		if (self::$tableReady !== null) {
			return self::$tableReady;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			if (stripos($db->getServerType(), 'postgre') !== false) {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '"id" SERIAL PRIMARY KEY, "run_at" TIMESTAMP NOT NULL, "source" VARCHAR(16) NOT NULL DEFAULT \'manual\', '
					. '"score" SMALLINT NOT NULL DEFAULT 0, "count_ok" SMALLINT NOT NULL DEFAULT 0, '
					. '"count_warning" SMALLINT NOT NULL DEFAULT 0, "count_danger" SMALLINT NOT NULL DEFAULT 0, '
					. '"joomla_version" VARCHAR(32) NOT NULL DEFAULT \'\', "php_version" VARCHAR(32) NOT NULL DEFAULT \'\', '
					. '"results" TEXT NOT NULL)';
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `run_at` DATETIME NOT NULL, '
					. '`source` VARCHAR(16) NOT NULL DEFAULT \'manual\', `score` SMALLINT NOT NULL DEFAULT 0, '
					. '`count_ok` SMALLINT NOT NULL DEFAULT 0, `count_warning` SMALLINT NOT NULL DEFAULT 0, '
					. '`count_danger` SMALLINT NOT NULL DEFAULT 0, `joomla_version` VARCHAR(32) NOT NULL DEFAULT \'\', '
					. '`php_version` VARCHAR(32) NOT NULL DEFAULT \'\', `results` MEDIUMTEXT NOT NULL, '
					. 'PRIMARY KEY (`id`), KEY `idx_run_at` (`run_at`)) DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
			}

			$db->setQuery($sql)->execute();
			self::$tableReady = true;
		} catch (\Throwable $e) {
			self::$tableReady = false;
		}

		return self::$tableReady;
	}

	/**
	 * Retention in days from the component options (default 365, min 1).
	 */
	public static function getRetentionDays(): int
	{
		$days = (int) ComponentHelper::getParams('com_fgsystemreport')->get('history_retention_days', 365);

		return $days > 0 ? $days : 365;
	}

	/**
	 * Record one run. Returns the new row id, or null when skipped
	 * (identical to the latest run and recorded less than an hour ago, or
	 * the table is unavailable).
	 *
	 * @param   array   $checks  Result of getSecurityChecks()
	 * @param   string  $source  'manual', 'scheduler' or 'cli'
	 */
	public static function record(array $checks, string $source = 'manual'): ?int
	{
		if (empty($checks) || !self::ensureTable()) {
			return null;
		}

		try {
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$score = FgsystemreportHelper::calculateSecurityScore($checks);

			$results = [];
			$ok      = 0;

			foreach ($checks as $check) {
				$results[] = [(string) $check->label, (string) $check->status];

				if ($check->status === 'ok') {
					$ok++;
				}
			}

			$json = json_encode($results, JSON_UNESCAPED_UNICODE);

			// Dedupe against the latest row
			$last = $db->setQuery(
				$db->getQuery(true)
					->select([$db->quoteName('run_at'), $db->quoteName('results')])
					->from($db->quoteName(self::TABLE))
					->order($db->quoteName('id') . ' DESC'),
				0,
				1
			)->loadObject();

			if ($last && $last->results === $json
				&& (time() - strtotime($last->run_at . ' UTC')) < self::DEDUPE_SECONDS) {
				return null;
			}

			$row = (object) [
				'run_at'         => Factory::getDate()->toSql(),
				'source'         => in_array($source, ['scheduler', 'cli'], true) ? $source : 'manual',
				'score'          => (int) $score->score,
				'count_ok'       => $ok,
				'count_warning'  => (int) $score->warningCount,
				'count_danger'   => (int) $score->dangerCount,
				'joomla_version' => substr(JVERSION, 0, 32),
				'php_version'    => substr(PHP_VERSION, 0, 32),
				'results'        => $json,
			];

			$db->insertObject(self::TABLE, $row);

			return (int) $db->insertid();
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * The most recent recorded run (without the per-check results), or null
	 * when there is none or the table is unavailable. Cheap - used by the
	 * admin Home quick icon, which must never run a live check.
	 */
	public static function getLatestRun(): ?object
	{
		if (!self::ensureTable()) {
			return null;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			return $db->setQuery(
				$db->getQuery(true)
					->select(['id', 'run_at', 'source', 'score', 'count_ok', 'count_warning', 'count_danger'])
					->from($db->quoteName(self::TABLE))
					->order($db->quoteName('id') . ' DESC'),
				0,
				1
			)->loadObject() ?: null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Runs within the last $days days, oldest first (capped at 2000).
	 */
	public static function getRuns(int $days): array
	{
		if (!self::ensureTable()) {
			return [];
		}

		try {
			$db     = Factory::getContainer()->get('DatabaseDriver');
			$cutoff = Factory::getDate('-' . max(1, $days) . ' days')->toSql();

			$rows = $db->setQuery(
				$db->getQuery(true)
					->select(['id', 'run_at', 'source', 'score', 'count_ok', 'count_warning', 'count_danger', 'joomla_version', 'php_version'])
					->from($db->quoteName(self::TABLE))
					->where($db->quoteName('run_at') . ' >= ' . $db->quote($cutoff))
					->order($db->quoteName('run_at') . ' ASC'),
				0,
				2000
			)->loadObjectList();

			return $rows ?: [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * One run including decoded per-check results.
	 */
	public static function getRun(int $id): ?object
	{
		if ($id <= 0 || !self::ensureTable()) {
			return null;
		}

		try {
			$db  = Factory::getContainer()->get('DatabaseDriver');
			$row = $db->setQuery(
				$db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
					->where($db->quoteName('id') . ' = ' . $id)
			)->loadObject();

			if (!$row) {
				return null;
			}

			$row->decoded = json_decode((string) $row->results, true) ?: [];

			return $row;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * The run immediately before the given one, or null.
	 */
	public static function getPreviousRun(int $id): ?object
	{
		if (!self::ensureTable()) {
			return null;
		}

		try {
			$db  = Factory::getContainer()->get('DatabaseDriver');
			$prev = (int) $db->setQuery(
				$db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName(self::TABLE))
					->where($db->quoteName('id') . ' < ' . $id)
					->order($db->quoteName('id') . ' DESC'),
				0,
				1
			)->loadResult();

			return $prev ? self::getRun($prev) : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Compare two runs. Returns arrays of [label, from, to] for 'worse',
	 * 'better', plus 'added' [label, status] and 'removed' [label, status].
	 */
	public static function compare(object $current, ?object $previous): array
	{
		$out = ['worse' => [], 'better' => [], 'added' => [], 'removed' => []];

		if ($previous === null) {
			return $out;
		}

		$rank = ['info' => 0, 'ok' => 0, 'warning' => 1, 'danger' => 2];
		$cur  = $current->decoded;
		$prev = $previous->decoded;

		$curMap  = [];
		$prevMap = [];

		foreach ($cur as $r) {
			$curMap[$r[0]] = $r[1];
		}

		foreach ($prev as $r) {
			$prevMap[$r[0]] = $r[1];
		}

		// Different languages between runs -> labels do not match at all;
		// match by position when the number of checks is the same.
		if (!array_intersect_key($curMap, $prevMap) && count($cur) === count($prev) && count($cur) > 0) {
			$prevMap = [];

			foreach ($cur as $i => $r) {
				$prevMap[$r[0]] = $prev[$i][1];
			}
		}

		foreach ($curMap as $label => $status) {
			if (!array_key_exists($label, $prevMap)) {
				$out['added'][] = [$label, $status];
				continue;
			}

			$from = $prevMap[$label];
			$a    = $rank[$from] ?? 0;
			$b    = $rank[$status] ?? 0;

			if ($b > $a) {
				$out['worse'][] = [$label, $from, $status];
			} elseif ($b < $a) {
				$out['better'][] = [$label, $from, $status];
			}
		}

		foreach ($prevMap as $label => $status) {
			if (!array_key_exists($label, $curMap)) {
				$out['removed'][] = [$label, $status];
			}
		}

		return $out;
	}

	/**
	 * Delete rows older than the retention period. Returns rows removed.
	 */
	public static function purge(?int $days = null): int
	{
		if (!self::ensureTable()) {
			return 0;
		}

		try {
			$db     = Factory::getContainer()->get('DatabaseDriver');
			$cutoff = Factory::getDate('-' . ($days ?? self::getRetentionDays()) . ' days')->toSql();
			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName(self::TABLE))
					->where($db->quoteName('run_at') . ' < ' . $db->quote($cutoff))
			)->execute();

			return (int) $db->getAffectedRows();
		} catch (\Throwable $e) {
			return 0;
		}
	}

	/**
	 * Delete all history.
	 */
	public static function clear(): void
	{
		if (!self::ensureTable()) {
			return;
		}

		try {
			Factory::getContainer()->get('DatabaseDriver')
				->setQuery('DELETE FROM ' . Factory::getContainer()->get('DatabaseDriver')->quoteName(self::TABLE))
				->execute();
		} catch (\Throwable $e) {
			// ignore
		}
	}

	public static function isEmpty(): bool
	{
		if (!self::ensureTable()) {
			return true;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			return (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE))->loadResult() === 0;
		} catch (\Throwable $e) {
			return true;
		}
	}
}

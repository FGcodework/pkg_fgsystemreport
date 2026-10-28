<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Read-only database diagnostics (MySQL / MariaDB): server settings,
 * Joomla core tables, storage engines, collations, largest tables.
 * Nothing is ever modified.
 *
 * @since  1.7.0
 */
final class DbDiagnosticsHelper
{
	/** 'manual' or 'scheduler' - who triggered the current run (e-mail alerts only for scheduler). */
	public static string $source = 'manual';

	private const MIN_PACKET   = 16777216;       // 16 MB
	private const OVERHEAD_WARN = 104857600;     // 100 MB reclaimable space
	private const LIST_LIMIT   = 15;

	/** Joomla core tables (without prefix). Everything else belongs to extensions. */
	private const CORE_ALL = [
		'action_log_config', 'action_logs', 'action_logs_extensions', 'action_logs_users', 'assets',
		'banner_clients', 'banner_tracks', 'banners', 'categories', 'contact_details', 'content',
		'content_frontpage', 'content_rating', 'content_types', 'contentitem_tag_map', 'extensions',
		'fields', 'fields_categories', 'fields_groups', 'fields_values', 'finder_filters', 'finder_links',
		'finder_links_terms', 'finder_logging', 'finder_taxonomy', 'finder_taxonomy_map', 'finder_terms',
		'finder_terms_common', 'finder_tokens', 'finder_tokens_aggregate', 'finder_types', 'guidedtour_steps',
		'guidedtours', 'history', 'languages', 'mail_templates', 'menu', 'menu_types', 'messages',
		'messages_cfg', 'modules', 'modules_menu', 'newsfeeds', 'overrider', 'postinstall_messages',
		'privacy_consents', 'privacy_requests', 'redirect_links', 'scheduler_logs', 'scheduler_tasks',
		'schemas', 'session', 'tags', 'template_overrides', 'template_styles', 'ucm_base', 'ucm_content',
		'update_sites', 'update_sites_extensions', 'updates', 'user_keys', 'user_mfa', 'user_notes',
		'user_profiles', 'user_usergroup_map', 'usergroups', 'users', 'viewlevels', 'webauthn_credentials',
		'workflow_associations', 'workflow_stages', 'workflow_transitions', 'workflows',
	];

	/** Tables every working Joomla 4/5/6 site has (without prefix). */
	private const CORE_TABLES = [
		'assets', 'categories', 'content', 'extensions', 'languages', 'menu', 'menu_types',
		'modules', 'modules_menu', 'schemas', 'session', 'template_styles', 'update_sites',
		'updates', 'users', 'usergroups', 'user_usergroup_map', 'viewlevels', 'action_logs',
	];

	/**
	 * @return  array  ['time' => int, 'steps' => list<array>]
	 */
	public static function run(bool $forceRefresh = false): array
	{
		try {
			$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
				->createCacheController('callback', ['defaultgroup' => 'com_fgsystemreport_db', 'lifetime' => 5]);

			if ($forceRefresh) {
				$cache->clean();
			}

			return $cache->get([self::class, 'build'], [], 'db-diagnostics');
		} catch (\Throwable $e) {
			return self::build();
		}
	}

	public static function build(): array
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		if (stripos($db->getServerType(), 'mysql') === false) {
			return [
				'time'  => time(),
				'steps' => [self::step('server', 'info', $db->getServerType() . ' ' . $db->getVersion(), [], 'COM_FGSYSTEMREPORT_DB_UNSUPPORTED')],
			];
		}

		$steps   = [];
		$steps[] = self::checkServer($db);
		$steps[] = self::checkVariables($db);

		$tables = self::loadTables($db);

		if ($tables === null) {
			$steps[] = self::step('tables', 'info', Text::_('JNONE'), [], 'COM_FGSYSTEMREPORT_DB_TABLES_UNAVAILABLE');
		} else {
			// Snapshot of table sizes -> growth history and alerts
			$sizes = [];

			foreach ($tables as $t) {
				$sizes[self::stripPrefix($db, (string) $t['name'])] = (int) $t['data_len'] + (int) $t['index_len'];
			}

			DbHistoryHelper::record($sizes);
			GrowthAlertHelper::checkDb(self::$source);
			[, $prevSnap] = DbHistoryHelper::getLatestTwo();

			array_push(
				$steps,
				self::checkCoreTables($db, $tables),
				self::checkEngines($db, $tables),
				self::checkCollations($db, $tables),
				self::checkLargest($db, $tables, $prevSnap),
				self::checkGrowth($sizes, $prevSnap)
			);
		}

		return ['time' => time(), 'steps' => $steps];
	}

	/** Build one step record. */
	private static function step(string $id, string $status, string $value, array $metrics = [], string $note = '', array $table = []): array
	{
		return ['id' => $id, 'status' => $status, 'value' => $value, 'metrics' => $metrics, 'note' => $note, 'table' => $table];
	}

	private static function scalar($db, string $sql): ?string
	{
		try {
			$v = $db->setQuery($sql)->loadResult();

			return $v === null ? null : (string) $v;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private static function checkServer($db): array
	{
		$charset   = self::scalar($db, 'SELECT @@character_set_database') ?? '?';
		$collation = self::scalar($db, 'SELECT @@collation_database') ?? '?';
		$status    = stripos($charset, 'utf8mb4') === 0 ? 'ok' : 'warning';

		return self::step(
			'server',
			$status,
			$db->getServerType() . ' ' . $db->getVersion(),
			[
				['COM_FGSYSTEMREPORT_DB_M_DEFAULT_CHARSET', $charset . ' / ' . $collation],
			],
			$status === 'ok' ? '' : 'COM_FGSYSTEMREPORT_DB_CHARSET_NOT_UTF8MB4'
		);
	}

	private static function checkVariables($db): array
	{
		$names   = ['max_allowed_packet', 'max_connections', 'wait_timeout', 'innodb_buffer_pool_size', 'innodb_file_per_table'];
		$metrics = [];
		$status  = 'ok';
		$note    = '';

		foreach ($names as $name) {
			try {
				$row = $db->setQuery('SHOW VARIABLES LIKE ' . $db->quote($name))->loadRow();
			} catch (\Throwable $e) {
				$row = null;
			}

			if (!$row) {
				continue;
			}

			$val = (string) $row[1];

			if (in_array($name, ['max_allowed_packet', 'innodb_buffer_pool_size'], true) && ctype_digit($val)) {
				if ($name === 'max_allowed_packet' && (int) $val < self::MIN_PACKET) {
					$status = 'warning';
					$note   = 'COM_FGSYSTEMREPORT_DB_PACKET_LOW';
				}

				$val = FgsystemreportHelper::formatFileSize((int) $val);
			}

			$metrics[] = [$name, $val];
		}

		if (!$metrics) {
			return self::step('variables', 'info', Text::_('JNONE'), [], 'COM_FGSYSTEMREPORT_DB_VARIABLES_UNAVAILABLE');
		}

		return self::step('variables', $status, count($metrics) . '', $metrics, $note);
	}

	/** @return  array|null  rows from information_schema, null when unavailable */
	private static function loadTables($db): ?array
	{
		try {
			$prefix = $db->getPrefix();
			$like   = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix) . '%';

			$rows = $db->setQuery(
				'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation,'
				. ' COALESCE(DATA_LENGTH,0) AS data_len, COALESCE(INDEX_LENGTH,0) AS index_len,'
				. ' COALESCE(DATA_FREE,0) AS free_len, COALESCE(TABLE_ROWS,0) AS rows_n'
				. ' FROM information_schema.TABLES'
				. ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ' . $db->quote('BASE TABLE')
				. ' AND TABLE_NAME LIKE ' . $db->quote($like)
			)->loadAssocList();

			return $rows ?: null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private static function stripPrefix($db, string $name): string
	{
		$p = $db->getPrefix();

		return strpos($name, $p) === 0 ? substr($name, strlen($p)) : $name;
	}

	private static function isCore($db, string $tableName): bool
	{
		return in_array(strtolower(self::stripPrefix($db, $tableName)), self::CORE_ALL, true);
	}

	private static function checkCoreTables($db, array $tables): array
	{
		$have    = [];

		foreach ($tables as $t) {
			$have[strtolower(self::stripPrefix($db, (string) $t['name']))] = true;
		}

		$missing = [];

		foreach (self::CORE_TABLES as $core) {
			if (!isset($have[$core])) {
				$missing[] = $core;
			}
		}

		$value = (count(self::CORE_TABLES) - count($missing)) . '/' . count(self::CORE_TABLES);

		if ($missing) {
			return self::step('core', 'danger', $value, [['COM_FGSYSTEMREPORT_DB_M_MISSING', implode(', ', $missing)]], 'COM_FGSYSTEMREPORT_DB_CORE_MISSING');
		}

		return self::step('core', 'ok', $value, [['COM_FGSYSTEMREPORT_DB_M_TABLES', (string) count($tables)]]);
	}

	private static function checkEngines($db, array $tables): array
	{
		$count   = [];
		$core    = [];
		$ext     = [];

		foreach ($tables as $t) {
			$engine = (string) ($t['engine'] ?? '');
			$count[$engine] = ($count[$engine] ?? 0) + 1;

			if (strcasecmp($engine, 'MyISAM') === 0) {
				if (self::isCore($db, (string) $t['name'])) {
					$core[] = (string) $t['name'];
				} else {
					$ext[] = (string) $t['name'];
				}
			}
		}

		arsort($count);
		$metrics = [];

		foreach ($count as $e => $n) {
			$metrics[] = [$e !== '' ? $e : '?', (string) $n];
		}

		$fmt = static fn (array $l): string => implode(', ', array_slice($l, 0, self::LIST_LIMIT)) . (count($l) > self::LIST_LIMIT ? ', ...' : '');

		if ($core) {
			$metrics[] = ['COM_FGSYSTEMREPORT_DB_M_MYISAM_CORE', $fmt($core)];
		}

		if ($ext) {
			$metrics[] = ['COM_FGSYSTEMREPORT_DB_M_MYISAM_EXT', $fmt($ext)];
		}

		if ($core) {
			return self::step('engines', 'warning', (string) count($core), $metrics, 'COM_FGSYSTEMREPORT_DB_MYISAM_FOUND');
		}

		if ($ext) {
			return self::step('engines', 'info', (string) count($ext), $metrics, 'COM_FGSYSTEMREPORT_DB_MYISAM_EXT_ONLY');
		}

		return self::step('engines', 'ok', (string) key($count), $metrics);
	}

	private static function checkCollations($db, array $tables): array
	{
		$count    = [];
		$coreBad  = [];   // core tables not on utf8mb4
		$extOdd   = [];   // extension tables not on the site's dominant core collation
		$coreCol  = [];

		foreach ($tables as $t) {
			$c = (string) ($t['collation'] ?? '');
			$count[$c] = ($count[$c] ?? 0) + 1;

			if (self::isCore($db, (string) $t['name'])) {
				$coreCol[$c] = ($coreCol[$c] ?? 0) + 1;
			}
		}

		arsort($count);
		arsort($coreCol);
		$dominant = $coreCol ? (string) key($coreCol) : (string) key($count);
		$metrics  = [];

		foreach ($count as $c => $n) {
			$metrics[] = [$c !== '' ? $c : '?', (string) $n];
		}

		foreach ($tables as $t) {
			$c = (string) ($t['collation'] ?? '');

			if (self::isCore($db, (string) $t['name'])) {
				if ($c !== '' && stripos($c, 'utf8mb4') !== 0) {
					$coreBad[] = $t['name'] . ' (' . $c . ')';
				}
			} elseif ($c !== $dominant) {
				$extOdd[] = $t['name'] . ' (' . $c . ')';
			}
		}

		$fmt = static fn (array $l): string => implode(', ', array_slice($l, 0, self::LIST_LIMIT)) . (count($l) > self::LIST_LIMIT ? ', ...' : '');

		if ($coreBad) {
			$metrics[] = ['COM_FGSYSTEMREPORT_DB_M_CORE_DIFFERENT', $fmt($coreBad)];

			return self::step('collations', 'warning', (string) count($coreBad), $metrics, 'COM_FGSYSTEMREPORT_DB_COLLATION_NOT_UTF8MB4');
		}

		if ($extOdd) {
			$metrics[] = ['COM_FGSYSTEMREPORT_DB_M_EXT_DIFFERENT', $fmt($extOdd)];

			return self::step('collations', 'info', (string) count($extOdd), $metrics, 'COM_FGSYSTEMREPORT_DB_COLLATION_MIXED');
		}

		return self::step('collations', 'ok', $dominant, $metrics);
	}

	private static function checkLargest($db, array $tables, ?object $prev): array
	{
		$total    = 0;
		$overhead = 0;

		foreach ($tables as &$t) {
			$t['size'] = (int) $t['data_len'] + (int) $t['index_len'];
			$total    += $t['size'];
			$overhead += (int) $t['free_len'];
		}

		unset($t);

		usort($tables, static fn ($a, $b) => $b['size'] <=> $a['size']);
		$top  = array_slice($tables, 0, 10);
		$rows = [];

		foreach ($top as $t) {
			$rows[] = [
				(string) $t['name'],
				number_format((int) $t['rows_n'], 0, ',', ' '),
				FgsystemreportHelper::formatFileSize($t['size']),
				FgsystemreportHelper::formatFileSize((int) $t['free_len']),
				self::fmtDelta($prev, self::stripPrefix($db, (string) $t['name']), (int) $t['size']),
			];
		}

		$metrics = [
			['COM_FGSYSTEMREPORT_DB_M_TOTAL_SIZE', FgsystemreportHelper::formatFileSize($total)],
			['COM_FGSYSTEMREPORT_DB_M_OVERHEAD', FgsystemreportHelper::formatFileSize($overhead)],
		];

		$status = $overhead > self::OVERHEAD_WARN ? 'warning' : 'ok';

		return self::step(
			'largest',
			$status,
			FgsystemreportHelper::formatFileSize($total),
			$metrics,
			$status === 'ok' ? '' : 'COM_FGSYSTEMREPORT_DB_OVERHEAD_HIGH',
			['head' => ['COM_FGSYSTEMREPORT_DB_COL_TABLE', 'COM_FGSYSTEMREPORT_DB_COL_ROWS', 'COM_FGSYSTEMREPORT_DB_COL_SIZE', 'COM_FGSYSTEMREPORT_DB_COL_OVERHEAD', 'COM_FGSYSTEMREPORT_DB_COL_CHANGE'], 'rows' => $rows]
		);
	}

	/** "+1.2 MB" / "-3 MB" / "-" relative to the previous snapshot; "" when there is none. */
	private static function fmtDelta(?object $prev, string $key, int $now): string
	{
		if ($prev === null) {
			return '';
		}

		if (!array_key_exists($key, $prev->tables)) {
			return '+' . FgsystemreportHelper::formatFileSize($now);
		}

		$d = $now - (int) $prev->tables[$key];

		if ($d === 0) {
			return "\u{2013}";
		}

		return ($d > 0 ? '+' : "\u{2212}") . FgsystemreportHelper::formatFileSize(abs($d));
	}

	private static function checkGrowth(array $sizes, ?object $prev): array
	{
		if ($prev === null) {
			return self::step('growth', 'info', Text::_('JNONE'), [], 'COM_FGSYSTEMREPORT_DB_GROWTH_NO_PREVIOUS');
		}

		$app = Factory::getApplication();
		$d   = Factory::getDate($prev->scanned_at);

		try {
			$d->setTimezone(new \DateTimeZone($app->get('offset', 'UTC')));
		} catch (\Exception $e) {
			// keep UTC
		}

		$deltas = [];

		foreach ($sizes as $name => $bytes) {
			$was = array_key_exists($name, $prev->tables) ? (int) $prev->tables[$name] : 0;

			if ($bytes - $was > 0) {
				$deltas[$name] = $bytes - $was;
			}
		}

		arsort($deltas);
		$rows = [];

		foreach (array_slice($deltas, 0, 5, true) as $name => $delta) {
			$rows[] = [$name, '+' . FgsystemreportHelper::formatFileSize($delta), FgsystemreportHelper::formatFileSize($sizes[$name])];
		}

		$total = array_sum($sizes) - (int) $prev->total;

		return self::step(
			'growth',
			'ok',
			($total >= 0 ? '+' : "\u{2212}") . FgsystemreportHelper::formatFileSize(abs($total)),
			[['COM_FGSYSTEMREPORT_DB_M_COMPARED', $d->format('d.m.Y H:i', true)]],
			'',
			$rows ? ['head' => ['COM_FGSYSTEMREPORT_DB_COL_TABLE', 'COM_FGSYSTEMREPORT_DB_COL_CHANGE', 'COM_FGSYSTEMREPORT_DB_COL_SIZE'], 'rows' => $rows] : []
		);
	}
}

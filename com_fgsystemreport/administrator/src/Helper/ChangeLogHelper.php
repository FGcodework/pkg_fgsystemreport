<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Timeline of site changes (extension installs/updates/removals, extension
 * enable/disable, global configuration changes), recorded by the system
 * plugin plg_system_fgsystemreport and shown next to the Security History
 * so a drop in the score can be tied to what changed.
 *
 * @since  1.10.0
 */
final class ChangeLogHelper
{
	private const TABLE   = '#__fgsystemreport_event';
	private const MAX_ROWS = 1000;

	/** @var bool|null */
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
					. '"id" SERIAL PRIMARY KEY, "created_at" TIMESTAMP NOT NULL, "type" VARCHAR(16) NOT NULL, '
					. '"subject" VARCHAR(190) NOT NULL DEFAULT \'\', "detail" VARCHAR(255) NOT NULL DEFAULT \'\', '
					. '"user_name" VARCHAR(150) NOT NULL DEFAULT \'\')';
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `created_at` DATETIME NOT NULL, `type` VARCHAR(16) NOT NULL, '
					. '`subject` VARCHAR(190) NOT NULL DEFAULT \'\', `detail` VARCHAR(255) NOT NULL DEFAULT \'\', '
					. '`user_name` VARCHAR(150) NOT NULL DEFAULT \'\', '
					. 'PRIMARY KEY (`id`), KEY `idx_created_at` (`created_at`)) DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
			}

			$db->setQuery($sql)->execute();
			self::$tableReady = true;
		} catch (\Throwable $e) {
			self::$tableReady = false;
		}

		return self::$tableReady;
	}

	/**
	 * Store one event. Identical events within a minute are stored once.
	 *
	 * @param   string  $type  install|update|uninstall|enable|disable|config
	 */
	public static function record(string $type, string $subject, string $detail = ''): bool
	{
		if (!in_array($type, ['install', 'update', 'uninstall', 'enable', 'disable', 'config'], true) || !self::ensureTable()) {
			return false;
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$user = '';

			try {
				$identity = Factory::getApplication()->getIdentity();
				$user     = $identity && $identity->id ? (string) $identity->username : '';
			} catch (\Throwable $e) {
				// no identity (CLI / scheduler)
			}

			$subject = mb_substr($subject, 0, 190);
			$detail  = mb_substr($detail, 0, 255);

			$recent = (int) $db->setQuery(
				$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName(self::TABLE))
					->where($db->quoteName('type') . ' = ' . $db->quote($type))
					->where($db->quoteName('subject') . ' = ' . $db->quote($subject))
					->where($db->quoteName('detail') . ' = ' . $db->quote($detail))
					->where($db->quoteName('created_at') . ' > ' . $db->quote(Factory::getDate('-1 minute')->toSql()))
			)->loadResult();

			if ($recent > 0) {
				return false;
			}

			$row = (object) [
				'created_at' => Factory::getDate()->toSql(),
				'type'       => $type,
				'subject'    => $subject,
				'detail'     => $detail,
				'user_name'  => mb_substr($user, 0, 150),
			];
			$db->insertObject(self::TABLE, $row);

			self::prune();

			return true;
		} catch (\Throwable $e) {
			self::logError('record', $e);

			return false;
		}
	}

	/**
	 * Diagnostics for the empty timeline: is the system plugin installed and
	 * enabled, does the table exist, and what was the last recording error.
	 *
	 * @return  array{plugin:string,table:bool,error:string}  plugin: missing|disabled|enabled
	 */
	public static function diagnose(): array
	{
		$plugin = 'missing';
		$error  = '';

		try {
			$db  = Factory::getContainer()->get('DatabaseDriver');
			$row = $db->setQuery(
				$db->getQuery(true)->select($db->quoteName('enabled'))->from($db->quoteName('#__extensions'))
					->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
					->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
					->where($db->quoteName('element') . ' = ' . $db->quote('fgsystemreport'))
			)->loadResult();

			if ($row !== null && $row !== false) {
				$plugin = (int) $row === 1 ? 'enabled' : 'disabled';
			}
		} catch (\Throwable $e) {
			// unknown
		}

		try {
			$f = self::logDir() . '/fgsystemreport_events.log';

			if (is_file($f)) {
				$lines = array_filter(array_map('trim', (array) file($f)));
				$error = (string) end($lines);
			}
		} catch (\Throwable $e) {
			// ignore
		}

		return ['plugin' => $plugin, 'table' => self::ensureTable(), 'error' => $error];
	}

	/** Joomla's configured log folder (Global Configuration), else administrator/logs. */
	private static function logDir(): string
	{
		$dir = '';

		try {
			$dir = rtrim((string) Factory::getApplication()->get('log_path', ''), '/\\');
		} catch (\Throwable $e) {
			// fall back below
		}

		return $dir !== '' ? $dir : JPATH_ADMINISTRATOR . '/logs';
	}

	/** Remember why recording failed (plugin handlers swallow errors on purpose). */
	public static function logError(string $where, \Throwable $e): void
	{
		try {
			$dir = self::logDir();

			if (is_dir($dir) && is_writable($dir)) {
				@file_put_contents(
					$dir . '/fgsystemreport_events.log',
					gmdate('Y-m-d H:i:s') . ' ' . $where . ': ' . str_replace(["\r", "\n"], ' ', $e->getMessage()) . "\n",
					FILE_APPEND
				);
			}
		} catch (\Throwable $x) {
			// nothing more to do
		}
	}

	/**
	 * Events of the last $days days, oldest first.
	 *
	 * @return  array  list of objects: created_at (UTC), type, subject, detail, user_name
	 */
	public static function getEvents(int $days, int $limit = 300): array
	{
		if (!self::ensureTable()) {
			return [];
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$q  = $db->getQuery(true)
				->select(['created_at', 'type', 'subject', 'detail', 'user_name'])
				->from($db->quoteName(self::TABLE))
				->where($db->quoteName('created_at') . ' >= ' . $db->quote(Factory::getDate('-' . max(1, $days) . ' days')->toSql()))
				->order($db->quoteName('id') . ' DESC');

			return array_reverse((array) $db->setQuery($q, 0, $limit)->loadObjectList());
		} catch (\Throwable $e) {
			return [];
		}
	}

	/** Delete rows past the retention period and keep at most MAX_ROWS. */
	public static function prune(): void
	{
		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$days = HistoryHelper::getRetentionDays();

			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName(self::TABLE))
					->where($db->quoteName('created_at') . ' < ' . $db->quote(Factory::getDate('-' . $days . ' days')->toSql()))
			)->execute();

			$cut = $db->setQuery(
				$db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName(self::TABLE))
					->order($db->quoteName('id') . ' DESC'),
				self::MAX_ROWS,
				1
			)->loadResult();

			if ($cut) {
				$db->setQuery(
					$db->getQuery(true)->delete($db->quoteName(self::TABLE))->where($db->quoteName('id') . ' <= ' . (int) $cut)
				)->execute();
			}
		} catch (\Throwable $e) {
			// best effort
		}
	}
}

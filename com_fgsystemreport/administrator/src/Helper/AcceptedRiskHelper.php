<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * "Accepted risk": an administrator can mark a warning/danger finding of the
 * Security Check as consciously accepted, with a note. Such a finding is shown
 * as info (so it no longer lowers the score or counts as a warning anywhere:
 * dashboard, history, report, console, scheduled task) and keeps the original
 * status in ->orig_status. Applied on top of the cached check results, so
 * accepting or revoking takes effect immediately.
 *
 * @since  1.13.0
 */
final class AcceptedRiskHelper
{
	private const TABLE = '#__fgsystemreport_accepted';

	/** @var bool|null */
	private static $tableReady = null;

	/** @var array<string,object>|null */
	private static $cache = null;

	private static function ensureTable(): bool
	{
		if (self::$tableReady !== null) {
			return self::$tableReady;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			if (stripos($db->getServerType(), 'postgre') !== false) {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '"check_key" VARCHAR(120) NOT NULL PRIMARY KEY, "note" VARCHAR(255) NOT NULL DEFAULT \'\', '
					. '"user_name" VARCHAR(150) NOT NULL DEFAULT \'\', "created_at" TIMESTAMP NOT NULL)';
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '`check_key` VARCHAR(120) NOT NULL, `note` VARCHAR(255) NOT NULL DEFAULT \'\', '
					. '`user_name` VARCHAR(150) NOT NULL DEFAULT \'\', `created_at` DATETIME NOT NULL, '
					. 'PRIMARY KEY (`check_key`)) DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
			}

			$db->setQuery($sql)->execute();
			self::$tableReady = true;
		} catch (\Throwable $e) {
			self::$tableReady = false;
		}

		return self::$tableReady;
	}

	/** @return array<string,object> check_key => row (note, user_name, created_at) */
	public static function all(): array
	{
		if (self::$cache !== null) {
			return self::$cache;
		}

		self::$cache = [];

		if (!self::ensureTable()) {
			return self::$cache;
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$rows = (array) $db->setQuery(
				$db->getQuery(true)->select(['check_key', 'note', 'user_name', 'created_at'])->from($db->quoteName(self::TABLE))
			)->loadObjectList();

			foreach ($rows as $r) {
				self::$cache[(string) $r->check_key] = $r;
			}
		} catch (\Throwable $e) {
			// none accepted
		}

		return self::$cache;
	}

	public static function isValidKey(string $key): bool
	{
		return (bool) preg_match('/^COM_FGSYSTEMREPORT_SEC_[A-Z0-9_]{1,80}$/', $key);
	}

	public static function accept(string $key, string $note): bool
	{
		if (!self::isValidKey($key) || !self::ensureTable()) {
			return false;
		}

		try {
			$db   = Factory::getContainer()->get('DatabaseDriver');
			$user = (string) (Factory::getApplication()->getIdentity()->username ?? '');

			self::revoke($key);

			$row = (object) [
				'check_key'  => $key,
				'note'       => mb_substr(trim($note), 0, 255),
				'user_name'  => mb_substr($user, 0, 150),
				'created_at' => Factory::getDate()->toSql(),
			];
			$db->insertObject(self::TABLE, $row);
			self::$cache = null;

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	public static function revoke(string $key): bool
	{
		if (!self::isValidKey($key) || !self::ensureTable()) {
			return false;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName(self::TABLE))->where($db->quoteName('check_key') . ' = ' . $db->quote($key))
			)->execute();
			self::$cache = null;

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Mark accepted findings: status warning/danger becomes info.
	 *
	 * @param   object[]  $checks  Result of buildSecurityChecks()
	 *
	 * @return  object[]
	 */
	public static function apply(array $checks): array
	{
		$accepted = self::all();

		foreach ($checks as $c) {
			$key = (string) ($c->key ?? '');

			if ($key === '' || !isset($accepted[$key]) || !in_array($c->status, ['warning', 'danger'], true)) {
				continue;
			}

			$a              = $accepted[$key];
			$c->orig_status = $c->status;
			$c->status      = 'info';
			$c->accepted    = true;
			$c->accept_note = (string) $a->note;
			$c->accept_user = (string) $a->user_name;
			$c->note        = Text::sprintf('COM_FGSYSTEMREPORT_ACCEPTED_NOTE', Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_' . strtoupper((string) $c->orig_status)))
				. ($a->note !== '' ? ' ' . $a->note : '') . ($c->note !== '' ? ' | ' . $c->note : '');
		}

		return $checks;
	}
}

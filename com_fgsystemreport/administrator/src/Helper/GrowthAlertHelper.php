<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Session\Session;

/**
 * Detects sudden folder growth between two consecutive Disk Usage scans and
 * reports it three ways: a banner in the component, a private message in the
 * Joomla admin (no e-mail needed) and, optionally, an e-mail through the
 * site's own mailer (so SMTP from Global Configuration is used, not PHP mail()).
 */
final class GrowthAlertHelper
{
	private const TABLE     = '#__fgsystemreport_alert';
	private const MB        = 1048576;
	private const MIN_PCT_B = 10 * 1048576; // percent rule ignores folders growing by less than 10 MB

	private static ?bool $tableReady = null;

	public static function settings(): array
	{
		$p = ComponentHelper::getParams('com_fgsystemreport');

		return [
			'enabled'    => (int) $p->get('growth_alert_enabled', 1) === 1,
			'mb'         => max(0, (int) $p->get('growth_alert_mb', 100)),
			'pct'        => max(0, (int) $p->get('growth_alert_percent', 25)),
			'db_mb'      => max(0, (int) $p->get('growth_alert_db_mb', 50)),
			'db_pct'     => max(0, (int) $p->get('growth_alert_db_percent', 25)),
			'message'    => (int) $p->get('growth_alert_message', 1) === 1,
			'email'      => (int) $p->get('growth_alert_email', 0) === 1,
			'recipients' => trim((string) $p->get('growth_alert_recipients', '')),
		];
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
					. '"id" SERIAL PRIMARY KEY, "created_at" TIMESTAMP NOT NULL, "hash" VARCHAR(32) NOT NULL, '
					. '"dismissed" SMALLINT NOT NULL DEFAULT 0, "payload" TEXT NOT NULL)';
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `created_at` DATETIME NOT NULL, '
					. '`hash` VARCHAR(32) NOT NULL, `dismissed` TINYINT NOT NULL DEFAULT 0, '
					. '`payload` MEDIUMTEXT NOT NULL, PRIMARY KEY (`id`)'
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
	 * Pure threshold logic: folders present in both snapshots whose growth
	 * crosses the configured limits. Largest growth first.
	 *
	 * @param   array  $cur       relative path => bytes (current scan)
	 * @param   array  $prev      relative path => bytes (previous scan)
	 * @param   int    $mb        absolute limit in MB (0 = off)
	 * @param   int    $pct       relative limit in % (0 = off)
	 *
	 * @return  array  list of ['path','from','to','delta','pct']
	 */
	public static function detect(array $cur, array $prev, int $mb, int $pct): array
	{
		$out = [];

		foreach ($cur as $path => $bytes) {
			if (!array_key_exists($path, $prev)) {
				continue;
			}

			$from  = (int) $prev[$path];
			$to    = (int) $bytes;
			$delta = $to - $from;

			if ($delta <= 0) {
				continue;
			}

			$p   = $from > 0 ? ($delta / $from * 100) : 100.0;
			$hit = ($mb > 0 && $delta >= $mb * self::MB)
				|| ($pct > 0 && $p >= $pct && $delta >= self::MIN_PCT_B);

			if ($hit) {
				$out[] = ['path' => (string) $path, 'from' => $from, 'to' => $to, 'delta' => $delta, 'pct' => round($p, 1)];
			}
		}

		usort($out, static fn ($a, $b) => $b['delta'] <=> $a['delta']);

		return $out;
	}

	/**
	 * Call right after a new Disk Usage snapshot was recorded.
	 *
	 * @param   array   $exclude  Exclude list of the scan
	 * @param   string  $source   'manual' or 'scheduler' (e-mail is sent only for scheduler runs)
	 */
	public static function check(array $exclude, string $source = 'manual'): void
	{
		try {
			$cfg = self::settings();

			if (!$cfg['enabled'] || (!$cfg['mb'] && !$cfg['pct'])) {
				return;
			}

			[$cur, $prev] = DiskHistoryHelper::getLatestTwo($exclude);

			if ($cur === null || $prev === null) {
				return;
			}

			$items = self::detect((array) $cur->dirs, (array) $prev->dirs, $cfg['mb'], $cfg['pct']);
			self::raise('disk', $items, $prev, $cur, $source);
		} catch (\Throwable $e) {
			// Alerting must never break a scan.
		}
	}

	/**
	 * Call right after a new database size snapshot was recorded.
	 */
	public static function checkDb(string $source = 'manual'): void
	{
		try {
			$cfg = self::settings();

			if (!$cfg['enabled'] || (!$cfg['db_mb'] && !$cfg['db_pct'])) {
				return;
			}

			[$cur, $prev] = DbHistoryHelper::getLatestTwo();

			if ($cur === null || $prev === null) {
				return;
			}

			$items = self::detect((array) $cur->tables, (array) $prev->tables, $cfg['db_mb'], $cfg['db_pct']);
			self::raise('db', $items, $prev, $cur, $source);
		} catch (\Throwable $e) {
			// Alerting must never break the diagnostics.
		}
	}

	private static function raise(string $type, array $items, object $prev, object $cur, string $source): void
	{
		if (!$items || !self::ensureTable()) {
			return;
		}

		$cfg  = self::settings();
		$hash = md5($type . json_encode($items));
		$db   = Factory::getContainer()->get('DatabaseDriver');
		$last = $db->setQuery(
			$db->getQuery(true)->select($db->quoteName('hash'))->from($db->quoteName(self::TABLE))
				->order($db->quoteName('id') . ' DESC'),
			0,
			10
		)->loadColumn() ?: [];

		if (in_array($hash, $last, true)) {
			return; // same finding as a recent alert (e.g. repeated rescan)
		}

		$db->insertObject(self::TABLE, (object) [
			'created_at' => Factory::getDate()->toSql(),
			'hash'       => $hash,
			'dismissed'  => 0,
			'payload'    => json_encode([
				'type'      => $type,
				'prev_time' => $prev->scanned_at,
				'cur_time'  => $cur->scanned_at,
				'items'     => $items,
			]),
		]);

		self::prune();

		if ($cfg['message']) {
			self::sendMessages($items, $type);
		}

		if ($cfg['email'] && $source === 'scheduler') {
			self::sendMail($items, $error, $type);
		}
	}

	private static function prune(): void
	{
		$db  = Factory::getContainer()->get('DatabaseDriver');
		$cut = Factory::getDate('-' . HistoryHelper::getRetentionDays() . ' days')->toSql();
		$db->setQuery(
			$db->getQuery(true)->delete($db->quoteName(self::TABLE))
				->where($db->quoteName('created_at') . ' < ' . $db->quote($cut))
		)->execute();
	}

	/** Newest undismissed alert of each type ('disk', 'db'). */
	public static function latestAll(): array
	{
		$out = [];

		try {
			if (!self::ensureTable()) {
				return [];
			}

			$db   = Factory::getContainer()->get('DatabaseDriver');
			$rows = $db->setQuery(
				$db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
					->where($db->quoteName('dismissed') . ' = 0')
					->order($db->quoteName('id') . ' DESC'),
				0,
				20
			)->loadObjectList() ?: [];

			foreach ($rows as $row) {
				$row->data = json_decode((string) $row->payload, true) ?: [];
				$type      = (string) ($row->data['type'] ?? 'disk');

				if (!isset($out[$type])) {
					$out[$type] = $row;
				}
			}
		} catch (\Throwable $e) {
			return [];
		}

		return $out;
	}

	public static function dismissAll(): void
	{
		try {
			if (self::ensureTable()) {
				$db = Factory::getContainer()->get('DatabaseDriver');
				$db->setQuery('UPDATE ' . $db->quoteName(self::TABLE) . ' SET ' . $db->quoteName('dismissed') . ' = 1')->execute();
			}
		} catch (\Throwable $e) {
		}
	}

	public static function relPath(string $path): string
	{
		$root = rtrim(JPATH_ROOT, '/\\');

		return strpos($path, $root) === 0 ? (substr($path, strlen($root)) ?: '/') : $path;
	}

	private static function lines(array $items, int $max = 15): string
	{
		$out = [];

		foreach (array_slice($items, 0, $max) as $i) {
			$out[] = sprintf(
				'%s: +%s (%s -> %s, +%s%%)',
				$i['path'],
				FgsystemreportHelper::formatFileSize($i['delta']),
				FgsystemreportHelper::formatFileSize($i['from']),
				FgsystemreportHelper::formatFileSize($i['to']),
				$i['pct']
			);
		}

		return implode("\n", $out);
	}

	/** Super user ids/e-mails (active). */
	private static function superUsers(): array
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		return $db->setQuery(
			'SELECT DISTINCT u.id, u.email FROM ' . $db->quoteName('#__users') . ' u'
			. ' INNER JOIN ' . $db->quoteName('#__user_usergroup_map') . ' m ON m.user_id = u.id'
			. ' WHERE m.group_id = 8 AND u.block = 0'
		)->loadObjectList() ?: [];
	}

	/** Private messages (Users -> Private Messages); no mail transport involved. */
	private static function sendMessages(array $items, string $type = 'disk'): void
	{
		$db      = Factory::getContainer()->get('DatabaseDriver');
		$users   = self::superUsers();
		$site    = (string) Factory::getApplication()->get('sitename', '');
		$pfx     = $type === 'db' ? 'COM_FGSYSTEMREPORT_GROWTH_DB_' : 'COM_FGSYSTEMREPORT_GROWTH_';
		$subject = Text::sprintf($pfx . 'MESSAGE_SUBJECT', $site);
		$body    = nl2br(htmlspecialchars(Text::_($pfx . 'MAIL_INTRO') . "\n\n" . self::lines($items), ENT_QUOTES, 'UTF-8'));

		foreach ($users as $u) {
			$db->insertObject('#__messages', (object) [
				'user_id_from' => (int) $u->id,
				'user_id_to'   => (int) $u->id,
				'folder_id'    => 0,
				'date_time'    => Factory::getDate()->toSql(),
				'state'        => 0,
				'priority'     => 0,
				'subject'      => $subject,
				'message'      => $body,
			]);
		}
	}

	public static function recipients(): array
	{
		$cfg = self::settings();

		if ($cfg['recipients'] !== '') {
			return array_values(array_filter(array_map('trim', explode(',', $cfg['recipients']))));
		}

		return array_map(static fn ($u) => $u->email, self::superUsers());
	}

	/**
	 * Send through the site mailer (Global Configuration: PHP mail / sendmail / SMTP).
	 *
	 * @param   array|null  $items  null = test message
	 * @param   string|null $error  receives the error text on failure
	 */
	public static function sendMail(?array $items, ?string &$error = null, string $type = 'disk'): bool
	{
		$rcpt = self::recipients();

		if (!$rcpt) {
			$error = Text::_('COM_FGSYSTEMREPORT_GROWTH_NO_RECIPIENTS');

			return false;
		}

		$site = (string) Factory::getApplication()->get('sitename', '');

		try {
			$mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

			if ($items === null) {
				$mailer->setSubject(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_TEST_SUBJECT', $site));
				$mailer->setBody(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_TEST_BODY', $site));
			} else {
				$pfx = $type === 'db' ? 'COM_FGSYSTEMREPORT_GROWTH_DB_' : 'COM_FGSYSTEMREPORT_GROWTH_';
				$mailer->setSubject(Text::sprintf($pfx . 'MESSAGE_SUBJECT', $site));
				$mailer->setBody(Text::_($pfx . 'MAIL_INTRO') . "\n\n" . self::lines($items));
			}

			foreach ($rcpt as $r) {
				$mailer->addRecipient($r);
			}

			$ok = $mailer->Send();

			if ($ok !== true) {
				$error = trim((string) ($mailer->ErrorInfo ?? '')) ?: Text::_('COM_FGSYSTEMREPORT_GROWTH_MAIL_FAILED');

				return false;
			}

			return true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();

			return false;
		}
	}

	/** Banners for the component pages (one per alert type). */
	public static function renderBanner(): void
	{
		$h   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
		$url = 'index.php?option=com_fgsystemreport&task=fgsystemreport.dismissGrowthAlert&'
			. Session::getFormToken() . '=1';

		foreach (self::latestAll() as $type => $alert) {
			$items = $alert->data['items'] ?? [];
			$title = $type === 'db' ? 'COM_FGSYSTEMREPORT_GROWTH_DB_BANNER_TITLE' : 'COM_FGSYSTEMREPORT_GROWTH_BANNER_TITLE';

			echo '<div class="alert alert-warning d-flex justify-content-between align-items-start" role="alert"><div>';
			echo '<strong><span class="icon-warning me-1" aria-hidden="true"></span>' . $h(Text::_($title)) . '</strong>';
			echo '<ul class="mb-0 mt-1">';

			foreach (array_slice($items, 0, 5) as $i) {
				echo '<li><code>' . $h($i['path']) . '</code> +' . $h(FgsystemreportHelper::formatFileSize($i['delta']))
					. ' (' . $h(FgsystemreportHelper::formatFileSize($i['from'])) . ' &rarr; ' . $h(FgsystemreportHelper::formatFileSize($i['to']))
					. ', +' . $h($i['pct']) . '%)</li>';
			}

			if (count($items) > 5) {
				echo '<li>' . $h(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_MORE', count($items) - 5)) . '</li>';
			}

			echo '</ul></div>';
			echo '<a class="btn btn-sm btn-outline-secondary ms-3 text-nowrap" href="' . $h($url) . '">' . $h(Text::_('COM_FGSYSTEMREPORT_GROWTH_DISMISS')) . '</a>';
			echo '</div>';
		}
	}
}

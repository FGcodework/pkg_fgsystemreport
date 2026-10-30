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
use Joomla\CMS\HTML\HTMLHelper;
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
	 * Pure threshold logic: folders/tables whose growth crosses the configured
	 * limits. Largest growth first.
	 *
	 * Items missing from the previous snapshot are "new". They are judged only
	 * by the absolute limit, against $newFrom as their previous size: 0 when
	 * the snapshot lists everything (database tables), or the smallest listed
	 * size for a top-N list (a folder not in it was at most that big, so the
	 * growth is a lower bound). null ignores new items.
	 *
	 * @param   array     $cur      relative path => bytes (current scan)
	 * @param   array     $prev     relative path => bytes (previous scan)
	 * @param   int       $mb       absolute limit in MB (0 = off)
	 * @param   int       $pct      relative limit in % (0 = off)
	 * @param   int|null  $newFrom  assumed previous size of new items, null = ignore them
	 *
	 * @return  array  list of ['path','from','to','delta','pct'] (+ 'new' => true)
	 */
	public static function detect(array $cur, array $prev, int $mb, int $pct, ?int $newFrom = null): array
	{
		$out = [];

		foreach ($cur as $path => $bytes) {
			$isNew = !array_key_exists($path, $prev);

			if ($isNew && $newFrom === null) {
				continue;
			}

			$from  = $isNew ? $newFrom : (int) $prev[$path];
			$to    = (int) $bytes;
			$delta = $to - $from;

			if ($delta <= 0) {
				continue;
			}

			if ($isNew) {
				if ($mb > 0 && $delta >= $mb * self::MB) {
					$out[] = ['path' => (string) $path, 'from' => $from, 'to' => $to, 'delta' => $delta, 'pct' => 0, 'new' => true];
				}

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
	 * Growth of the overall size (site or database) between two snapshots,
	 * judged by the same limits. Catches growth spread over many small items
	 * that no single folder/table limit would report.
	 *
	 * @return  array  [] or one item ['path' => '*', ..., 'total' => true]
	 */
	public static function detectTotal(int $cur, int $prev, int $mb, int $pct): array
	{
		$delta = $cur - $prev;

		if ($prev <= 0 || $delta <= 0) {
			return [];
		}

		$p   = $delta / $prev * 100;
		$hit = ($mb > 0 && $delta >= $mb * self::MB) || ($pct > 0 && $p >= $pct && $delta >= self::MIN_PCT_B);

		return $hit ? [['path' => '*', 'from' => $prev, 'to' => $cur, 'delta' => $delta, 'pct' => round($p, 1), 'total' => true]] : [];
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

			// The disk snapshot is a top-N list: a folder missing from the previous
			// one was at most as big as the smallest folder that was listed.
			$prevDirs = (array) $prev->dirs;
			$items    = array_merge(
				self::detectTotal((int) $cur->total, (int) $prev->total, $cfg['mb'], $cfg['pct']),
				self::detect((array) $cur->dirs, $prevDirs, $cfg['mb'], $cfg['pct'], $prevDirs ? (int) min($prevDirs) : 0)
			);
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

			$items = array_merge(
				self::detectTotal((int) $cur->total, (int) $prev->total, $cfg['db_mb'], $cfg['db_pct']),
				self::detect((array) $cur->tables, (array) $prev->tables, $cfg['db_mb'], $cfg['db_pct'], 0)
			);
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

		$row = (object) [
			'created_at' => Factory::getDate()->toSql(),
			'hash'       => $hash,
			'dismissed'  => 0,
			'payload'    => json_encode([
				'type'      => $type,
				'prev_time' => $prev->scanned_at,
				'cur_time'  => $cur->scanned_at,
				'items'     => $items,
			]),
		];
		$db->insertObject(self::TABLE, $row);

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

	/** Plain-text label and detail of one alert item (folder, table, new item or the total). */
	private static function describe(array $i): array
	{
		$size  = static fn ($b) => FgsystemreportHelper::formatFileSize($b);
		$label = !empty($i['total']) ? Text::_('COM_FGSYSTEMREPORT_GROWTH_TOTAL') : (string) $i['path'];

		if (!empty($i['new'])) {
			$detail = '+' . $size($i['delta']) . ' (' . Text::_('COM_FGSYSTEMREPORT_GROWTH_NEW_ITEM') . ', ' . $size($i['to']) . ')';
		} else {
			$detail = '+' . $size($i['delta']) . ' (' . $size($i['from']) . ' -> ' . $size($i['to']) . ', +' . $i['pct'] . '%)';
		}

		return [$label, $detail];
	}

	private static function lines(array $items, int $max = 15): string
	{
		$out = [];

		foreach (array_slice($items, 0, $max) as $i) {
			[$label, $detail] = self::describe($i);
			$out[]            = $label . ': ' . $detail;
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
			. ' WHERE m.group_id IN (' . implode(',', FgsystemreportHelper::getSuperUserGroupIds()) . ') AND u.block = 0'
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
			$row = (object) [
				'user_id_from' => (int) $u->id,
				'user_id_to'   => (int) $u->id,
				'folder_id'    => 0,
				'date_time'    => Factory::getDate()->toSql(),
				'state'        => 0,
				'priority'     => 0,
				'subject'      => $subject,
				'message'      => $body,
			];
			$db->insertObject('#__messages', $row);
		}
	}

	public static function recipients(): array
	{
		$cfg = self::settings();

		if ($cfg['recipients'] !== '') {
			// Comma / semicolon / whitespace separated; invalid addresses are dropped
			$all = preg_split('/[\s,;]+/', (string) $cfg['recipients'], -1, PREG_SPLIT_NO_EMPTY) ?: [];

			return array_values(array_filter($all, static fn ($r) => filter_var($r, FILTER_VALIDATE_EMAIL) !== false));
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
		$url = 'index.php?option=com_fgsystemreport&task=fgsystemreport.dismissGrowthAlert';

		foreach (self::latestAll() as $type => $alert) {
			$items = $alert->data['items'] ?? [];
			$title = $type === 'db' ? 'COM_FGSYSTEMREPORT_GROWTH_DB_BANNER_TITLE' : 'COM_FGSYSTEMREPORT_GROWTH_BANNER_TITLE';

			echo '<div class="alert alert-warning d-flex justify-content-between align-items-start" role="alert"><div>';
			echo '<strong><span class="icon-warning me-1" aria-hidden="true"></span>' . $h(Text::_($title)) . '</strong>';
			echo '<ul class="mb-0 mt-1">';

			foreach (array_slice($items, 0, 5) as $i) {
				[$label, $detail] = self::describe($i);
				echo '<li>' . (!empty($i['total']) ? '<strong>' . $h($label) . '</strong>' : '<code>' . $h($label) . '</code>') . ' ' . $h($detail) . '</li>';
			}

			if (count($items) > 5) {
				echo '<li>' . $h(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_MORE', count($items) - 5)) . '</li>';
			}

			echo '</ul></div>';
			echo '<form method="post" action="' . $h($url) . '" class="m-0 ms-3">'
				. '<button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">' . $h(Text::_('COM_FGSYSTEMREPORT_GROWTH_DISMISS')) . '</button>'
				. HTMLHelper::_('form.token') . '</form>';
			echo '</div>';
		}
	}
}

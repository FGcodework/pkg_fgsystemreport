<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Reads PHP and Joomla error logs (tail only), groups identical errors and
 * attributes each group to the extension, template or core folder whose
 * file raised it. Pure read-only; no hooks, no per-request cost.
 *
 * @since  1.10.0
 */
final class ErrorLogHelper
{
	public const TAIL_BYTES  = 1048576; // last 1 MB of every file
	public const MAX_FILES   = 6;
	public const MAX_EVENTS  = 50000;

	private const CORE_COMPONENTS = [
		'com_admin', 'com_ajax', 'com_actionlogs', 'com_associations', 'com_banners', 'com_cache', 'com_categories',
		'com_checkin', 'com_config', 'com_contact', 'com_content', 'com_contenthistory', 'com_cpanel', 'com_fields',
		'com_finder', 'com_guidedtours', 'com_installer', 'com_joomlaupdate', 'com_languages', 'com_login', 'com_mails',
		'com_media', 'com_menus', 'com_messages', 'com_modules', 'com_newsfeeds', 'com_plugins', 'com_postinstall',
		'com_privacy', 'com_redirect', 'com_scheduler', 'com_tags', 'com_templates', 'com_users', 'com_workflow', 'com_wrapper',
	];

	/**
	 * Log files worth parsing.
	 *
	 * @return  array  list of ['path','label','size','modified'], newest first, max MAX_FILES
	 */
	public static function candidateFiles(): array
	{
		$found = [];
		$dir   = LogViewHelper::directory();

		foreach (LogViewHelper::listFiles($dir) as $f) {
			if (preg_match('/error|^everything\./i', $f['name'])) {
				$found[$dir . DIRECTORY_SEPARATOR . $f['name']] = $f['name'];
			}
		}

		$root = rtrim(str_replace('\\', '/', JPATH_ROOT), '/');
		$more = [JPATH_ROOT . '/error_log', JPATH_ADMINISTRATOR . '/error_log'];
		$ini  = (string) ini_get('error_log');

		if ($ini !== '' && strpos(str_replace('\\', '/', (string) realpath($ini)), $root . '/') === 0) {
			$more[] = $ini;
		}

		foreach ($more as $p) {
			$real = realpath($p);

			if ($real !== false && is_file($real) && is_readable($real) && !is_link($p)) {
				$found[$real] = ltrim(substr(str_replace('\\', '/', $real), strlen($root)), '/');
			}
		}

		$out = [];

		foreach ($found as $path => $label) {
			$out[] = ['path' => $path, 'label' => $label, 'size' => (int) @filesize($path), 'modified' => (int) @filemtime($path)];
		}

		usort($out, static fn ($a, $b) => $b['modified'] <=> $a['modified']);

		return array_slice($out, 0, self::MAX_FILES);
	}

	/**
	 * @return  array  ['groups'=>list,'owners'=>list,'files'=>list,'events'=>int,'partial'=>bool]
	 */
	public static function analyze(int $hours, string $levels = 'important'): array
	{
		$files  = self::candidateFiles();
		$now    = time();
		$since  = $now - $hours * 3600;
		$day    = $now - 86400;
		$week   = $now - 7 * 86400;
		$groups = [];
		$events = 0;
		$partial = false;
		$allowed = $levels === 'fatal' ? ['fatal', 'error'] : ($levels === 'all' ? ['fatal', 'error', 'warning', 'notice', 'deprecated'] : ['fatal', 'error', 'warning']);

		foreach ($files as $f) {
			foreach (self::parse(self::tail($f['path'])) as $e) {
				if (!in_array($e['level'], $allowed, true)) {
					continue;
				}

				if ($e['ts'] < $week && $e['ts'] < $since) {
					continue;
				}

				if (++$events > self::MAX_EVENTS) {
					$partial = true;
					break 2;
				}

				$key = $e['level'] . '|' . $e['norm'] . '|' . $e['file'] . ':' . $e['line'];

				if (!isset($groups[$key])) {
					$groups[$key] = [
						'level'   => $e['level'],
						'message' => $e['msg'],
						'file'    => $e['file'],
						'line'    => $e['line'],
						'owner'   => self::owner($e['file']),
						'count'   => 0,
						'c24'     => 0,
						'cprev'   => 0,
						'first'   => $e['ts'],
						'last'    => $e['ts'],
						'spike'   => false,
					];
				}

				$g = &$groups[$key];

				if ($e['ts'] >= $since) {
					$g['count']++;
				}

				if ($e['ts'] >= $day) {
					$g['c24']++;
				} elseif ($e['ts'] >= $week) {
					$g['cprev']++;
				}

				$g['first'] = min($g['first'], $e['ts']);
				$g['last']  = max($g['last'], $e['ts']);
				unset($g);
			}
		}

		$list   = [];
		$owners = [];

		foreach ($groups as $g) {
			if ($g['count'] === 0) {
				continue;
			}

			// Spike: 10+ in the last day and more than 3x the daily average of the 6 days before
			$g['spike'] = $g['c24'] >= 10 && $g['c24'] > 3 * ($g['cprev'] / 6);
			$list[]     = $g;
			$ok         = $g['owner']['type'] . '|' . $g['owner']['name'];

			if (!isset($owners[$ok])) {
				$owners[$ok] = $g['owner'] + ['count' => 0, 'groups' => 0, 'fatal' => 0];
			}

			$owners[$ok]['count']  += $g['count'];
			$owners[$ok]['groups'] += 1;

			if ($g['level'] === 'fatal') {
				$owners[$ok]['fatal'] += $g['count'];
			}
		}

		$rank = ['fatal' => 0, 'error' => 1, 'warning' => 2, 'notice' => 3, 'deprecated' => 4];

		usort($list, static fn ($a, $b) => ($rank[$a['level']] <=> $rank[$b['level']]) ?: ($b['count'] <=> $a['count']));
		usort($owners, static fn ($a, $b) => ($b['fatal'] <=> $a['fatal']) ?: ($b['count'] <=> $a['count']));

		return ['groups' => array_slice($list, 0, 200), 'owners' => array_values($owners), 'files' => $files, 'events' => $events, 'partial' => $partial];
	}

	/**
	 * Fatal-level errors of the last 24 h, for the Security Check.
	 *
	 * @return  array|null  ['count'=>int,'top'=>string]; null when there is no readable log
	 */
	public static function recentFatals(): ?array
	{
		try {
			$r = self::analyze(24, 'fatal');

			if (!$r['files']) {
				return null;
			}

			$count = 0;

			foreach ($r['groups'] as $g) {
				$count += $g['count'];
			}

			$top = $r['owners'] ? $r['owners'][0]['name'] : '';

			return ['count' => $count, 'top' => $top];
		} catch (\Throwable $e) {
			return null;
		}
	}

	/** Last TAIL_BYTES of a file as lines (first partial line dropped). */
	public static function tail(string $path): array
	{
		$size = (int) @filesize($path);
		$fh   = @fopen($path, 'rb');

		if ($fh === false) {
			return [];
		}

		if ($size > self::TAIL_BYTES) {
			fseek($fh, $size - self::TAIL_BYTES);
			fgets($fh);
		}

		$raw = (string) stream_get_contents($fh);
		fclose($fh);

		return preg_split('/\r\n|\n|\r/', $raw) ?: [];
	}

	/**
	 * Parse PHP error_log and Joomla log lines. Lines without a timestamp
	 * (stack traces, continuation lines) are skipped.
	 *
	 * @return  \Generator  yields ['ts','level','msg','norm','file','line']
	 */
	public static function parse(array $lines): \Generator
	{
		foreach ($lines as $line) {
			if ($line === '' || $line[0] === '#') {
				continue;
			}

			$ts = 0;

			if (preg_match('/^\[(\d{1,2}-[A-Za-z]{3}-\d{4} \d\d:\d\d:\d\d)(?: ([A-Za-z0-9\/_+\-]+))?\]\s+(.*)$/', $line, $m)) {
				try {
					$tz = new \DateTimeZone($m[2] !== '' && @timezone_open($m[2]) ? $m[2] : 'UTC');
					$ts = (new \DateTime($m[1], $tz))->getTimestamp();
				} catch (\Throwable $e) {
					continue;
				}

				$msg   = $m[3];
				$level = 'error';

				if (preg_match('/^(?:PHP\s+)?(Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Error|Exception)\s*:?\s*(.*)$/s', $msg, $mm)) {
					$map   = ['fatal error' => 'fatal', 'parse error' => 'fatal', 'error' => 'fatal', 'exception' => 'fatal',
						'warning' => 'warning', 'notice' => 'notice', 'strict standards' => 'notice', 'deprecated' => 'deprecated'];
					$level = $map[strtolower($mm[1])] ?? 'error';
					$msg   = $mm[2];
				} else {
					$level = 'notice'; // unclassified PHP log line
				}
			} elseif (preg_match('/^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:[+-]\d\d:\d\d|Z))\s+([A-Z]+)\s+\S+\s+(\S+)\s+(.*)$/', $line, $m)) {
				$ts = (int) strtotime($m[1]);

				if (in_array($m[2], ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'], true)) {
					$level = 'error';
				} elseif ($m[2] === 'WARNING') {
					$level = 'warning';
				} else {
					continue;
				}

				$msg = $m[4];
			} else {
				continue;
			}

			if ($ts <= 0) {
				continue;
			}

			$file = '';
			$ln   = 0;

			if (preg_match('~((?:/|[A-Za-z]:[\\\\/])[^\s:()\'"<>]+\.php)(?:\s+on line\s+|:|\()?(\d+)?~', $msg, $pm)) {
				$file = self::relative($pm[1]);
				$ln   = isset($pm[2]) ? (int) $pm[2] : 0;
			}

			$msg  = trim(preg_replace('/\s+/', ' ', $msg));
			$norm = strtolower($msg);
			$norm = preg_replace('~(?:/|[a-z]:[\\\\/])[^\s:()\'"<>]+~i', '<path>', $norm);
			$norm = preg_replace('/0x[0-9a-f]+|\d+/', '#', $norm);

			yield [
				'ts'    => $ts,
				'level' => $level,
				'msg'   => mb_substr($msg, 0, 300),
				'norm'  => mb_substr($norm, 0, 160),
				'file'  => $file,
				'line'  => $ln,
			];
		}
	}

	/** Path relative to the Joomla root, forward slashes. */
	public static function relative(string $path): string
	{
		$path = str_replace('\\', '/', $path);
		$root = rtrim(str_replace('\\', '/', JPATH_ROOT), '/') . '/';

		return stripos($path, $root) === 0 ? substr($path, strlen($root)) : $path;
	}

	/**
	 * Who owns the file: an extension, a template, the Joomla core or unknown.
	 *
	 * @return  array  ['type'=>extension|template|core|unknown,'name'=>string]
	 */
	public static function owner(string $rel): array
	{
		$rel = ltrim(str_replace('\\', '/', $rel), '/');

		if ($rel === '') {
			return ['type' => 'unknown', 'name' => ''];
		}

		if (preg_match('~^plugins/([^/]+)/([^/]+)/~', $rel, $m)) {
			return ['type' => 'extension', 'name' => 'plg_' . $m[1] . '_' . $m[2]];
		}

		if (preg_match('~^(?:administrator/)?modules/(mod_[^/]+)/~', $rel, $m)) {
			return ['type' => 'extension', 'name' => $m[1]];
		}

		if (preg_match('~^(?:administrator/)?components/(com_[^/]+)/~', $rel, $m)) {
			return in_array($m[1], self::CORE_COMPONENTS, true)
				? ['type' => 'core', 'name' => $m[1]]
				: ['type' => 'extension', 'name' => $m[1]];
		}

		if (preg_match('~^(?:administrator/)?templates/([^/]+)/~', $rel, $m)) {
			return ['type' => 'template', 'name' => 'tpl_' . $m[1]];
		}

		if (preg_match('~^libraries/vendor/([^/]+/[^/]+)/~', $rel, $m)) {
			return ['type' => 'core', 'name' => 'vendor/' . $m[1]];
		}

		if (preg_match('~^(libraries|includes|administrator/includes|cli|layouts|administrator/language|language)/~', $rel, $m)) {
			return ['type' => 'core', 'name' => $m[1]];
		}

		if (preg_match('~^[^/]+\.php$~', $rel)) {
			return ['type' => 'core', 'name' => $rel];
		}

		return ['type' => 'unknown', 'name' => ''];
	}
}

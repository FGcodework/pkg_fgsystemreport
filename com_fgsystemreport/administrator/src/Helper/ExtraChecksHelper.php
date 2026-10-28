<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Read-only probes added to the Security Check in 1.10.0: mail DNS,
 * overdue scheduled tasks, OPcache, HSTS and file-system leftovers.
 * Every method returns null when the probe is not applicable or cannot run
 * (so the caller simply omits the check instead of reporting a false alarm).
 *
 * @since  1.10.0
 */
final class ExtraChecksHelper
{
	/** Executable extensions that must never exist under /images */
	private const PHP_EXT = '/\.(php\d?|phtml|phar|pht|phps)$/i';

	/**
	 * SPF / DMARC / MX of the site's domain.
	 *
	 * @return  array|null  ['host','spf'=>bool,'dmarc'=>bool,'mx'=>bool,'policy'=>string]
	 */
	public static function mailDns(): ?array
	{
		if (!function_exists('dns_get_record')) {
			return null;
		}

		$host = strtolower((string) parse_url(Uri::root(), PHP_URL_HOST));
		$host = preg_replace('/^www\./', '', $host);

		if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || strpos($host, '.') === false) {
			return null;
		}

		$txt   = @dns_get_record($host, DNS_TXT);
		$dmarc = @dns_get_record('_dmarc.' . $host, DNS_TXT);
		$mx    = @dns_get_record($host, DNS_MX);

		// DNS lookup itself failed (no resolver / timeout): do not guess
		if ($txt === false && $dmarc === false && $mx === false) {
			return null;
		}

		$join = static function ($records): array {
			$out = [];

			foreach ((array) $records as $r) {
				if (isset($r['txt'])) {
					$out[] = (string) $r['txt'];
				} elseif (isset($r['entries'])) {
					$out[] = implode('', (array) $r['entries']);
				}
			}

			return $out;
		};

		$spf    = false;
		$policy = '';

		foreach ($join($txt) as $t) {
			if (stripos(ltrim($t), 'v=spf1') === 0) {
				$spf = true;
			}
		}

		$hasDmarc = false;

		foreach ($join($dmarc) as $t) {
			if (stripos(ltrim($t), 'v=dmarc1') === 0) {
				$hasDmarc = true;

				if (preg_match('/;\s*p\s*=\s*(\w+)/i', $t, $m)) {
					$policy = strtolower($m[1]);
				}
			}
		}

		return [
			'host'   => $host,
			'spf'    => $spf,
			'dmarc'  => $hasDmarc,
			'mx'     => !empty($mx),
			'policy' => $policy,
		];
	}

	/**
	 * Enabled scheduled tasks whose next execution is more than a day late.
	 *
	 * @return  array|null  list of task titles (empty = fine); null = no scheduler / no tasks
	 */
	public static function overdueTasks(): ?array
	{
		try {
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$limit = Factory::getDate('-1 day')->toSql();

			$total = (int) $db->setQuery(
				$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__scheduler_tasks'))
					->where($db->quoteName('state') . ' = 1')
			)->loadResult();

			if ($total === 0) {
				return null;
			}

			$q = $db->getQuery(true)
				->select($db->quoteName('title'))
				->from($db->quoteName('#__scheduler_tasks'))
				->where($db->quoteName('state') . ' = 1')
				->where($db->quoteName('next_execution') . ' IS NOT NULL')
				->where($db->quoteName('next_execution') . ' < ' . $db->quote($limit))
				->order($db->quoteName('next_execution') . ' ASC');

			return array_map('strval', (array) $db->setQuery($q, 0, 10)->loadColumn());
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * @return  string|null  'ok' | 'off' | 'full'; null in the CLI (own php.ini) or when undeterminable
	 */
	public static function opcache(): ?string
	{
		if (PHP_SAPI === 'cli') {
			return null;
		}

		if (!extension_loaded('Zend OPcache') || !filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
			return 'off';
		}

		if (function_exists('opcache_get_status')) {
			$status = @opcache_get_status(false);

			if (is_array($status) && !empty($status['cache_full'])) {
				return 'full';
			}
		}

		return 'ok';
	}

	/**
	 * Strict-Transport-Security header of the home page.
	 *
	 * @return  string|null  header value ('' = missing); null when not applicable / unreachable
	 */
	public static function hsts(): ?string
	{
		$root = rtrim(Uri::root(), '/') . '/';

		if (!function_exists('curl_init') || stripos($root, 'https://') !== 0) {
			return null;
		}

		$value = '';
		$ch    = curl_init($root);

		curl_setopt_array($ch, [
			CURLOPT_NOBODY         => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 6,
			CURLOPT_CONNECTTIMEOUT => 4,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_USERAGENT      => 'FG System Report',
			CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$value) {
				if (stripos($line, 'strict-transport-security:') === 0) {
					$value = trim(substr($line, 26));
				}

				return strlen($line);
			},
		]);

		$ok = curl_exec($ch);
		curl_close($ch);

		return $ok === false ? null : $value;
	}

	/**
	 * Executable PHP files inside /images (budgeted walk).
	 *
	 * @return  array|null  ['files'=>list<string> (max 10),'count'=>int,'partial'=>bool]
	 */
	public static function phpInImages(): ?array
	{
		$dir = JPATH_ROOT . '/images';

		if (!is_dir($dir) || !is_readable($dir)) {
			return null;
		}

		$files   = [];
		$count   = 0;
		$seen    = 0;
		$partial = false;
		$start   = microtime(true);

		try {
			$it = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY,
				\RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ($it as $f) {
				if (++$seen > 60000 || (microtime(true) - $start) > 2.5) {
					$partial = true;
					break;
				}

				if ($f->isFile() && preg_match(self::PHP_EXT, $f->getFilename())) {
					$count++;

					if (count($files) < 10) {
						$files[] = ltrim(str_replace(JPATH_ROOT, '', $f->getPathname()), '/\\');
					}
				}
			}
		} catch (\Throwable $e) {
			return null;
		}

		return ['files' => $files, 'count' => $count, 'partial' => $partial];
	}

	/**
	 * Leftovers directly in the web root.
	 *
	 * @return  array|null  ['install'=>bool,'config'=>list,'backups'=>list,'tools'=>list]
	 */
	public static function rootLeftovers(): ?array
	{
		if (!is_readable(JPATH_ROOT)) {
			return null;
		}

		$out = ['install' => false, 'config' => [], 'backups' => [], 'tools' => []];

		try {
			foreach (new \DirectoryIterator(JPATH_ROOT) as $f) {
				if ($f->isDot() || $f->isLink()) {
					continue;
				}

				$name = $f->getFilename();
				$low  = strtolower($name);

				if ($f->isDir()) {
					if ($low === 'installation') {
						$out['install'] = true;
					} elseif (in_array($low, ['phpmyadmin', 'pma', 'adminer'], true)) {
						$out['tools'][] = $name . '/';
					}

					continue;
				}

				if (preg_match('/^configuration\.php.+/i', $name) || preg_match('/^configuration.*\.(bak|old|orig|save|txt|dist)$/i', $name)) {
					$out['config'][] = $name;
				} elseif (preg_match('/\.(sql|zip|tar|gz|tgz|bz2|7z|rar|jpa|jps|bak|old|orig)$/i', $name)) {
					$out['backups'][] = $name;
				} elseif (preg_match('/^(adminer|phpinfo|info|test|shell|wso|c99|r57|pma)[\w.\-]*\.php$/i', $name)) {
					$out['tools'][] = $name;
				}
			}
		} catch (\Throwable $e) {
			return null;
		}

		foreach (['config', 'backups', 'tools'] as $k) {
			sort($out[$k]);
			$out[$k] = array_slice($out[$k], 0, 10);
		}

		return $out;
	}
}

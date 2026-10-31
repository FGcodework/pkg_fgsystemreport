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
	 * Response headers of the home page (one HEAD request, cached for the
	 * request). Names are lower-case; repeated headers are joined with ", ".
	 * Only the final response after redirects is kept.
	 *
	 * @return  array<string,string>|null  null when curl is missing or the site is unreachable
	 */
	public static function homeHeaders(): ?array
	{
		static $cache = false;

		if ($cache !== false) {
			return $cache;
		}

		$cache = null;

		if (!function_exists('curl_init')) {
			return null;
		}

		$root    = rtrim(Uri::root(), '/') . '/';
		$headers = [];
		$verify  = false;

		try {
			$verify = (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_fgsystemreport')->get('probe_verify_tls', 0) === 1;
		} catch (\Throwable $e) {
			// default: do not verify (only headers are read from this very site)
		}

		$ch = curl_init($root);

		curl_setopt_array($ch, [
			CURLOPT_NOBODY         => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_TIMEOUT        => 6,
			CURLOPT_CONNECTTIMEOUT => 4,
			CURLOPT_SSL_VERIFYPEER => $verify,
			CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
			CURLOPT_USERAGENT      => 'FG System Report',
			CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$headers) {
				if (stripos($line, 'HTTP/') === 0) {
					$headers = []; // a new response (redirect hop): forget the previous one
				} elseif (strpos($line, ':') !== false) {
					[$name, $value] = explode(':', $line, 2);
					$name           = strtolower(trim($name));
					$value          = trim($value);
					$headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
				}

				return strlen($line);
			},
		]);

		$ok = curl_exec($ch);
		curl_close($ch);

		if ($ok !== false) {
			$cache = $headers;
		}

		return $cache;
	}

	/**
	 * Strict-Transport-Security header of the home page.
	 *
	 * @return  string|null  header value ('' = missing); null when not applicable / unreachable
	 */
	public static function hsts(): ?string
	{
		if (stripos(rtrim(Uri::root(), '/') . '/', 'https://') !== 0) {
			return null;
		}

		$headers = self::homeHeaders();

		return $headers === null ? null : ($headers['strict-transport-security'] ?? '');
	}

	/**
	 * Which of the basic browser-hardening headers are missing.
	 *
	 * @param   array<string,string>  $headers  lower-case header name => value
	 *
	 * @return  string[]  names of the missing protections (empty = all present)
	 */
	public static function missingSecurityHeaders(array $headers): array
	{
		$missing = [];

		if (stripos($headers['x-content-type-options'] ?? '', 'nosniff') === false) {
			$missing[] = 'X-Content-Type-Options';
		}

		if (trim($headers['x-frame-options'] ?? '') === ''
			&& stripos($headers['content-security-policy'] ?? '', 'frame-ancestors') === false) {
			$missing[] = 'X-Frame-Options / CSP frame-ancestors';
		}

		if (trim($headers['referrer-policy'] ?? '') === '') {
			$missing[] = 'Referrer-Policy';
		}

		return $missing;
	}

	/** Extensions that must never be handled by PHP (images, text, archives ...). */
	private const NON_PHP_EXT = 'jpe?g|png|gif|ico|svg|webp|bmp|txt|css|js|json|xml|pdf|zip|log';

	/**
	 * Inspect the text of one server / PHP configuration file for settings
	 * attackers use to stay in a site: code that runs before or after every
	 * script, or PHP handlers attached to images and other non-PHP files.
	 * Pure function (no file access), so it can be tested directly.
	 *
	 * @param   string  $filename  base name: ".htaccess", ".user.ini" or "php.ini"
	 * @param   string  $content   file content
	 *
	 * @return  list<array{issue:string,detail:string}>  issue: prepend | append | handler_media | handler_php
	 */
	public static function inspectConfigFile(string $filename, string $content): array
	{
		$issues   = [];
		$htaccess = strcasecmp($filename, '.htaccess') === 0;
		$scope    = '';
		$ext      = self::NON_PHP_EXT;

		foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
			$line = trim($line);

			if ($line === '' || $line[0] === '#' || $line[0] === ';') {
				continue;
			}

			// auto_prepend_file / auto_append_file
			$pattern = $htaccess
				? '/^php_(?:admin_)?value\s+auto_(prepend|append)_file\s+(.+)$/i'
				: '/^auto_(prepend|append)_file\s*=\s*(.+)$/i';

			if (preg_match($pattern, $line, $m)) {
				$value = trim($m[2], " \t\"'");

				if ($value !== '' && strcasecmp($value, 'none') !== 0) {
					$issues[] = ['issue' => strtolower($m[1]), 'detail' => mb_substr($value, 0, 120)];
				}

				continue;
			}

			if (!$htaccess) {
				continue;
			}

			// scope of the following SetHandler / ForceType
			if (preg_match('/^<(?:FilesMatch|Files|LocationMatch|Location)\s+(.+?)>$/i', $line, $m)) {
				$scope = $m[1];
				continue;
			}

			if (preg_match('/^<\/(?:FilesMatch|Files|LocationMatch|Location)>/i', $line)) {
				$scope = '';
				continue;
			}

			if (preg_match('/^(AddHandler|AddType)\s+(\S+)\s+(.+)$/i', $line, $m)) {
				if (stripos($m[2], 'php') === false) {
					continue;
				}

				$bad = [];

				foreach (preg_split('/\s+/', trim($m[3])) as $e) {
					$e = strtolower(ltrim(trim($e, "\"'"), '.'));

					if ($e !== '' && preg_match('/^(?:' . $ext . ')$/', $e)) {
						$bad[] = '.' . $e;
					}
				}

				$issues[] = $bad
					? ['issue' => 'handler_media', 'detail' => mb_substr($m[1] . ' ' . $m[2] . ' ' . implode(' ', $bad), 0, 120)]
					: ['issue' => 'handler_php', 'detail' => mb_substr($m[1] . ' ' . $m[2], 0, 120)];

				continue;
			}

			if (preg_match('/^(SetHandler|ForceType)\s+(.+)$/i', $line, $m) && stripos($m[2], 'php') !== false) {
				$onMedia = $scope !== '' && preg_match('/(?<![a-z0-9])(?:' . $ext . ')(?![a-z0-9])/i', $scope);

				$issues[] = [
					'issue'  => $onMedia ? 'handler_media' : 'handler_php',
					'detail' => mb_substr(($scope !== '' ? $scope . ' ' : '') . $m[1] . ' ' . trim($m[2], " \t\"'"), 0, 120),
				];
			}
		}

		return $issues;
	}

	/**
	 * Persistence directives in the server / PHP configuration files of the
	 * site: the web root and /administrator (any finding counts) and every
	 * such file found under the upload folders (there even a plain PHP
	 * handler counts, that is how a planted .htaccess makes uploads run).
	 *
	 * @return  array|null  ['findings'=>list<string> (max 10),'count'=>int,'critical'=>int,'partial'=>bool]
	 */
	public static function checkPersistenceDirectives(): ?array
	{
		if (!is_readable(JPATH_ROOT)) {
			return null;
		}

		$names    = ['.htaccess', '.user.ini', 'php.ini'];
		$files    = [];
		$findings = [];
		$count    = 0;
		$critical = 0;
		$partial  = false;

		foreach (['', '/administrator'] as $dir) {
			foreach ($names as $n) {
				$files[JPATH_ROOT . $dir . '/' . $n] = false;
			}
		}

		// Upload folders: budgeted walk for planted config files
		$seen  = 0;
		$start = microtime(true);

		foreach (['/images', '/media', '/tmp'] as $sub) {
			$base = JPATH_ROOT . $sub;

			if (!is_dir($base) || !is_readable($base)) {
				continue;
			}

			try {
				$it = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::LEAVES_ONLY,
					\RecursiveIteratorIterator::CATCH_GET_CHILD
				);

				foreach ($it as $f) {
					if (++$seen > 60000 || (microtime(true) - $start) > 2.5) {
						$partial = true;
						break 2;
					}

					if (in_array(strtolower($f->getFilename()), $names, true) && $f->isFile()) {
						$files[$f->getPathname()] = true;
					}
				}
			} catch (\Throwable $e) {
				// unreadable folder: skip
			}
		}

		foreach ($files as $path => $inUploads) {
			if (!is_file($path) || !is_readable($path)) {
				continue;
			}

			$content = @file_get_contents($path, false, null, 0, 65536);

			if ($content === false || $content === '') {
				continue;
			}

			foreach (self::inspectConfigFile(basename($path), $content) as $i) {
				if ($i['issue'] === 'handler_php' && !$inUploads) {
					continue; // an ordinary PHP handler in the web root / administrator is normal
				}

				$count++;
				$isCritical = $inUploads || $i['issue'] === 'handler_media';
				$critical  += $isCritical ? 1 : 0;

				if (count($findings) < 10) {
					$findings[] = ltrim(str_replace(JPATH_ROOT, '', $path), '/\\') . ': ' . $i['detail'];
				}
			}
		}

		return ['findings' => $findings, 'count' => $count, 'critical' => $critical, 'partial' => $partial];
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

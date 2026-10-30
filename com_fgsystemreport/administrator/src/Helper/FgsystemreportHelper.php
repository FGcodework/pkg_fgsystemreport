<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Cache\CacheControllerFactoryInterface;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Component\ComponentHelper;

/**
 * Fgsystemreport helper.
 *
 * @since  1.0.0
 */
class FgsystemreportHelper
{
	/**
	 * Gets the files attached to an item
	 *
	 * @param   int     $pk     The item's id
	 *
	 * @param   string  $table  The table's name
	 *
	 * @param   string  $field  The field's name
	 *
	 * @return  array  The files
	 */

	/**
	 * Get the ordered list of installed extensions with translated names,
	 * package grouping and available-update info. Shared by the PDF report
	 * and the dashboard view.
	 *
	 * Each item gets extra properties: display_name, installed_version,
	 * available_version, is_package, is_child, parent_package, has_update.
	 *
	 * @param   bool  $includeCore  Include Joomla core (locked) extensions
	 *
	 * @return  array  Ordered list of extension objects
	 */

	/**
	 * Cached wrapper around buildSecurityChecks(). The underlying checks
	 * involve several outbound network calls (Joomla version, SSL
	 * certificate, exposed-file probes) and a full extensions table scan
	 * with per-extension language loading - expensive enough that running
	 * it on every single page load (dashboard + Security Check) adds up,
	 * especially when outbound connections are slow or blocked and each
	 * one has to time out. Cached for 60 minutes via Joomla's own cache
	 * controller; pass $forceRefresh (e.g. from a "Check again" button) to
	 * discard the cached result and recompute immediately.
	 *
	 * @param   string|null  $precomputedLatestVersion  Already-fetched Joomla
	 *                                                   version, to avoid a
	 *                                                   second live check
	 * @param   array|null   $precomputedExtensions      Already-fetched
	 *                                                    getInstalledExtensions(true)
	 *                                                    result, to avoid a
	 *                                                    second full table scan
	 * @param   bool         $forceRefresh               Discard the cache and
	 *                                                    recompute now
	 *
	 * @return  array
	 */
	/**
	 * Turn a set of Security Check results (from getSecurityChecks()) into
	 * a single 0-100 "site health" score plus a short list of the most
	 * important things to fix - meant for an executive-summary view (e.g.
	 * the front page of the PDF report) rather than the full detail table.
	 *
	 * Scoring: starts at 100, -15 per 'danger' finding, -5 per 'warning'
	 * finding, floor 0. 'ok'/'info' findings don't affect the score.
	 *
	 * @param   array  $checks  Result of getSecurityChecks()
	 *
	 * @return  object  ->score (int 0-100), ->dangerCount (int),
	 *                  ->warningCount (int), ->topRecommendations (array
	 *                  of objects with ->label and ->note, worst first,
	 *                  max 3)
	 */
	public static function calculateSecurityScore(array $checks): object
	{
		$score        = 100;
		$dangerCount  = 0;
		$warningCount = 0;
		$dangerItems  = [];
		$warningItems = [];

		foreach ($checks as $check) {
			if ($check->status === 'danger') {
				$dangerCount++;
				$score -= 15;

				if ($check->note !== '') {
					$dangerItems[] = $check;
				}
			} elseif ($check->status === 'warning') {
				$warningCount++;
				$score -= 5;

				if ($check->note !== '') {
					$warningItems[] = $check;
				}
			}
		}

		$topRecommendations = array_slice(array_merge($dangerItems, $warningItems), 0, 3);

		$result                     = new \stdClass();
		$result->score              = max(0, min(100, $score));
		$result->dangerCount        = $dangerCount;
		$result->warningCount       = $warningCount;
		$result->topRecommendations = $topRecommendations;

		return $result;
	}

	/**
	 * Ids of all user groups that hold core.admin (not just the default
	 * "Super Users" group, id 8). Falls back to 8 if none can be determined.
	 *
	 * @return  int[]
	 */
	public static function getSuperUserGroupIds(): array
	{
		static $ids = null;

		if ($ids === null) {
			try {
				$db  = Factory::getContainer()->get('DatabaseDriver');
				$all = $db->setQuery('SELECT id FROM #__usergroups')->loadColumn() ?: [];
				$ids = array_values(array_map('intval', array_filter($all, static function ($groupId) {
					return Access::checkGroup((int) $groupId, 'core.admin');
				})));
			} catch (\Throwable $e) {
				$ids = [];
			}

			if (!$ids) {
				$ids = [8];
			}
		}

		return $ids;
	}

	public static function getSecurityChecks(
		?string $precomputedLatestVersion = null,
		?array $precomputedExtensions = null,
		bool $forceRefresh = false
	): array {
		try {
			$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
				->createCacheController('callback', ['defaultgroup' => 'com_fgsystemreport', 'lifetime' => 60]);

			if ($forceRefresh) {
				$cache->clean();
			}

			return AcceptedRiskHelper::apply((array) $cache->get(
				[self::class, 'buildSecurityChecks'],
				[$precomputedLatestVersion, $precomputedExtensions],
				'security-checks'
			));
		} catch (\Throwable $e) {
			// Cache unavailable for any reason (e.g. disabled/misconfigured
			// cache handler) - fall back to computing directly rather than
			// breaking the page.
			return AcceptedRiskHelper::apply(self::buildSecurityChecks($precomputedLatestVersion, $precomputedExtensions));
		}
	}

	/**
	 * Run a set of security/health checks on the site.
	 *
	 * Each item: label, status (ok|warning|danger|info), value, note.
	 *
	 * @return  array
	 */
	public static function buildSecurityChecks(?string $precomputedLatestVersion = null, ?array $precomputedExtensions = null): array
	{
		$app    = Factory::getApplication();
		$db     = Factory::getContainer()->get('DatabaseDriver');
		$checks = [];

		$add = static function (string $label, string $status, string $value, string $note = '') use (&$checks) {
			$checks[] = (object) [
				'key'    => $label,
				'label'  => Text::_($label),
				'status' => $status,
				'value'  => $value,
				'note'   => $note,
			];
		};

		// 1) Joomla core version vs. the latest available release.
		// Prefers a live check against the official Joomla download servers;
		// falls back to the local #__updates cache if outbound HTTPS is
		// blocked (common on some shared hosting) - so the check still works
		// even without network access, just less precisely.
		// A caller that already fetched the latest version (e.g. the
		// dashboard) can pass it in to avoid a second network round-trip.
		$latest = $precomputedLatestVersion ?? self::fetchLatestJoomlaVersion();

		if ($latest !== null) {
			if (version_compare(JVERSION, $latest, '<')) {
				$add('COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE', 'warning', JVERSION,
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_JOOMLA_UPDATE_AVAILABLE', $latest));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE', 'ok', JVERSION,
					Text::_('COM_FGSYSTEMREPORT_SEC_JOOMLA_UP_TO_DATE'));
			}
		} else {
			// Live check unreachable - fall back to the local update cache
			$coreUpdate = $db->setQuery(
				'SELECT version FROM #__updates WHERE extension_id = 700'
			)->loadResult();

			if ($coreUpdate) {
				$add('COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE', 'danger', JVERSION,
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_JOOMLA_UPDATE_AVAILABLE', $coreUpdate));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE', 'info', JVERSION,
					Text::_('COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE_UNVERIFIED'));
			}
		}

		// 2) PHP version vs. security-support EOL.
		//
		// *** UPDATE THIS TABLE ONCE A YEAR ***
		// PHP releases exactly one new minor version every November and
		// publishes EOL dates well in advance - see https://www.php.net/supported-versions.php
		// Last verified: 2026-09 (added 8.5, EOL 2029-12-31; 8.1-8.4
		// dates confirmed unchanged under PHP's 2-year-active +
		// 2-year-security-only policy).
		$phpEol = [
			'7.4' => '2022-11-28',
			'8.0' => '2023-11-26',
			'8.1' => '2025-12-31',
			'8.2' => '2026-12-31',
			'8.3' => '2027-12-31',
			'8.4' => '2028-12-31',
			'8.5' => '2029-12-31',
		];
		$phpMinor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

		if (isset($phpEol[$phpMinor])) {
			$eolTs = strtotime($phpEol[$phpMinor]);
			if ($eolTs < time()) {
				$add('COM_FGSYSTEMREPORT_SEC_PHP', 'danger', PHP_VERSION,
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_PHP_EOL', $phpEol[$phpMinor]));
			} elseif ($eolTs < strtotime('+6 months')) {
				$add('COM_FGSYSTEMREPORT_SEC_PHP', 'warning', PHP_VERSION,
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_PHP_EOL_SOON', $phpEol[$phpMinor]));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_PHP', 'ok', PHP_VERSION,
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_PHP_SUPPORTED', $phpEol[$phpMinor]));
			}
		} else {
			// A PHP version newer than this table knows about (this
			// component hasn't been updated since it was released) is,
			// by definition, still within its first ~2 years of support -
			// treat it as fine rather than showing a blank, uncoloured
			// "info" row, but say so explicitly since we can't state an
			// exact EOL date for it.
			$latestKnownMinor = array_key_last($phpEol);

			if (version_compare($phpMinor, $latestKnownMinor, '>')) {
				$add('COM_FGSYSTEMREPORT_SEC_PHP', 'ok', PHP_VERSION,
					Text::_('COM_FGSYSTEMREPORT_SEC_PHP_NEWER_THAN_KNOWN'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_PHP', 'info', PHP_VERSION, '');
			}
		}

		// 2b) Database type and version vs. Joomla's minimum supported
		// version. As of Joomla 6 these are unchanged from Joomla 5:
		// MySQL 8.0.13+, MariaDB 10.4+, PostgreSQL 12+ (verified against
		// Joomla's own technical requirements docs, 2026-09) - update if
		// a future Joomla release raises them.
		$dbType = method_exists($db, 'getServerType') ? $db->getServerType() : $db->name;
		$dbVersion = $db->getVersion();

		$dbLabels = [
			'mysql'      => 'MySQL',
			'mariadb'    => 'MariaDB',
			'postgresql' => 'PostgreSQL',
			'pgsql'      => 'PostgreSQL',
			'sqlsrv'     => 'SQL Server',
			'sqlazure'   => 'SQL Server',
		];
		$dbLabel = $dbLabels[$dbType] ?? ucfirst((string) $dbType);

		// Extract a clean "X.Y.Z" from strings like "8.0.36-cll-lve" or "10.11.6-MariaDB"
		preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', (string) $dbVersion, $vm);
		$dbMajor = isset($vm[1]) ? (int) $vm[1] : 0;
		$dbMinor = isset($vm[2]) ? (int) $vm[2] : 0;
		$dbPatch = isset($vm[3]) ? (int) $vm[3] : 0;

		$dbStatus = 'info';
		$dbNote   = '';

		// Joomla 5/6 minimum requirements
		if ($dbType === 'mariadb' || stripos((string) $dbVersion, 'mariadb') !== false) {
			$dbLabel = 'MariaDB';
			if ($dbMajor < 10 || ($dbMajor === 10 && $dbMinor < 4) || ($dbMajor === 10 && $dbMinor === 4 && $dbPatch < 5)) {
				$dbStatus = 'warning';
				$dbNote   = Text::sprintf('COM_FGSYSTEMREPORT_SEC_DB_MIN_VERSION', 'MariaDB 10.4.5+');
			} else {
				$dbStatus = 'ok';
			}
		} elseif ($dbType === 'mysql') {
			if ($dbMajor < 8 || ($dbMajor === 8 && $dbMinor === 0 && $dbPatch < 13)) {
				$dbStatus = 'warning';
				$dbNote   = Text::sprintf('COM_FGSYSTEMREPORT_SEC_DB_MIN_VERSION', 'MySQL 8.0.13+');
			} else {
				$dbStatus = 'ok';
			}
		} elseif (in_array($dbType, ['postgresql', 'pgsql'], true)) {
			if ($dbMajor < 12) {
				$dbStatus = 'warning';
				$dbNote   = Text::sprintf('COM_FGSYSTEMREPORT_SEC_DB_MIN_VERSION', 'PostgreSQL 12+');
			} else {
				$dbStatus = 'ok';
			}
		}

		$add('COM_FGSYSTEMREPORT_SEC_DB_VERSION', $dbStatus, $dbLabel . ' ' . $dbVersion, $dbNote);

		// 2c) PHP extensions required by Fero's own custom Joomla extensions
		// (Favicon Manager, phocaimage, Watermark plugin, Quick Admin Notes,
		// Simple Adminer, HTML5 Flipping Book / Thumbler, etc.)
		$phpExtensions = [
			'gd'          => 'COM_FGSYSTEMREPORT_SEC_EXT_GD_NOTE',
			'imagick'     => 'COM_FGSYSTEMREPORT_SEC_EXT_IMAGICK_NOTE',
			'exif'        => 'COM_FGSYSTEMREPORT_SEC_EXT_EXIF_NOTE',
			'openssl'     => 'COM_FGSYSTEMREPORT_SEC_EXT_OPENSSL_NOTE',
			'pdo_mysql'   => 'COM_FGSYSTEMREPORT_SEC_EXT_PDOMYSQL_NOTE',
			'curl'        => 'COM_FGSYSTEMREPORT_SEC_EXT_CURL_NOTE',
			'zip'         => 'COM_FGSYSTEMREPORT_SEC_EXT_ZIP_NOTE',
		];

		foreach ($phpExtensions as $extName => $noteKey) {
			$loaded = extension_loaded($extName);
			$note   = Text::_($noteKey);

			// Imagick: also flag missing SVG delegate (used by Favicon Manager for SVG favicons)
			if ($extName === 'imagick' && $loaded && class_exists('\Imagick')) {
				$formats = \Imagick::queryFormats('SVG');
				if (empty($formats)) {
					$add('COM_FGSYSTEMREPORT_SEC_EXT_IMAGICK', 'warning', Text::_('JYES'),
						Text::_('COM_FGSYSTEMREPORT_SEC_EXT_IMAGICK_NO_SVG'));
					continue;
				}
			}

			$label = 'COM_FGSYSTEMREPORT_SEC_EXT_' . strtoupper(str_replace('_', '', $extName));
			$add($label, $loaded ? 'ok' : 'warning', $loaded ? Text::_('JYES') : Text::_('JNO'),
				$loaded ? '' : $note);
		}

		// 3) configuration.php permissions
		$configFile = JPATH_CONFIGURATION . '/configuration.php';

		if (is_file($configFile)) {
			$perms = fileperms($configFile) & 0777;
			$permsText = sprintf('%o', $perms);

			if ($perms & 0002) {
				$add('COM_FGSYSTEMREPORT_SEC_CONFIG_PERMS', 'danger', $permsText,
					Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_WORLD_WRITABLE'));
			} elseif (is_writable($configFile)) {
				$add('COM_FGSYSTEMREPORT_SEC_CONFIG_PERMS', 'warning', $permsText,
					Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_WRITABLE'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_CONFIG_PERMS', 'ok', $permsText,
					Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_OK'));
			}
		}

		// 3b) Publicly exposed sensitive files (.env, .git, backup/config
		// leftovers, phpinfo scripts) - a live check against the site's own
		// front end, since these are usually forgotten cleanup steps.
		$exposedCheck = self::checkExposedSensitiveFiles();

		if (!$exposedCheck->available) {
			$add('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES_CHECK_FAILED'));
		} elseif (!$exposedCheck->reliable) {
			$add('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES', 'info', Text::_('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES_UNKNOWN'),
				Text::_('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES_SOFT_404'));
		} elseif (!empty($exposedCheck->exposed)) {
			$add('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES', 'danger', (string) count($exposedCheck->exposed),
				Text::sprintf('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES_FOUND', implode(', ', $exposedCheck->exposed)));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES', 'ok', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_EXPOSED_FILES_NONE'));
		}

		// 4) Debug mode
		$debug = (bool) $app->get('debug');
		$add('COM_FGSYSTEMREPORT_SEC_DEBUG', $debug ? 'warning' : 'ok',
			$debug ? Text::_('JYES') : Text::_('JNO'),
			$debug ? Text::_('COM_FGSYSTEMREPORT_SEC_DEBUG_ON') : '');

		// 5) Error reporting level
		$errRep = (string) $app->get('error_reporting', 'default');
		$errBad = in_array($errRep, ['maximum', 'development'], true);
		$add('COM_FGSYSTEMREPORT_SEC_ERROR_REPORTING', $errBad ? 'warning' : 'ok', $errRep,
			$errBad ? Text::_('COM_FGSYSTEMREPORT_SEC_ERROR_REPORTING_HIGH') : '');

		// 6) HTTPS enforcement (0 = none, 1 = admin only, 2 = entire site)
		$forceSsl = (int) $app->get('force_ssl', 0);
		$sslLabels = [
			0 => Text::_('COM_FGSYSTEMREPORT_SEC_SSL_NONE'),
			1 => Text::_('COM_FGSYSTEMREPORT_SEC_SSL_ADMIN'),
			2 => Text::_('COM_FGSYSTEMREPORT_SEC_SSL_FULL'),
		];
		$add('COM_FGSYSTEMREPORT_SEC_FORCE_SSL', $forceSsl === 2 ? 'ok' : 'warning',
			$sslLabels[$forceSsl] ?? (string) $forceSsl,
			$forceSsl === 2 ? '' : Text::_('COM_FGSYSTEMREPORT_SEC_SSL_RECOMMEND'));

		// 6b) SSL certificate expiry - live check against the site's own
		// domain on port 443, independent of the force_ssl setting above.
		$sslCert = self::checkSslCertificate();

		if ($sslCert === null) {
			$add('COM_FGSYSTEMREPORT_SEC_SSL_CERT', 'info', Text::_('COM_FGSYSTEMREPORT_SEC_SSL_CERT_UNKNOWN'),
				Text::_('COM_FGSYSTEMREPORT_SEC_SSL_CERT_CHECK_FAILED'));
		} else {
			$certValue = $sslCert->validTo . ' (' . $sslCert->daysLeft . ' ' . Text::_('COM_FGSYSTEMREPORT_SEC_SSL_CERT_DAYS') . ')';
			$certNote  = $sslCert->issuer !== '' ? Text::sprintf('COM_FGSYSTEMREPORT_SEC_SSL_CERT_ISSUER', $sslCert->issuer) : '';

			if ($sslCert->daysLeft < 0) {
				$add('COM_FGSYSTEMREPORT_SEC_SSL_CERT', 'danger', $certValue,
					Text::_('COM_FGSYSTEMREPORT_SEC_SSL_CERT_EXPIRED'));
			} elseif ($sslCert->daysLeft <= 14) {
				$add('COM_FGSYSTEMREPORT_SEC_SSL_CERT', 'danger', $certValue,
					Text::_('COM_FGSYSTEMREPORT_SEC_SSL_CERT_EXPIRING_SOON'));
			} elseif ($sslCert->daysLeft <= 30) {
				$add('COM_FGSYSTEMREPORT_SEC_SSL_CERT', 'warning', $certValue,
					Text::_('COM_FGSYSTEMREPORT_SEC_SSL_CERT_EXPIRING_SOON'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_SSL_CERT', 'ok', $certValue, $certNote);
			}
		}

		// 7) .htaccess present
		$htaccess = is_file(JPATH_ROOT . '/.htaccess');
		$add('COM_FGSYSTEMREPORT_SEC_HTACCESS', $htaccess ? 'ok' : 'info',
			$htaccess ? Text::_('JYES') : Text::_('JNO'),
			$htaccess ? '' : Text::_('COM_FGSYSTEMREPORT_SEC_HTACCESS_MISSING'));

		// 8) Super User accounts (any group with core.admin, not just the
		// default "Super Users" group - a client-created group inherited
		// from it, or core.admin granted directly to another group, would
		// otherwise be invisible here) + stale logins
		$superUserGroupIds = self::getSuperUserGroupIds();

		if (!empty($superUserGroupIds)) {
			$superUsers = $db->setQuery(
				'SELECT DISTINCT u.id, u.username, u.block, u.lastvisitDate FROM #__users u'
				. ' INNER JOIN #__user_usergroup_map m ON m.user_id = u.id'
				. ' WHERE m.group_id IN (' . implode(',', array_map('intval', $superUserGroupIds)) . ')'
			)->loadObjectList() ?: [];
		} else {
			$superUsers = [];
		}

		$names = [];
		$stale = 0;

		foreach ($superUsers as $su) {
			$never = empty($su->lastvisitDate) || $su->lastvisitDate === '0000-00-00 00:00:00';
			$old   = !$never && strtotime($su->lastvisitDate) < strtotime('-180 days');

			if (!$su->block && ($never || $old)) {
				$stale++;
				$names[] = $su->username . ' (!)';
			} else {
				$names[] = $su->username;
			}
		}

		$suStatus = ($stale > 0 || count($superUsers) > 3) ? 'warning' : 'ok';
		$suNote   = $stale > 0
			? Text::sprintf('COM_FGSYSTEMREPORT_SEC_SUPERUSERS_STALE', $stale)
			: (count($superUsers) > 3 ? Text::_('COM_FGSYSTEMREPORT_SEC_SUPERUSERS_MANY') : '');
		$add('COM_FGSYSTEMREPORT_SEC_SUPERUSERS', $suStatus,
			count($superUsers) . ' (' . implode(', ', $names) . ')', $suNote);

		// 8b) Failed Super User login attempts, from Joomla's own User
		// Actions Log (requires the "Action Log - Joomla" plugin to be
		// enabled - Joomla logs failed backend logins there by design).
		$actionLogEnabled = (int) $db->setQuery(
			"SELECT COUNT(*) FROM #__extensions WHERE type = 'plugin' AND folder = 'actionlog'"
			. " AND element = 'joomla' AND enabled = 1"
		)->loadResult();

		if (!$actionLogEnabled) {
			$add('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS_LOG_DISABLED'));
		} else {
			$superUserNames = array_map(static function ($su) {
				return $su->username;
			}, $superUsers);

			$params     = ComponentHelper::getParams('com_fgsystemreport');
			$windowDays = max(1, (int) $params->get('failed_login_days', 7));
			$dangerAt   = max(1, (int) $params->get('failed_login_threshold', 10));

			$failedCount = 0;

			if (!empty($superUserNames)) {
				try {
					// Fetch candidate rows without filtering by username in SQL
					// (the message column is JSON, so a LIKE substring match
					// against it is imprecise - e.g. a user named "admin" would
					// match "administrator", "com_admin", "admin2"...). The row
					// count in a security-event log over a short window is
					// small, so filtering exactly in PHP is cheap.
					$rows = $db->setQuery(
						$db->getQuery(true)
							->select(['message'])
							->from($db->quoteName('#__action_logs'))
							->where($db->quoteName('message_language_key') . ' LIKE ' . $db->quote('%LOGIN_FAILED%'))
							->where('log_date >= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-' . $windowDays . ' days'))))
					)->loadColumn() ?: [];

					foreach ($rows as $rawMessage) {
						$decoded = json_decode((string) $rawMessage, true);

						if (is_array($decoded) && isset($decoded['username'])
							&& in_array($decoded['username'], $superUserNames, true)) {
							$failedCount++;
						}
					}
				} catch (\Throwable $e) {
					$failedCount = 0;
				}
			}

			if ($failedCount > $dangerAt) {
				$add('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS', 'danger', (string) $failedCount,
					Text::_('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS_HIGH'));
			} elseif ($failedCount > 0) {
				$add('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS', 'warning', (string) $failedCount,
					Text::_('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS_SOME'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_FAILED_LOGINS', 'ok', (string) $failedCount, '');
			}
		}


		// 9) Extensions with available updates (core + third party)
		$updCount = 0;

		foreach ($precomputedExtensions ?? self::getInstalledExtensions(true) as $ext) {
			if ($ext->has_update) {
				$updCount++;
			}
		}

		$add('COM_FGSYSTEMREPORT_SEC_EXT_UPDATES', $updCount > 0 ? 'danger' : 'ok',
			(string) $updCount,
			$updCount > 0 ? Text::_('COM_FGSYSTEMREPORT_SEC_EXT_UPDATES_PENDING') : '');

		// 9b) Backup status (Akeeba Backup, if installed)
		$backupStatus = self::checkBackupStatus();

		if ($backupStatus === null) {
			$add('COM_FGSYSTEMREPORT_SEC_BACKUP', 'info', Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_NOT_DETECTED'),
				Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_NOT_DETECTED_NOTE'));
		} elseif ($backupStatus->lastBackupTimestamp === null) {
			$add('COM_FGSYSTEMREPORT_SEC_BACKUP', 'danger', Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_NEVER'),
				Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_NEVER_NOTE'));
		} else {
			$daysAgo = (int) floor((time() - $backupStatus->lastBackupTimestamp) / 86400);
			$backupValue = date('Y-m-d', $backupStatus->lastBackupTimestamp) . ' (' . $daysAgo . ' '
				. Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_DAYS_AGO') . ')';

			if ($daysAgo > 14) {
				$add('COM_FGSYSTEMREPORT_SEC_BACKUP', 'danger', $backupValue,
					Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_STALE'));
			} elseif ($daysAgo > 7) {
				$add('COM_FGSYSTEMREPORT_SEC_BACKUP', 'warning', $backupValue,
					Text::_('COM_FGSYSTEMREPORT_SEC_BACKUP_STALE'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_BACKUP', 'ok', $backupValue, '');
			}
		}

		// 10) Multi-factor authentication - real usage, not just plugin
		// count. A plugin being enabled says nothing about whether anyone
		// actually configured MFA; a Super User with no row in
		// #__user_mfa is a concrete, actionable finding.
		$mfaEnabled = (int) $db->setQuery(
			"SELECT COUNT(*) FROM #__extensions WHERE type = 'plugin'"
			. " AND folder = 'multifactorauth' AND enabled = 1"
		)->loadResult();

		if (!$mfaEnabled) {
			$add('COM_FGSYSTEMREPORT_SEC_MFA', 'warning', '0', Text::_('COM_FGSYSTEMREPORT_SEC_MFA_NONE'));
		} else {
			$superUserIds = array_map(static function ($su) {
				return (int) $su->id;
			}, $superUsers);

			$superUsersWithoutMfa = 0;

			if (!empty($superUserIds)) {
				try {
					$idsWithMfa = $db->setQuery(
						'SELECT DISTINCT user_id FROM ' . $db->quoteName('#__user_mfa')
						. ' WHERE user_id IN (' . implode(',', $superUserIds) . ')'
					)->loadColumn() ?: [];

					$superUsersWithoutMfa = count(array_diff($superUserIds, $idsWithMfa));
				} catch (\Throwable $e) {
					$superUsersWithoutMfa = -1; // table missing or query failed - can't tell
				}
			}

			if ($superUsersWithoutMfa < 0) {
				$add('COM_FGSYSTEMREPORT_SEC_MFA', 'info', Text::_('JNONE'),
					Text::_('COM_FGSYSTEMREPORT_SEC_MFA_CHECK_FAILED'));
			} elseif ($superUsersWithoutMfa > 0) {
				$add('COM_FGSYSTEMREPORT_SEC_MFA', 'danger', (string) $superUsersWithoutMfa,
					Text::_('COM_FGSYSTEMREPORT_SEC_MFA_SUPERUSERS_MISSING'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_MFA', 'ok', (string) count($superUserIds), '');
			}
		}

		// 11) Database prefix
		$prefix = $db->getPrefix();
		$add('COM_FGSYSTEMREPORT_SEC_DB_PREFIX', $prefix === 'jos_' ? 'warning' : 'ok',
			$prefix,
			$prefix === 'jos_' ? Text::_('COM_FGSYSTEMREPORT_SEC_DB_PREFIX_DEFAULT') : '');

		// 12) Extensions with no update site at all, or one that's disabled
		// - these silently go stale since Joomla has no way to ever notify
		// about updates for them, and an abandoned/unmaintained extension
		// is a common entry point.
		// Only extensions that can have their own update site: not Joomla core
		// (ids below 10000) and not members of a package (the package carries
		// the update site for its children).
		$noUpdateSiteNames = [];
		$orphanNames       = [];
		$noUpdateSiteCount = 0;

		try {
			$extRows = (array) $db->setQuery(
				"SELECT e.extension_id, e.type, e.element, e.folder, e.client_id, e.package_id,"
				. " (SELECT COUNT(*) FROM #__update_sites_extensions use_"
				. "   INNER JOIN #__update_sites us ON us.update_site_id = use_.update_site_id"
				. "   WHERE use_.extension_id = e.extension_id AND us.enabled = 1) AS active_sites"
				. " FROM #__extensions e"
				. " WHERE e.type IN ('plugin', 'component', 'module', 'template', 'library', 'package')"
				. " AND e.locked = 0 AND e.extension_id >= 10000"
				. " ORDER BY e.element"
			)->loadObjectList();

			$base = static function (object $r): array {
				$client = (int) $r->client_id === 1 ? JPATH_ADMINISTRATOR : JPATH_ROOT;

				switch ($r->type) {
					case 'plugin':
						return [JPATH_ROOT . '/plugins/' . $r->folder . '/' . $r->element];
					case 'component':
						return [JPATH_ADMINISTRATOR . '/components/' . $r->element, JPATH_ROOT . '/components/' . $r->element];
					case 'module':
						return [$client . '/modules/' . $r->element];
					case 'template':
						return [$client . '/templates/' . $r->element];
					case 'library':
						return [JPATH_ROOT . '/libraries/' . $r->element];
					default:
						return [];
				}
			};

			foreach ($extRows as $r) {
				$label = $r->type === 'plugin' ? 'plg_' . $r->folder . '_' . $r->element : $r->element;
				$dirs  = $base($r);

				if ($dirs) {
					$exists = false;

					foreach ($dirs as $d) {
						if (is_dir($d)) {
							$exists = true;
							break;
						}
					}

					if (!$exists) {
						$orphanNames[] = $label;
						continue;
					}
				}

				if ((int) $r->active_sites === 0 && (int) $r->package_id === 0) {
					$noUpdateSiteCount++;

					if (count($noUpdateSiteNames) < 12) {
						$noUpdateSiteNames[] = $label;
					}
				}
			}
		} catch (\Throwable $e) {
			$noUpdateSiteCount = -1;
		}

		if ($noUpdateSiteCount < 0) {
			$add('COM_FGSYSTEMREPORT_SEC_NO_UPDATE_SITE', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_NO_UPDATE_SITE_CHECK_FAILED'));
		} elseif ($noUpdateSiteCount > 0) {
			$add('COM_FGSYSTEMREPORT_SEC_NO_UPDATE_SITE', 'warning', (string) $noUpdateSiteCount,
				Text::_('COM_FGSYSTEMREPORT_SEC_NO_UPDATE_SITE_FOUND') . ' ' . implode(', ', $noUpdateSiteNames)
				. ($noUpdateSiteCount > count($noUpdateSiteNames) ? ', ...' : ''));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_NO_UPDATE_SITE', 'ok', '0', '');
		}

		// 12b) Extension records whose files are gone (uninstalled by deleting
		// the folder, renamed, or a failed install): they clutter the Manage
		// list and can hide a half-removed extension.
		if ($noUpdateSiteCount >= 0) {
			if ($orphanNames) {
				$add('COM_FGSYSTEMREPORT_SEC_ORPHAN_EXT', 'warning', (string) count($orphanNames),
					Text::_('COM_FGSYSTEMREPORT_SEC_ORPHAN_EXT_FOUND') . ' ' . implode(', ', array_slice($orphanNames, 0, 12))
					. (count($orphanNames) > 12 ? ', ...' : ''));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_ORPHAN_EXT', 'ok', '0', '');
			}
		}

		// 13) Writable directory permissions and exposed directory listing
		// for the folders that most commonly get left wide open:
		// tmp, logs, images, cache.
		$dirIssues = [];
		$checkDirs = ['tmp', 'logs', 'images', 'cache'];

		foreach ($checkDirs as $dirName) {
			$fullPath = JPATH_ROOT . '/' . $dirName;

			if (!is_dir($fullPath)) {
				continue;
			}

			$perms = @fileperms($fullPath);

			if ($perms !== false && ($perms & 0002)) {
				// world-writable (the "other" write bit is set)
				$dirIssues[] = $dirName . ' (' . Text::_('COM_FGSYSTEMREPORT_SEC_DIR_WORLD_WRITABLE') . ')';
			}
		}

		$exposedDirs = self::checkExposedDirectoryListing(['tmp', 'logs']);

		foreach ($exposedDirs as $exposedDir) {
			$dirIssues[] = $exposedDir . ' (' . Text::_('COM_FGSYSTEMREPORT_SEC_DIR_LISTING_EXPOSED') . ')';
		}

		if (!empty($dirIssues)) {
			$add('COM_FGSYSTEMREPORT_SEC_DIR_PERMISSIONS', 'warning', (string) count($dirIssues),
				implode(', ', $dirIssues));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_DIR_PERMISSIONS', 'ok', Text::_('JNONE'), '');
		}

		// 14) PHP settings that matter for security and are free to check
		// (no network calls, just ini_get()).
		$phpIssues = [];

		if (filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN)) {
			$phpIssues[] = 'display_errors=On';
		}

		if (filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN)) {
			$phpIssues[] = 'expose_php=On';
		}

		$add('COM_FGSYSTEMREPORT_SEC_PHP_SETTINGS', empty($phpIssues) ? 'ok' : 'warning',
			empty($phpIssues) ? Text::_('JNONE') : implode(', ', $phpIssues),
			empty($phpIssues) ? '' : Text::_('COM_FGSYSTEMREPORT_SEC_PHP_SETTINGS_NOTE'));

		$add('COM_FGSYSTEMREPORT_SEC_PHP_LIMITS', 'info',
			sprintf(
				'memory_limit=%s, max_execution_time=%ss, upload_max_filesize=%s, open_basedir=%s',
				ini_get('memory_limit') ?: '?',
				ini_get('max_execution_time') ?: '?',
				ini_get('upload_max_filesize') ?: '?',
				ini_get('open_basedir') !== '' ? Text::_('JYES') : Text::_('JNO')
			), '');

		// 15) configuration.php: secret entropy/length, session handler,
		// mailer type, offline status, SEF without .htaccess
		$configIssues = [];
		$secret       = (string) $app->get('secret', '');

		if (strlen($secret) < 16) {
			$configIssues[] = Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_SECRET_SHORT');
		} elseif (preg_match('/^(.)\1*$/', $secret) || count(array_unique(str_split($secret))) < 8) {
			// all-same-character or very low character variety - a weak
			// secret even if it happens to be long
			$configIssues[] = Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_SECRET_WEAK');
		}

		if ((int) $app->get('offline', 0) === 1) {
			$configIssues[] = Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_OFFLINE');
		}

		$mailer = (string) $app->get('mailer', 'mail');

		if ($mailer === 'mail') {
			$configIssues[] = Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_MAILER_PHPMAIL');
		}

		if ((int) $app->get('sef_rewrite', 0) === 1 && !is_file(JPATH_ROOT . '/.htaccess')) {
			$configIssues[] = Text::_('COM_FGSYSTEMREPORT_SEC_CONFIG_SEF_NO_HTACCESS');
		}

		if (!empty($configIssues)) {
			$add('COM_FGSYSTEMREPORT_SEC_CONFIG', 'warning', (string) count($configIssues),
				implode('; ', $configIssues));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_CONFIG', 'ok', Text::_('JNONE'), '');
		}

		// 16) Known vulnerable extensions (Joomla VEL) - best-effort name
		// match against the official JSON feed. The feed's own extension
		// identification is inconsistent free text (not a canonical
		// element name), so a hit here is reported as a possible match to
		// verify manually, never as a confirmed vulnerability.
		try {
			$velMatches  = [];
			$velResponse = @file_get_contents('https://extensions.joomla.org/index.php?option=com_vel&format=json');

			if ($velResponse !== false) {
				$velData = json_decode($velResponse, true);
				$velLive = [];

				if (!empty($velData['success']) && !empty($velData['data']['items'])) {
					foreach ($velData['data']['items'] as $item) {
						if (($item['status'] ?? '') === '1') {
							$velLive[] = $item;
						}
					}
				}

				$installedNames = [];

				foreach (($precomputedExtensions ?? []) as $ext) {
					$name = trim((string) ($ext->name ?? ''));

					// skip generic/short names - too likely to produce
					// noise matches against free-text VEL titles
					if (strlen($name) >= 6 && !preg_match('/^(mod_|plg_system_|com_)?(content|users|menus|search)$/i', $name)) {
						$installedNames[$name] = true;
					}
				}

				foreach ($velLive as $velItem) {
					$title = strtolower((string) ($velItem['title'] ?? ''));

					foreach (array_keys($installedNames) as $installedName) {
						if (strlen($installedName) >= 6 && stripos($title, strtolower($installedName)) !== false) {
							$velMatches[] = $installedName;
						}
					}
				}

				$velMatches = array_values(array_unique($velMatches));
			}

			if (!isset($velResponse) || $velResponse === false) {
				$add('COM_FGSYSTEMREPORT_SEC_VEL', 'info', Text::_('JNONE'),
					Text::_('COM_FGSYSTEMREPORT_SEC_VEL_CHECK_FAILED'));
			} elseif (!empty($velMatches)) {
				$add('COM_FGSYSTEMREPORT_SEC_VEL', 'warning', (string) count($velMatches),
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_VEL_POSSIBLE_MATCH', implode(', ', $velMatches)));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_VEL', 'ok', Text::_('JNONE'), '');
			}
		} catch (\Throwable $e) {
			$add('COM_FGSYSTEMREPORT_SEC_VEL', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_VEL_CHECK_FAILED'));
		}

		// 17) Bloated database tables - common on neglected sites and a
		// good argument for a maintenance service.
		try {
			$bloatTables = ['#__action_logs', '#__session', '#__finder_links', '#__finder_links_terms'];
			$bloatFindings = [];

			foreach ($bloatTables as $bloatTable) {
				$realTable = $db->replacePrefix($bloatTable);
				$countResult = $db->setQuery(
					'SELECT COUNT(*) FROM ' . $db->quoteName($bloatTable)
				)->loadResult();

				$rowCount = (int) $countResult;

				if ($rowCount > 50000) {
					$bloatFindings[] = $realTable . ' (' . number_format($rowCount) . ' ' . Text::_('COM_FGSYSTEMREPORT_SEC_DB_ROWS') . ')';
				}
			}
		} catch (\Throwable $e) {
			$bloatFindings = null;
		}

		if ($bloatFindings === null) {
			$add('COM_FGSYSTEMREPORT_SEC_DB_BLOAT', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_DB_BLOAT_CHECK_FAILED'));
		} elseif (!empty($bloatFindings)) {
			$add('COM_FGSYSTEMREPORT_SEC_DB_BLOAT', 'warning', (string) count($bloatFindings),
				implode(', ', $bloatFindings));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_DB_BLOAT', 'ok', Text::_('JNONE'), '');
		}

		// 18) "Behaviour - Backward Compatibility" plugin status - relevant
		// when preparing for Joomla 6/7, since some old API only still
		// works because this plugin is bridging it.
		$backCompatEnabled = (int) $db->setQuery(
			"SELECT enabled FROM #__extensions WHERE type = 'plugin'"
			. " AND folder = 'behaviour' AND element = 'backcompat'"
		)->loadResult();

		$add('COM_FGSYSTEMREPORT_SEC_BACKCOMPAT', $backCompatEnabled ? 'warning' : 'ok',
			$backCompatEnabled ? Text::_('JYES') : Text::_('JNO'),
			$backCompatEnabled ? Text::_('COM_FGSYSTEMREPORT_SEC_BACKCOMPAT_ENABLED') : '');

		// 19) PHP/DB clock skew - a desynced server and database clock is
		// a subtle, hard-to-diagnose source of bugs (premature or late
		// session expiry, mistimed scheduled tasks, cache invalidation).
		// time() and MySQL's UTC_TIMESTAMP() are both UTC-based, so this
		// comparison is fair regardless of either side's configured
		// timezone.
		try {
			$dbUtcTimestamp = (int) $db->setQuery('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP()) AS ts')->loadResult();
			$skewSeconds    = abs(time() - $dbUtcTimestamp);
		} catch (\Throwable $e) {
			$skewSeconds = null;
		}

		$phpTz    = date_default_timezone_get();
		$dbTzRow  = $db->setQuery("SHOW VARIABLES LIKE 'time_zone'")->loadObject();
		$dbTz     = $dbTzRow->Value ?? '?';
		$tzNote   = Text::sprintf('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW_TZ', $phpTz, $dbTz);

		// A skew that lands within a few seconds of a whole hour (3600,
		// 7200, ...), combined with the DB's time_zone being literally
		// "SYSTEM", is much more likely to be MySQL/MariaDB
		// misinterpreting UTC_TIMESTAMP() when its timezone tables were
		// never loaded (mysql_tzinfo_to_sql was never run - common on
		// shared hosting) than an actual desynced clock. Downgrade that
		// specific pattern to a warning with an explanation instead of a
		// flat "Critical", rather than needing a human to notice the
		// suspiciously round number themselves. Distance is measured to
		// the *nearest* multiple of an hour (both just-above and
		// just-below count, e.g. 3595s is as suspicious as 3605s), and
		// requires skew > 60s so a genuinely fine near-zero skew is never
		// affected by this at all.
		$remainder           = $skewSeconds !== null ? $skewSeconds % 3600 : null;
		$distanceToWholeHour = $remainder !== null ? min($remainder, 3600 - $remainder) : null;
		$looksLikeTzMisread  = $skewSeconds !== null
			&& $skewSeconds > 60
			&& $dbTz === 'SYSTEM'
			&& $distanceToWholeHour <= 5;

		if ($skewSeconds === null) {
			$add('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW_CHECK_FAILED'));
		} elseif ($looksLikeTzMisread) {
			$add('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW', 'warning', $skewSeconds . 's',
				$tzNote . ' ' . Text::_('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW_LIKELY_TZ_MISREAD'));
		} elseif ($skewSeconds > 300) {
			$add('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW', 'danger', $skewSeconds . 's', $tzNote);
		} elseif ($skewSeconds > 60) {
			$add('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW', 'warning', $skewSeconds . 's', $tzNote);
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_CLOCK_SKEW', 'ok', $skewSeconds . 's', $tzNote);
		}

		// 20) flock() reliability - unreliable file locking (common on
		// certain NFS-mounted or clustered hosting setups) can silently
		// break Joomla's own file-based cache/session locking without any
		// obvious error.
		try {
			$lockTestFile = rtrim($app->get('tmp_path'), '/') . '/jc_flock_test_' . uniqid() . '.tmp';
			$flockOk      = false;
			$handle       = @fopen($lockTestFile, 'c');

			if ($handle !== false) {
				if (@flock($handle, LOCK_EX)) {
					$flockOk = true;
					@flock($handle, LOCK_UN);
				}

				fclose($handle);
				@unlink($lockTestFile);
			}
		} catch (\Throwable $e) {
			$flockOk = null;
		}

		if ($flockOk === null) {
			$add('COM_FGSYSTEMREPORT_SEC_FLOCK', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_FLOCK_CHECK_FAILED'));
		} elseif (!$flockOk) {
			$add('COM_FGSYSTEMREPORT_SEC_FLOCK', 'warning', Text::_('JNO'),
				Text::_('COM_FGSYSTEMREPORT_SEC_FLOCK_UNRELIABLE'));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_FLOCK', 'ok', Text::_('JYES'), '');
		}

		// 21) External (public) IP address - informational, handy when
		// telling a client "whitelist this IP" for something (SMTP relay,
		// firewall, third-party API access). A single lightweight request
		// to a well-known IP-echo service, same defensive pattern
		// (short timeout, graceful failure) as the other network checks.
		$externalIp = null;

		if (function_exists('curl_init')) {
			try {
				$ch = curl_init('https://api.ipify.org');
				curl_setopt_array($ch, [
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 3,
					CURLOPT_CONNECTTIMEOUT => 2,
					CURLOPT_SSL_VERIFYPEER => true,
				]);
				$result   = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($httpCode === 200 && $result !== false && filter_var(trim((string) $result), FILTER_VALIDATE_IP)) {
					$externalIp = trim($result);
				}
			} catch (\Throwable $e) {
				$externalIp = null;
			}
		}

		if ($externalIp !== null) {
			$add('COM_FGSYSTEMREPORT_SEC_EXTERNAL_IP', 'info', $externalIp, '');
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_EXTERNAL_IP', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_EXTERNAL_IP_CHECK_FAILED'));
		}

		// 22) MySQL sql_mode - specifically whether strict mode is active.
		// Without STRICT_TRANS_TABLES/STRICT_ALL_TABLES, MySQL silently
		// truncates or coerces invalid data instead of raising an error -
		// a subtle, hard-to-trace source of data integrity bugs.
		try {
			$sqlModeRow = $db->setQuery('SELECT @@SESSION.sql_mode AS mode')->loadResult();
			$sqlMode    = (string) $sqlModeRow;
			$hasStrict  = stripos($sqlMode, 'STRICT_TRANS_TABLES') !== false
				|| stripos($sqlMode, 'STRICT_ALL_TABLES') !== false;
		} catch (\Throwable $e) {
			$sqlMode   = null;
			$hasStrict = null;
		}

		if ($sqlMode === null) {
			$add('COM_FGSYSTEMREPORT_SEC_SQL_MODE', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_SQL_MODE_CHECK_FAILED'));
		} elseif (!$hasStrict) {
			$add('COM_FGSYSTEMREPORT_SEC_SQL_MODE', 'warning', $sqlMode !== '' ? $sqlMode : Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_SQL_MODE_NOT_STRICT'));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_SQL_MODE', 'ok', $sqlMode, '');
		}

		// 23) "System - Cache" plugin status. Full-page caching of this
		// kind has a real history of cross-user data leakage when used
		// carelessly on pages with per-user content (cached output served
		// to the wrong visitor) - worth flagging as something to verify
		// is configured correctly, not a guaranteed problem on its own.
		$sysCacheEnabled = (int) $db->setQuery(
			"SELECT COUNT(*) FROM #__extensions WHERE type = 'plugin'"
			. " AND folder = 'system' AND element = 'cache' AND enabled = 1"
		)->loadResult();

		$add('COM_FGSYSTEMREPORT_SEC_SYSTEM_CACHE', $sysCacheEnabled ? 'warning' : 'ok',
			$sysCacheEnabled ? Text::_('JYES') : Text::_('JNO'),
			$sysCacheEnabled ? Text::_('COM_FGSYSTEMREPORT_SEC_SYSTEM_CACHE_ENABLED') : '');

		// 24) Folder permissions: world-writable folders are dangerous;
		// folders Joomla must write to (cache, tmp, logs, images) being
		// missing or read-only breaks features.
		$perm     = SiteHealthHelper::folderPermissions();
		$world    = [];
		$problems = [];

		foreach ($perm as $r) {
			$txt = $r['name'] . ($r['perm'] !== '' ? ' (' . $r['perm'] . ')' : '');

			if ($r['problem'] === 'world') {
				$world[] = $txt;
			} elseif ($r['problem'] !== '') {
				$problems[] = $txt;
			}
		}

		$permValue = (count($perm) - count($world) - count($problems)) . '/' . count($perm);

		if ($world) {
			$add('COM_FGSYSTEMREPORT_SEC_FOLDER_PERMS', 'danger', $permValue,
				Text::sprintf('COM_FGSYSTEMREPORT_SEC_FOLDER_PERMS_WORLD', implode(', ', $world)));
		} elseif ($problems) {
			$add('COM_FGSYSTEMREPORT_SEC_FOLDER_PERMS', 'warning', $permValue,
				Text::sprintf('COM_FGSYSTEMREPORT_SEC_FOLDER_PERMS_PROBLEM', implode(', ', $problems)));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_FOLDER_PERMS', 'ok', $permValue, '');
		}

		// 25) Orphaned rows in the assets table (parent no longer exists).
		$orphans = SiteHealthHelper::orphanAssets();

		if ($orphans === null) {
			$add('COM_FGSYSTEMREPORT_SEC_ORPHAN_ASSETS', 'info', Text::_('JNONE'),
				Text::_('COM_FGSYSTEMREPORT_SEC_ORPHAN_ASSETS_CHECK_FAILED'));
		} elseif ($orphans > 0) {
			$add('COM_FGSYSTEMREPORT_SEC_ORPHAN_ASSETS', 'warning', (string) $orphans,
				Text::sprintf('COM_FGSYSTEMREPORT_SEC_ORPHAN_ASSETS_FOUND', $orphans));
		} else {
			$add('COM_FGSYSTEMREPORT_SEC_ORPHAN_ASSETS', 'ok', '0', '');
		}

		// 26) Mail DNS: SPF / DMARC / MX of the site's domain.
		$mail = ExtraChecksHelper::mailDns();

		if ($mail !== null) {
			$yn    = static fn (bool $b): string => Text::_($b ? 'JYES' : 'JNO');
			$value = Text::sprintf('COM_FGSYSTEMREPORT_SEC_MAIL_DNS_VALUE', $yn($mail['spf']), $yn($mail['dmarc']) . ($mail['policy'] !== '' ? ' (p=' . $mail['policy'] . ')' : ''), $yn($mail['mx']));
			$miss  = array_keys(array_filter(['SPF' => !$mail['spf'], 'DMARC' => !$mail['dmarc']]));

			if ($miss) {
				$add('COM_FGSYSTEMREPORT_SEC_MAIL_DNS', 'warning', $value,
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_MAIL_DNS_MISSING', implode(' + ', $miss), $mail['host']));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_MAIL_DNS', 'ok', $value, '');
			}
		}

		// 27) Scheduled tasks that stopped running.
		$overdue = ExtraChecksHelper::overdueTasks();

		if ($overdue !== null) {
			if ($overdue) {
				$add('COM_FGSYSTEMREPORT_SEC_SCHED', 'warning', (string) count($overdue),
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_SCHED_OVERDUE', count($overdue), implode(', ', $overdue)));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_SCHED', 'ok', Text::_('COM_FGSYSTEMREPORT_SEC_SCHED_ON_TIME'), '');
			}
		}

		// 28) OPcache
		$opc = ExtraChecksHelper::opcache();

		if ($opc === 'off') {
			$add('COM_FGSYSTEMREPORT_SEC_OPCACHE', 'warning', Text::_('JNO'), Text::_('COM_FGSYSTEMREPORT_SEC_OPCACHE_OFF'));
		} elseif ($opc === 'full') {
			$add('COM_FGSYSTEMREPORT_SEC_OPCACHE', 'warning', Text::_('JYES'), Text::_('COM_FGSYSTEMREPORT_SEC_OPCACHE_FULL'));
		} elseif ($opc === 'ok') {
			$add('COM_FGSYSTEMREPORT_SEC_OPCACHE', 'ok', Text::_('JYES'), '');
		}

		// 29) HSTS (informational: never lowers the score)
		$hsts = ExtraChecksHelper::hsts();

		if ($hsts !== null) {
			if ($hsts === '') {
				$add('COM_FGSYSTEMREPORT_SEC_HSTS', 'info', Text::_('JNO'), Text::_('COM_FGSYSTEMREPORT_SEC_HSTS_MISSING'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_HSTS', 'ok', $hsts, '');
			}
		}

		// 30) PHP files under /images
		$php = ExtraChecksHelper::phpInImages();

		if ($php !== null) {
			if ($php['count'] > 0) {
				$add('COM_FGSYSTEMREPORT_SEC_PHP_IN_IMAGES', 'danger', (string) $php['count'],
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_PHP_IN_IMAGES_FOUND', implode(', ', $php['files'])));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_PHP_IN_IMAGES', 'ok', '0',
					$php['partial'] ? Text::_('COM_FGSYSTEMREPORT_SEC_PHP_IN_IMAGES_PARTIAL') : '');
			}
		}

		// 31) Leftover files in the web root
		$left = ExtraChecksHelper::rootLeftovers();

		if ($left !== null) {
			$notes = [];

			if ($left['install']) {
				$notes[] = Text::_('COM_FGSYSTEMREPORT_SEC_LEFTOVERS_INSTALL');
			}

			if ($left['config']) {
				$notes[] = Text::sprintf('COM_FGSYSTEMREPORT_SEC_LEFTOVERS_CONFIG', implode(', ', $left['config']));
			}

			if ($left['backups']) {
				$notes[] = Text::sprintf('COM_FGSYSTEMREPORT_SEC_LEFTOVERS_BACKUPS', implode(', ', $left['backups']));
			}

			if ($left['tools']) {
				$notes[] = Text::sprintf('COM_FGSYSTEMREPORT_SEC_LEFTOVERS_TOOLS', implode(', ', $left['tools']));
			}

			$cnt = (int) $left['install'] + count($left['config']) + count($left['backups']) + count($left['tools']);

			if ($left['install'] || $left['config']) {
				$add('COM_FGSYSTEMREPORT_SEC_LEFTOVERS', 'danger', (string) $cnt, implode(' ', $notes));
			} elseif ($notes) {
				$add('COM_FGSYSTEMREPORT_SEC_LEFTOVERS', 'warning', (string) $cnt, implode(' ', $notes));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_LEFTOVERS', 'ok', '0', '');
			}
		}

		// 32) PHP fatal errors in the last 24 hours (from the error logs)
		$fatals = ErrorLogHelper::recentFatals();

		if ($fatals !== null) {
			if ($fatals['count'] > 0) {
				$add('COM_FGSYSTEMREPORT_SEC_FATALS', 'warning', (string) $fatals['count'],
					Text::sprintf('COM_FGSYSTEMREPORT_SEC_FATALS_FOUND', $fatals['count'], $fatals['top'] !== '' ? $fatals['top'] : '?'));
			} else {
				$add('COM_FGSYSTEMREPORT_SEC_FATALS', 'ok', '0', '');
			}
		}

		return $checks;
	}


	/**
	 * Live-check the latest available Joomla version from the official
	 * download servers (not from the local #__updates cache).
	 *
	 * Uses the JSON API for Joomla 5.4.1+ (downloads.joomla.org) and falls
	 * back to the legacy update XML feed for older versions, same servers
	 * Joomla itself uses to check for core updates.
	 *
	 * @return  string|null  Latest version string, or null if the check failed
	 */
	/**
	 * Check a short list of commonly-forgotten sensitive paths (.env,
	 * .git internals, backup/config leftovers, phpinfo scripts...) for
	 * public HTTP exposure on the site's front end. Requests run in
	 * parallel via curl_multi with a short timeout, so the whole check
	 * costs roughly one request's worth of time, not N sequential ones.
	 *
	 * Two safeguards against false positives on hosts with "soft 404"
	 * behaviour (SEF router / custom 404 page / some security plugins or
	 * CDNs return HTTP 200 for literally any URL, including ones that
	 * don't exist):
	 *   1. A canary request to a random, guaranteed-nonexistent path is
	 *      sent alongside the real ones. If it also comes back 200, the
	 *      whole check is unreliable on this host and is reported as
	 *      indeterminate rather than flagging every path as exposed.
	 *   2. Real paths use GET with a `Range: bytes=0-256` header (HEAD is
	 *      blocked or mishandled - e.g. returns 200 with no body - on
	 *      some servers for dotfiles) and the returned content is checked
	 *      against a signature expected for that specific file, so a
	 *      generic 200 response (e.g. a custom error/maintenance page)
	 *      isn't mistaken for the real file.
	 *
	 * @return  object  ->available (bool, whether the check could run at
	 *                  all), ->reliable (bool, whether the canary probe
	 *                  confirms normal 404 behaviour), ->exposed (array
	 *                  of confirmed-exposed URLs, only meaningful when
	 *                  both of the above are true).
	 */
	public static function checkExposedSensitiveFiles(): object
	{
		$result = (object) [
			'available' => false,
			'reliable'  => false,
			'exposed'   => [],
		];

		if (!function_exists('curl_multi_init')) {
			return $result;
		}

		$root = rtrim(Uri::root(), '/');

		if ($root === '' || !preg_match('#^https?://#i', $root)) {
			return $result;
		}

		// path => regex the response body must match to be considered a
		// genuine hit, not just any 200 response
		$paths = [
			'.env'                   => '/^\s*[A-Za-z_][A-Za-z0-9_]*\s*=/m',
			'.git/config'             => '/\[core\]/',
			'.git/HEAD'               => '/^ref:|^[0-9a-f]{40}/',
			'configuration.php.bak'   => '/<\?php|class\s+Configuration/i',
			'configuration.php~'      => '/<\?php|class\s+Configuration/i',
			'configuration.php.old'   => '/<\?php|class\s+Configuration/i',
			'configuration.php.save'  => '/<\?php|class\s+Configuration/i',
			'phpinfo.php'             => '/phpinfo\(\)|PHP Version|<title>phpinfo/i',
			'error_log'               => '/PHP (Warning|Notice|Fatal error|Deprecated|Parse error)/i',
		];

		$canaryPath = 'jc-probe-' . bin2hex(random_bytes(8));

		$targets = array_merge([$canaryPath => null], $paths);

		$multiHandle = curl_multi_init();
		$handles     = [];

		foreach ($targets as $path => $signature) {
			$ch = curl_init($root . '/' . $path);
			curl_setopt_array($ch, [
				CURLOPT_HTTPGET        => true,
				CURLOPT_HTTPHEADER     => ['Range: bytes=0-256'],
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 4,
				CURLOPT_CONNECTTIMEOUT => 3,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_SSL_VERIFYPEER => false, // report exposure even behind a self-signed/staging cert
				CURLOPT_USERAGENT      => 'JC-System-Report-Security-Check',
			]);
			curl_multi_add_handle($multiHandle, $ch);
			$handles[$path] = $ch;
		}

		$running = null;
		do {
			curl_multi_exec($multiHandle, $running);
			curl_multi_select($multiHandle);
		} while ($running > 0);

		$responses = [];

		foreach ($handles as $path => $ch) {
			$responses[$path] = (object) [
				'httpCode' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
				'body'     => curl_multi_getcontent($ch),
			];
			curl_multi_remove_handle($multiHandle, $ch);
			curl_close($ch);
		}

		curl_multi_close($multiHandle);

		$result->available = true;

		$canaryResponse = $responses[$canaryPath];

		// A well-behaved server returns 404 (or similar) for a random,
		// non-existent path. If it returns success instead, every "200"
		// this check would otherwise report is meaningless.
		if (in_array($canaryResponse->httpCode, [200, 206], true)) {
			return $result;
		}

		$result->reliable = true;

		foreach ($paths as $path => $signature) {
			$response = $responses[$path];

			if (!in_array($response->httpCode, [200, 206], true)) {
				continue;
			}

			if (preg_match($signature, $response->body)) {
				$result->exposed[] = $root . '/' . $path;
			}
		}

		return $result;
	}

	/**
	 * Check whether any of the given site-root-relative directories return
	 * a browsable directory listing over HTTP (e.g. Apache's "Index of /"
	 * with no index.html/.htaccess protecting it). Uses the same canary
	 * approach as checkExposedSensitiveFiles() to avoid false positives on
	 * "soft 404" hosts.
	 *
	 * @param   array  $dirs  Site-root-relative directory names, e.g. ['tmp', 'logs']
	 *
	 * @return  array  Names of directories with an exposed listing
	 */
	public static function checkExposedDirectoryListing(array $dirs): array
	{
		if (!function_exists('curl_multi_init')) {
			return [];
		}

		$root = rtrim(Uri::root(), '/');

		if ($root === '' || !preg_match('#^https?://#i', $root)) {
			return [];
		}

		$canaryPath = 'jc-probe-dir-' . bin2hex(random_bytes(8)) . '/';
		$targets    = array_merge([$canaryPath], array_map(static function ($d) {
			return rtrim($d, '/') . '/';
		}, $dirs));

		$multiHandle = curl_multi_init();
		$handles     = [];

		foreach ($targets as $path) {
			$ch = curl_init($root . '/' . $path);
			curl_setopt_array($ch, [
				CURLOPT_HTTPGET        => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 4,
				CURLOPT_CONNECTTIMEOUT => 3,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_USERAGENT      => 'JC-System-Report-Security-Check',
			]);
			curl_multi_add_handle($multiHandle, $ch);
			$handles[$path] = $ch;
		}

		$running = null;
		do {
			curl_multi_exec($multiHandle, $running);
			curl_multi_select($multiHandle);
		} while ($running > 0);

		$responses = [];

		foreach ($handles as $path => $ch) {
			$responses[$path] = (object) [
				'httpCode' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
				'body'     => curl_multi_getcontent($ch),
			];
			curl_multi_remove_handle($multiHandle, $ch);
			curl_close($ch);
		}

		curl_multi_close($multiHandle);

		$signature = '/<title>Index of |Parent Directory<\/a>|\[To Parent Directory\]/i';

		// Canary: a random nonexistent directory should NOT look like a
		// listing. If it does, this host's behaviour makes the check
		// unreliable, so skip it rather than risk a false positive.
		if (preg_match($signature, $responses[$canaryPath]->body ?? '')) {
			return [];
		}

		$exposed = [];

		foreach ($dirs as $dir) {
			$path = rtrim($dir, '/') . '/';
			$response = $responses[$path] ?? null;

			if ($response && in_array($response->httpCode, [200, 403], true)
				&& preg_match($signature, $response->body ?? '')) {
				$exposed[] = $dir;
			}
		}

		return $exposed;
	}

	/**
	 * Check the SSL certificate on the site's own domain (port 443) and
	 * return how many days remain until it expires.
	 *
	 * @return  object|null  Object with ->daysLeft (int), ->validTo (string
	 *                       Y-m-d), ->issuer (string); or null if the check
	 *                       could not connect (no HTTPS listener, DNS
	 *                       failure, etc.) or the host couldn't be
	 *                       determined.
	 */
	public static function checkSslCertificate(): ?object
	{
		$host = parse_url(Uri::root(), PHP_URL_HOST);

		if (empty($host)) {
			return null;
		}

		$context = stream_context_create([
			'ssl' => [
				'capture_peer_cert' => true,
				'verify_peer'       => false,
				'verify_peer_name'  => false,
			],
		]);

		$client = @stream_socket_client(
			'ssl://' . $host . ':443',
			$errno,
			$errstr,
			5,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if (!$client) {
			return null;
		}

		$params = stream_context_get_params($client);
		fclose($client);

		if (empty($params['options']['ssl']['peer_certificate'])) {
			return null;
		}

		$cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

		if (empty($cert['validTo_time_t'])) {
			return null;
		}

		$result           = new \stdClass();
		$result->daysLeft = (int) floor(($cert['validTo_time_t'] - time()) / 86400);
		$result->validTo  = date('Y-m-d', $cert['validTo_time_t']);
		$result->issuer   = $cert['issuer']['CN'] ?? $cert['issuer']['O'] ?? '';

		return $result;
	}

	/**
	 * Look up the most recent successful backup made by Akeeba Backup
	 * (by far the most common Joomla backup extension), if it's installed.
	 *
	 * @return  object|null  Object with ->installed (bool) and
	 *                       ->lastBackupTimestamp (int|null, null means
	 *                       Akeeba is installed but no successful backup
	 *                       was ever recorded); or null if Akeeba Backup
	 *                       isn't installed at all.
	 */
	public static function checkBackupStatus(): ?object
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		// Akeeba Backup 9+ (current, Joomla 4/5/6-compatible) was renamed
		// from com_akeeba to com_akeebabackup, and its history table from
		// #__ak_stats to #__akeebabackup_backups - the column layout
		// (backupend, status) carried over though. Detect either
		// generation and query whichever one is actually present.
		$candidates = [
			'com_akeebabackup' => '#__akeebabackup_backups',
			'com_akeeba'       => '#__ak_stats',
		];

		$element = null;
		$table   = null;

		foreach ($candidates as $candidateElement => $candidateTable) {
			// Don't require enabled = 1: a component can be registered but
			// show enabled = 0 in some discover-install / manual-reinstall
			// edge cases while still working fine and having a readable
			// backup history - we only need it installed, not "active".
			$installedInDb = (int) $db->setQuery(
				"SELECT COUNT(*) FROM #__extensions WHERE type = 'component' AND element = " . $db->quote($candidateElement)
			)->loadResult();

			// Fall back to a filesystem check in case the extensions table
			// is out of sync with what's actually on disk.
			$installedOnDisk = is_dir(JPATH_ADMINISTRATOR . '/components/' . $candidateElement);

			if ($installedInDb || $installedOnDisk) {
				$element = $candidateElement;
				$table   = $candidateTable;
				break;
			}
		}

		if ($element === null) {
			return null;
		}

		$result            = new \stdClass();
		$result->installed = true;

		try {
			// Primary: explicitly successful backups (case-insensitive, in
			// case of schema variants across Akeeba versions).
			$lastBackupEnd = $db->setQuery(
				"SELECT MAX(backupend) FROM " . $table . " WHERE LOWER(status) = " . $db->quote('complete')
			)->loadResult();

			// Fallback: if nothing matched (e.g. a different status label
			// than expected), still show the most recent finished backup
			// run of any status rather than reporting "never".
			if (empty($lastBackupEnd)) {
				$lastBackupEnd = $db->setQuery(
					"SELECT MAX(backupend) FROM " . $table
				)->loadResult();
			}
		} catch (\Throwable $e) {
			// Table missing or schema differs from what we expect - report as "unknown", not a crash
			$lastBackupEnd = null;
		}

		// backupend may be stored as a Unix timestamp (legacy #__ak_stats)
		// or as a DATETIME string (seen on current #__akeebabackup_backups
		// installs) - handle both, and treat MySQL zero-dates as "none".
		$result->lastBackupTimestamp = null;

		if (!empty($lastBackupEnd)) {
			if (is_numeric($lastBackupEnd)) {
				$timestamp = (int) $lastBackupEnd;
			} else {
				$parsed    = strtotime((string) $lastBackupEnd);
				$timestamp = $parsed !== false ? $parsed : 0;
			}

			if ($timestamp > 0) {
				$result->lastBackupTimestamp = $timestamp;
			}
		}

		return $result;
	}

	/**
	 * Get this component's own installed version number, the same way
	 * getInstalledExtensions() derives it for every other extension (from
	 * #__extensions.manifest_cache) - so it always matches what the
	 * Installed Extensions page shows, without needing to touch this file
	 * every time the version is bumped.
	 *
	 * @return  string  e.g. "1.0.73", or "" if it couldn't be determined
	 */
	public static function getOwnVersion(): string
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$manifestCache = $db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('manifest_cache'))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('component'))
				->where($db->quoteName('element') . ' = ' . $db->quote('com_fgsystemreport'))
		)->loadResult();

		if (empty($manifestCache)) {
			return '';
		}

		$decoded = json_decode($manifestCache, true);

		return $decoded['version'] ?? '';
	}

	public static function fetchLatestJoomlaVersion(): ?string
	{
		if (!function_exists('curl_init')) {
			return null;
		}

		if (version_compare(JVERSION, '5.4.1', '>=')) {
			$ch = curl_init('https://downloads.joomla.org/api/v1/latest/cms');
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 5,
				CURLOPT_USERAGENT      => 'JC-System-Report-Version-Check',
				CURLOPT_SSL_VERIFYPEER => true,
			]);
			$response = curl_exec($ch);
			curl_close($ch);

			if ($response === false) {
				return null;
			}

			$data = json_decode($response, true);

			if (empty($data['branches']) || !is_array($data['branches'])) {
				return null;
			}

			$latest = end($data['branches']);

			return !empty($latest['version']) ? (string) $latest['version'] : null;
		}

		$ch = curl_init('https://update.joomla.org/core/list.xml');
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_USERAGENT      => 'JC-System-Report-Version-Check',
			CURLOPT_SSL_VERIFYPEER => true,
		]);
		$xmlContent = curl_exec($ch);
		curl_close($ch);

		if ($xmlContent === false) {
			return null;
		}

		$xml = @simplexml_load_string($xmlContent);

		if (!$xml || !isset($xml->extension)) {
			return null;
		}

		$latestVersion = '';

		foreach ($xml->extension as $extension) {
			if ((string) $extension['name'] !== 'Joomla') {
				continue;
			}

			$version = (string) $extension['version'];

			if ($version && version_compare($version, $latestVersion, '>')) {
				$latestVersion = $version;
			}
		}

		return $latestVersion !== '' ? $latestVersion : null;
	}

	/**
	 * Turn an untranslated raw name/element (e.g. "plg_content_sigplus" or
	 * "COM_SOME_EXTENSION_XML_DESCRIPTION") into a readable label when no
	 * language file was available to translate it properly.
	 *
	 * @param   string  $raw  Raw name or element
	 *
	 * @return  string
	 */
	private static function prettifyExtensionName(string $raw): string
	{
		$name = $raw;

		// Strip a single known extension-type prefix (com_/mod_/plg_/tpl_/pkg_/lib_)
		if (preg_match('/^(com|mod|plg|tpl|pkg|lib)_(.+)$/i', $name, $m)) {
			$name = $m[2];
		}

		// Replace underscores/dashes with spaces and collapse repeats
		$name = trim(preg_replace('/[_\-]+/', ' ', $name));

		// Title-case each word
		$name = ucwords(strtolower($name));

		return $name !== '' ? $name : $raw;
	}

	public static function getInstalledExtensions(bool $includeCore = false, bool $hideOfficial = false): array
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('name') . " IS NOT NULL AND " . $db->quoteName('name') . " != ''");

		if (!$includeCore) {
			$query->where($db->quoteName('locked') . ' = 0');
		}

		$query->order($db->quoteName('name') . ' ASC');
		$db->setQuery($query);
		$extensions = $db->loadObjectList() ?: [];

		// Available updates
		$updateRows = $db->setQuery('SELECT extension_id, version, description FROM #__updates')
			->loadObjectList('extension_id') ?: [];

		$lang = Factory::getApplication()->getLanguage();

		foreach ($extensions as $ext) {
			$manifest = json_decode($ext->manifest_cache, true);

			// Load the extension's .sys.ini so its name constant can be translated
			// (same approach as core com_installer Manage view)
			switch ($ext->type) {
				case 'component':
					$source = JPATH_ADMINISTRATOR . '/components/' . $ext->element;
					$lang->load($ext->element . '.sys', JPATH_ADMINISTRATOR)
						|| $lang->load($ext->element . '.sys', $source);
					break;

				case 'module':
					$base = $ext->client_id ? JPATH_ADMINISTRATOR : JPATH_SITE;
					$source = $base . '/modules/' . $ext->element;
					$lang->load($ext->element . '.sys', $base)
						|| $lang->load($ext->element . '.sys', $source);
					break;

				case 'plugin':
					$extName = 'plg_' . $ext->folder . '_' . $ext->element;
					$source = JPATH_PLUGINS . '/' . $ext->folder . '/' . $ext->element;
					$lang->load($extName . '.sys', JPATH_ADMINISTRATOR)
						|| $lang->load($extName . '.sys', $source);
					break;

				case 'template':
					$base = $ext->client_id ? JPATH_ADMINISTRATOR : JPATH_SITE;
					$source = $base . '/templates/' . $ext->element;
					$lang->load('tpl_' . $ext->element . '.sys', $base)
						|| $lang->load('tpl_' . $ext->element . '.sys', $source);
					break;

				case 'file':
					$lang->load('files_' . $ext->element . '.sys', JPATH_SITE);
					break;

				case 'library':
				case 'package':
				default:
					$lang->load($ext->element . '.sys', JPATH_SITE)
						|| $lang->load($ext->name . '.sys', JPATH_SITE);
					break;
			}

			$rawName = !empty($manifest['name']) ? $manifest['name'] : $ext->name;
			$translated = Text::_($rawName);
			if ($translated === $rawName && $ext->name !== $rawName) {
				$translated = Text::_($ext->name);
			}

			// If the language constant never resolved (no sys.ini for this extension),
			// fall back to a human-readable version of the raw name/element instead of
			// showing e.g. "plg_content_sigplus" verbatim.
			if (($translated === $rawName && preg_match('/^[A-Z0-9_]+$/', $translated))
				|| preg_match('/^(com|mod|plg|tpl|pkg|lib)_/i', $translated)) {
				$translated = self::prettifyExtensionName($translated);
			}

			$ext->display_name       = $translated;
			$ext->installed_version  = $manifest['version'] ?? '-';
			$ext->available_version  = $updateRows[$ext->extension_id]->version ?? '-';
			$ext->update_description = $updateRows[$ext->extension_id]->description ?? '-';
			$ext->author              = $manifest['author'] ?? '';
			$ext->is_official         = (trim((string) $ext->author) === 'Joomla! Project');
		}

		if ($hideOfficial) {
			$extensions = array_values(array_filter($extensions, static function ($ext) {
				return !$ext->is_official;
			}));
		}

		// Map of package names, keyed by extension_id
		$packageNames = [];
		foreach ($extensions as $ext) {
			if ($ext->type === 'package') {
				$packageNames[$ext->extension_id] = $ext->display_name;
			}
		}

		// Reorder: alphabetical, but package members directly under their package
		$childrenByPackage = [];
		$topLevel = [];
		foreach ($extensions as $ext) {
			if (!empty($ext->package_id) && isset($packageNames[$ext->package_id])) {
				$childrenByPackage[$ext->package_id][] = $ext;
			} else {
				$topLevel[] = $ext;
			}
		}

		$ordered = [];
		foreach ($topLevel as $ext) {
			$ordered[] = $ext;
			if ($ext->type === 'package' && isset($childrenByPackage[$ext->extension_id])) {
				foreach ($childrenByPackage[$ext->extension_id] as $child) {
					$ordered[] = $child;
				}
			}
		}

		// Precompute display flags
		foreach ($ordered as $ext) {
			$ext->is_package     = ($ext->type === 'package');
			$ext->is_child       = (!$ext->is_package && !empty($ext->package_id) && isset($packageNames[$ext->package_id]));
			$ext->parent_package = $ext->is_child ? $packageNames[$ext->package_id] : null;
			$ext->has_update     = (!empty($ext->available_version) && $ext->available_version !== '-');
		}

		return $ordered;
	}

	/**
	 * Quick filters for the Installed Extensions list.
	 *
	 * @param   array   $list  Result of getInstalledExtensions()
	 * @param   string  $mode  all | updates | third | disabled
	 *                         (updates = update available, third = not
	 *                         authored by "Joomla! Project", disabled =
	 *                         not enabled)
	 *
	 * @return  array
	 */
	public static function applyExtensionQuickFilter(array $list, string $mode): array
	{
		switch ($mode) {
			case 'updates':
				$fn = static function ($e) { return $e->has_update; };
				break;
			case 'third':
				$fn = static function ($e) { return !$e->is_official; };
				break;
			case 'disabled':
				$fn = static function ($e) { return (int) $e->enabled !== 1; };
				break;
			default:
				return $list;
		}

		return array_values(array_filter($list, $fn));
	}

	/**
	 * Scan the currently active admin template's CSS (FontAwesome, the
	 * template's own template.css, and Joomla's bundled icomoon set) for
	 * icon classes (".icon-xxx") and return them grouped by source file -
	 * a quick reference for which icon classes actually exist to use in
	 * this admin template, without digging through CSS source by hand.
	 *
	 * @return  array  [file_path => string[] icon names (without the
	 *                 "icon-" prefix), sorted], only for files that were
	 *                 found and contained at least one match
	 */
	public static function getIconClasses(): array
	{
		$template = Factory::getApplication()->getTemplate();

		$candidateFiles = [
			JPATH_ROOT . '/administrator/templates/' . $template . '/css/vendor/fontawesome-free/fontawesome.css',
			JPATH_ROOT . '/media/templates/administrator/' . $template . '/css/vendor/fontawesome-free/fontawesome.css',
			JPATH_ROOT . '/administrator/templates/' . $template . '/css/template.css',
			JPATH_ROOT . '/media/templates/administrator/' . $template . '/css/template.css',
			JPATH_ROOT . '/media/jui/css/icomoon.css',
		];

		$result = [];

		foreach ($candidateFiles as $filePath) {
			if (!is_file($filePath) || !is_readable($filePath)) {
				continue;
			}

			$contents = @file_get_contents($filePath);

			if ($contents === false || $contents === '') {
				continue;
			}

			preg_match_all('/\.icon-([a-zA-Z0-9_-]+?)(?=[:,{\s])/', $contents, $matches);

			$icons = array_unique($matches[1] ?? []);
			$icons = array_values(array_filter($icons, static function ($name) {
				return $name !== 'link-hover';
			}));

			if (empty($icons)) {
				continue;
			}

			sort($icons);
			$result[$filePath] = $icons;
		}

		return $result;
	}

	/**
	 * Format a byte count with automatic unit selection (B/KB/MB/GB/TB/PB)
	 * instead of always showing raw bytes or a fixed unit.
	 *
	 * @param   int|float  $bytes  Size in bytes
	 *
	 * @return  string  e.g. "445,88 MB"
	 */
	public static function formatFileSize($bytes): string
	{
		static $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

		$i    = 0;
		$size = $bytes;

		while ($size >= 1024 && $i < count($units) - 1) {
			$size /= 1024;
			$i++;
		}

		$decimals = ($i === 0) ? 0 : 2;

		return number_format($size, $decimals, ',', ' ') . ' ' . $units[$i];
	}

	/**
	 * Walk $path and return the top $topN folders by size (descending) plus
	 * the overall total. Excluded folders are never entered at all, so
	 * 'total' is the sum of what was actually scanned - $path minus the
	 * excluded folders, not literally the whole disk.
	 *
	 * Single-pass traversal (folder structure and file sizes collected
	 * together), symlinked folders skipped entirely (would otherwise risk
	 * infinite recursion on a symlink loop), and permission errors during
	 * traversal caught so one unreadable folder doesn't abort the whole
	 * scan.
	 *
	 * Adapted from mod_jm_treesize (Maarten Blokdijk, jmodules.com), which
	 * Fero has maintained and improved through many iterations - the
	 * Joomla-3.x-compatible wrappers from that module aren't needed here
	 * since this component only targets Joomla 6.
	 *
	 * @param   string  $path     Root path to scan (e.g. JPATH_ROOT)
	 * @param   array   $exclude  Folder names to skip entirely, anywhere
	 *                            in the tree (e.g. ['cache', 'tmp', '.git'])
	 * @param   int     $topN     How many of the largest folders to return
	 *
	 * @return  array  ['dirs' => [path => bytes, ...] (already sorted,
	 *                 largest first), 'total' => int bytes]
	 */
	/**
	 * Cached wrapper around scanDiskUsage() - scanning JPATH_ROOT is
	 * comparatively expensive (a full filesystem traversal), so this is
	 * cached for 60 minutes via Joomla's own cache controller, the same
	 * pattern used for getSecurityChecks(). Uses its own cache group
	 * (separate from Security Check's) so force-refreshing disk usage
	 * never also discards the Security Check cache, and vice versa.
	 *
	 * @param   array  $exclude       Folder names to skip
	 * @param   int    $topN          How many of the largest folders to return
	 * @param   bool   $forceRefresh  Discard the cache and rescan now
	 *
	 * @return  array  ['dirs' => [...], 'total' => int, 'time' => int
	 *                 (unix timestamp of when the scan actually ran,
	 *                 whether serving fresh or from cache)]
	 */
	public static function getDiskUsageDisplayData(array $exclude, int $topN, bool $forceRefresh = false): array
	{
		$cacheId = 'disk-usage-' . md5(implode(',', $exclude) . '|' . $topN);

		try {
			$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
				->createCacheController('callback', ['defaultgroup' => 'com_fgsystemreport_diskusage', 'lifetime' => 60]);

			if ($forceRefresh) {
				$cache->clean();
			}

			return $cache->get(
				[self::class, 'scanDiskUsageWithTimestamp'],
				[JPATH_ROOT, $exclude, $topN],
				$cacheId
			);
		} catch (\Throwable $e) {
			// Cache unavailable for any reason - compute directly rather
			// than breaking the page.
			return self::scanDiskUsageWithTimestamp(JPATH_ROOT, $exclude, $topN);
		}
	}

	/**
	 * scanDiskUsage() with a 'time' key added, so the cached payload
	 * carries its own creation timestamp - lets the page show "last
	 * scanned: X minutes ago" correctly whether serving fresh or cached
	 * data, without the cache controller itself needing to expose that.
	 *
	 * @param   string  $path     Root path to scan
	 * @param   array   $exclude  Folder names to skip
	 * @param   int     $topN     How many of the largest folders to return
	 *
	 * @return  array
	 */
	/** 'manual' or 'scheduler' - who triggered the current disk scan. */
	public static string $scanSource = 'manual';

	public static function scanDiskUsageWithTimestamp(string $path, array $exclude, int $topN): array
	{
		$scan         = self::scanDiskUsage($path, $exclude, $topN);
		$scan['time'] = time();

		// This only runs on a real scan (cache miss / forced rescan), so each
		// run is one snapshot for the Disk Usage comparison.
		DiskHistoryHelper::record($exclude, $scan);
		GrowthAlertHelper::check($exclude, self::$scanSource);

		return $scan;
	}

	public static function scanDiskUsage(string $path, array $exclude, int $topN): array
	{
		$rootPath  = rtrim($path, '/\\');
		$dirlist   = [];
		$totalSize = 0;
		// folder => its immediate parent folder, built during the same
		// traversal. SplFileInfo::getPath() returns the parent path
		// without string-parsing (unlike dirname()), so walking up to the
		// root afterwards is array lookups against this map, not repeated
		// dirname() calls for every file in a deeply nested tree.
		$parentOf = [];

		try {
			$dirIterator = new \RecursiveDirectoryIterator(
				$path,
				\RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::SKIP_DOTS
			);
			$filtered = new DiskScan\ExcludeFilter($dirIterator, array_flip($exclude));
			$objects  = new \RecursiveIteratorIterator(
				$filtered,
				\RecursiveIteratorIterator::SELF_FIRST,
				\RecursiveIteratorIterator::CATCH_GET_CHILD
			);
		} catch (\UnexpectedValueException $e) {
			// $path itself can't be opened (e.g. bad permissions) - nothing to scan.
			return ['dirs' => [], 'total' => 0];
		}

		// Manual iteration instead of foreach: CATCH_GET_CHILD only catches
		// errors from automatic descent via getChildren(), not exceptions
		// thrown directly from valid()/current()/next() (e.g. an
		// open_basedir restriction on a specific subfolder) - those are
		// caught manually here so one unreadable folder gets skipped and
		// scanning continues instead of the whole thing failing.
		$objects->rewind();

		while (true) {
			try {
				if (!$objects->valid()) {
					break;
				}

				$object = $objects->current();
			} catch (\UnexpectedValueException $e) {
				try {
					$objects->next();
				} catch (\UnexpectedValueException $e2) {
					break;
				}

				continue;
			}

			if ($object->isDir()) {
				$dirPath = $object->getPathName();
				$dirlist[$dirPath]  = 0;
				$parentOf[$dirPath] = $object->getPath();
			} else {
				$size = $object->getSize();
				$totalSize += $size;
				$dir = $object->getPath();

				while (isset($dirlist[$dir]) || $dir === $rootPath) {
					if (isset($dirlist[$dir])) {
						$dirlist[$dir] += $size;
					}

					if ($dir === $rootPath) {
						break;
					}

					$dir = $parentOf[$dir] ?? $rootPath;
				}
			}

			try {
				$objects->next();
			} catch (\UnexpectedValueException $e) {
				break;
			}
		}

		return ['dirs' => self::topNFolders($dirlist, $topN), 'total' => $totalSize];
	}

	/**
	 * Pick the $n largest entries from $dirlist without sorting the whole
	 * array - a bounded min-heap of size $n: O(folders x log n) instead of
	 * O(folders x log folders) from a full sort when only a handful of
	 * rows are actually needed.
	 *
	 * @param   array  $dirlist  [path => bytes]
	 * @param   int    $n        How many to return
	 *
	 * @return  array  [path => bytes], sorted largest first
	 */
	private static function topNFolders(array $dirlist, int $n): array
	{
		if ($n <= 0 || !$dirlist) {
			return [];
		}

		$heap = new DiskScan\TopHeap();

		foreach ($dirlist as $path => $size) {
			if ($heap->count() < $n) {
				$heap->insert([$size, $path]);
			} elseif ($size > $heap->top()[0]) {
				$heap->extract();
				$heap->insert([$size, $path]);
			}
		}

		// The heap yields items smallest-to-largest - reverse for descending display order.
		$top = [];

		while (!$heap->isEmpty()) {
			[$size, $path] = $heap->extract();
			$top[$path] = $size;
		}

		return array_reverse($top, true);
	}
}

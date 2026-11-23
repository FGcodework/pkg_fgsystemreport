<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Sorts the Security Check rows into themes for the Security page.
 *
 * Only the display is grouped; the checks themselves (and their order inside a
 * theme) are untouched, so the score, the history and the exports do not
 * change. A check this class does not know yet lands in "other" - the test
 * tests/SecurityGroupTest.php fails when a new check has not been given a theme.
 *
 * @since  1.18.0
 */
final class SecurityGroupHelper
{
    /** Themes in the order they are shown. */
    public const GROUPS = ['updates', 'files', 'access', 'transport', 'server', 'config', 'ops', 'other'];

    private const PREFIX = 'COM_FGSYSTEMREPORT_SEC_';

    /** Exact check (without the common prefix) => theme. */
    private const MAP = [
        // updates and known vulnerabilities
        'JOOMLA_CORE' => 'updates', 'PHP' => 'updates', 'DB_VERSION' => 'updates', 'EXT_UPDATES' => 'updates',
        'VEL' => 'updates', 'VEL_REVIEW' => 'updates', 'NO_UPDATE_SITE' => 'updates', 'UPDATE_CHANNEL' => 'updates',
        'BACKCOMPAT' => 'updates',
        // files and integrity
        'CORE_FILES' => 'files', 'CRITICAL_FILES' => 'files', 'EXPOSED_FILES' => 'files', 'LEFTOVERS' => 'files',
        'PHP_IN_IMAGES' => 'files', 'PERSISTENCE' => 'files', 'CONFIG_PERMS' => 'files', 'DIR_PERMISSIONS' => 'files',
        'FOLDER_PERMS' => 'files', 'PERMS_SCAN' => 'files', 'BACKEND_PROTECTION' => 'access', 'HTACCESS' => 'files', 'UPLOADS' => 'files',
        // accounts and access
        'SUPERUSERS' => 'access', 'SUPERUSERS_NEW' => 'access', 'MFA' => 'access', 'FAILED_LOGINS' => 'access',
        'ADMIN_NAMES' => 'access', 'REGISTRATION' => 'access', 'PASSWORD_LENGTH' => 'access', 'ACTIONLOGS' => 'access',
        'TEXT_FILTERS' => 'access', 'FIREWALL' => 'access',
        // HTTPS and browser protection
        'FORCE_SSL' => 'transport', 'SSL_CERT' => 'transport', 'SSL_TRUST' => 'transport', 'HSTS' => 'transport',
        'HEADERS' => 'transport', 'FINGERPRINT' => 'transport', 'PERMISSIONS_POLICY' => 'transport',
        // PHP and server
        'PHP_SETTINGS' => 'server', 'PHP_LIMITS' => 'server', 'EXEC_FUNCTIONS' => 'server', 'OPCACHE' => 'server',
        'DISK_FREE' => 'server', 'CLOCK_SKEW' => 'server', 'FLOCK' => 'server', 'EXTERNAL_IP' => 'server',
        // Joomla configuration
        'DEBUG' => 'config', 'ERROR_LEAK' => 'config', 'ERROR_REPORTING' => 'config', 'CONFIG' => 'config', 'SYSTEM_CACHE' => 'config',
        // database, mail and operation
        'DB_PREFIX' => 'ops', 'DB_BLOAT' => 'ops', 'SQL_MODE' => 'ops', 'ORPHAN_ASSETS' => 'ops', 'ORPHAN_EXT' => 'ops',
        'BACKUP' => 'ops', 'MAIL_DNS' => 'ops', 'SCHED' => 'ops', 'FATALS' => 'ops',
    ];

    /** The theme of one check, by its key (COM_FGSYSTEMREPORT_SEC_...). */
    public static function groupOf(string $key): string
    {
        if (strpos($key, self::PREFIX) !== 0) {
            return 'other';
        }

        $name = substr($key, \strlen(self::PREFIX));

        if (isset(self::MAP[$name])) {
            return self::MAP[$name];
        }

        // a check that shows more than one row: REGISTRATION_ADMIN, UPLOADS_OPEN, ...
        foreach (['REGISTRATION_', 'PASSWORD_LENGTH_', 'UPLOADS_', 'TEXT_FILTERS_', 'ADMIN_NAMES_', 'ACTIONLOGS_', 'UPDATE_CHANNEL_'] as $p) {
            if (strpos($name, $p) === 0) {
                return self::MAP[rtrim($p, '_')];
            }
        }

        // PHP extensions: SEC_EXT_IMAGICK, SEC_EXT_MBSTRING, ... (EXT_UPDATES is listed above)
        if (strpos($name, 'EXT_') === 0) {
            return 'server';
        }

        return 'other';
    }

    /**
     * Rows sorted into themes, in display order; empty themes are left out.
     *
     * @param   object[]  $checks  rows with a "key" and a "status"
     *
     * @return  array<string,object[]>  theme => rows (original order kept)
     */
    public static function group(array $checks): array
    {
        $out = array_fill_keys(self::GROUPS, []);

        foreach ($checks as $c) {
            $out[self::groupOf((string) ($c->key ?? ''))][] = $c;
        }

        return array_filter($out);
    }

    /**
     * @param   object[]  $rows
     *
     * @return  array{danger:int,warning:int,total:int}
     */
    public static function counts(array $rows): array
    {
        $n = ['danger' => 0, 'warning' => 0, 'total' => \count($rows)];

        foreach ($rows as $r) {
            if (isset($n[$r->status ?? ''])) {
                $n[$r->status]++;
            }
        }

        return $n;
    }
}

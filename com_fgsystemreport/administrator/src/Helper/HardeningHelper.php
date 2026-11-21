<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Small hardening checks that need no more than a header list or an ini value.
 * Everything here is pure (tests/HardeningTest.php); the Security Check rows
 * are built in FgsystemreportHelper::buildSecurityChecks().
 *
 * @since  1.17.0
 */
final class HardeningHelper
{
    /** Functions that let a script run programs on the server. */
    public const EXEC_FUNCTIONS = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];

    /** Response headers that name the software (and often its version). */
    private const FINGERPRINT = ['x-powered-by' => 'X-Powered-By', 'x-content-encoded-by' => 'X-Content-Encoded-By', 'x-generator' => 'X-Generator'];

    /**
     * Headers that tell a visitor which software runs the site.
     *
     * X-Powered-By and the Joomla headers always count. Server only counts
     * when it carries a version ("Apache/2.4.58"; a bare "Apache" or "nginx"
     * says little).
     *
     * @param   array<string,string>  $headers  lower-case name => value
     *
     * @return  list<array{name:string,value:string}>
     */
    public static function fingerprintHeaders(array $headers): array
    {
        $found = [];

        foreach (self::FINGERPRINT as $lower => $name) {
            $v = trim((string) ($headers[$lower] ?? ''));

            if ($v !== '') {
                $found[] = ['name' => $name, 'value' => mb_substr($v, 0, 80)];
            }
        }

        $server = trim((string) ($headers['server'] ?? ''));

        if ($server !== '' && preg_match('~\d+\.\d+~', $server)) {
            $found[] = ['name' => 'Server', 'value' => mb_substr($server, 0, 80)];
        }

        return $found;
    }

    /** @return  string  warning when the PHP version or the Joomla version is named, otherwise info */
    public static function fingerprintStatus(array $found): string
    {
        foreach ($found as $f) {
            if (\in_array($f['name'], ['X-Powered-By', 'X-Content-Encoded-By', 'X-Generator'], true) && preg_match('~\d+\.\d+~', $f['value'])) {
                return 'warning';
            }
        }

        return 'info';
    }

    /** Is a Permissions-Policy (or the older Feature-Policy) sent? */
    public static function hasPermissionsPolicy(array $headers): bool
    {
        return trim((string) ($headers['permissions-policy'] ?? '')) !== '' || trim((string) ($headers['feature-policy'] ?? '')) !== '';
    }

    /**
     * Which of EXEC_FUNCTIONS the disable_functions ini value does not disable.
     *
     * @return  list<string>
     */
    public static function enabledExecFunctions(string $disableFunctions): array
    {
        $off = array_filter(array_map('trim', explode(',', strtolower($disableFunctions))));

        return array_values(array_diff(self::EXEC_FUNCTIONS, $off));
    }

    /** Does the ini value mean "on"? ("1", "On", "true", "yes") */
    public static function iniOn($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Free disk space.
     *
     * @param   float|int|false|null  $free   bytes free
     * @param   float|int|false|null  $total  bytes in all
     *
     * @return  array{status:string,percent:int}|null  null when the numbers are not usable
     */
    public static function diskStatus($free, $total): ?array
    {
        if (!\is_numeric($free) || !\is_numeric($total) || $total <= 0 || $free < 0 || $free > $total) {
            return null;
        }

        $percent = (int) round($free / $total * 100);
        $mb      = $free / 1048576;

        if ($percent < 5 || $mb < 100) {
            $status = 'danger';
        } elseif ($percent < 10 || $mb < 500) {
            $status = 'warning';
        } else {
            $status = 'ok';
        }

        return ['status' => $status, 'percent' => $percent];
    }
}

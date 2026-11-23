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

    /**
     * Is the DMARC policy "none"? Such a record only asks receivers for reports;
     * mail that fails the checks is still delivered, so nobody is stopped from
     * sending as this domain.
     *
     * @param   string  $policy  value of p= as read from DNS ('' = not found)
     *
     * @since   1.17.1
     */
    public static function dmarcMonitorOnly(string $policy): bool
    {
        return strtolower(trim($policy)) === 'none';
    }

    /** Joomla extensions that act as a web application firewall: plugin element => name. */
    public const FIREWALL_PLUGINS = [
        'siteopssuite'     => 'SiteOps Suite',
        'admintools'       => 'Admin Tools',
        'rsfirewall'       => 'RSFirewall!',
        'securitycheckpro' => 'Securitycheck Pro',
        'securitycheck'    => 'Securitycheck',
    ];

    /**
     * Whether a file name in the web root looks like a leftover admin, test or
     * restore script (phpinfo, adminer, web shells, the Akeeba Kickstart restorer).
     *
     * @since  1.18.12
     */
    public static function isLeftoverTool(string $name): bool
    {
        return (bool) preg_match('/^(adminer|phpinfo|info|test|shell|wso|c99|r57|pma|kickstart)[\w.\-]*\.php$/i', $name);
    }

    /** Status codes a firewall answers with when it refuses a request. */
    private const REFUSAL_CODES = [403, 406, 429, 503];

    /**
     * User-Agent of the requests this component sends to its own site, so the
     * site's firewall and access log show who is knocking.
     *
     * @since  1.18.5
     */
    public static function userAgent(string $version): string
    {
        $version = trim($version);

        return 'FG-System-Report' . ($version !== '' ? '/' . $version : '') . ' (self-check)';
    }

    /**
     * Which of the firewall plugins are installed, split by enabled / disabled.
     *
     * @param   list<array{element:string,enabled:int|string}>  $rows  system plugins from #__extensions
     *
     * @return  array{active:list<string>,disabled:list<string>}
     *
     * @since   1.18.5
     */
    public static function firewallsFromPlugins(array $rows): array
    {
        $out = ['active' => [], 'disabled' => []];

        foreach (self::FIREWALL_PLUGINS as $element => $name) {
            foreach ($rows as $row) {
                if (($row['element'] ?? '') !== $element) {
                    continue;
                }

                $out[(int) ($row['enabled'] ?? 0) === 1 ? 'active' : 'disabled'][] = $name;
                break;
            }
        }

        return $out;
    }

    /**
     * A firewall or CDN in front of the site, read from the response headers
     * (any response, so also the home page).
     *
     * @param   array<string,string>  $headers  lower-case name => value
     *
     * @return  string  name, '' when none is recognised
     *
     * @since   1.18.5
     */
    public static function firewallFromHeaders(array $headers): string
    {
        $server = strtolower((string) ($headers['server'] ?? ''));

        if (isset($headers['cf-ray']) || $server === 'cloudflare') {
            return 'Cloudflare';
        }

        if (isset($headers['x-sucuri-id']) || isset($headers['x-sucuri-cache']) || strpos($server, 'sucuri') !== false) {
            return 'Sucuri';
        }

        if (strpos($server, 'imunify360') !== false || isset($headers['x-imunify360-request-id'])) {
            return 'Imunify360';
        }

        return '';
    }

    /**
     * Did a firewall refuse this request before it reached the file asked for?
     * A plain 403 from the web server (e.g. .htaccess denies dotfiles) is not
     * one: only a response that carries the firewall's own mark counts.
     *
     * @param   array<string,string>  $headers  lower-case name => value
     *
     * @return  string  name of the firewall, '' when the response is not a firewall refusal
     *
     * @since   1.18.5
     */
    public static function blockedByFirewall(int $code, array $headers): string
    {
        if (!\in_array($code, self::REFUSAL_CODES, true)) {
            return '';
        }

        // SiteOps Suite: its own page is sent with a strict, self-contained Content-Security-Policy
        $csp = strtolower((string) ($headers['content-security-policy'] ?? ''));

        if ($code === 403 && strpos($csp, "default-src 'none'") === 0 && strpos($csp, "form-action 'self'") !== false
            && strpos(strtolower((string) ($headers['x-robots-tag'] ?? '')), 'noindex') !== false) {
            return 'SiteOps Suite';
        }

        // Cloudflare marks what it challenged or blocked; a 403 from the origin behind it has no such header
        if (isset($headers['cf-mitigated'])) {
            return 'Cloudflare';
        }

        $name = self::firewallFromHeaders($headers);

        // Sucuri / Imunify360 refuse with the mark of their own proxy on the response
        return \in_array($name, ['Sucuri', 'Imunify360'], true) ? $name : '';
    }

    /**
     * Does an error page (or any response) show server internals: a stack trace,
     * the file and line of an exception, a PHP warning with a path, or the path
     * of the Joomla installation? Such a page tells an attacker where the site
     * lives and which code runs.
     *
     * @param   string  $body  start of the response
     * @param   string  $root  file system root of the site (JPATH_ROOT), '' to skip that test
     *
     * @since   1.18.6
     */
    public static function errorPageLeak(string $body, string $root = ''): bool
    {
        if ($body === '') {
            return false;
        }

        $patterns = [
            // exception dump: "Exception File: /path/x.php", "Exception Trace:"
            '/Exception\s+(File|Line|Trace)\s*:/i',
            // PHP stack trace: "#0 /path/x.php(12): Class->method()"
            '/#0\s+\S+\.php\(\d+\)/',
            '/Stack trace:/i',
            // PHP warning / notice / fatal error printed into the page
            '/(Warning|Fatal error|Notice|Deprecated|Parse error|Uncaught [A-Za-z\\\\]+)\s*(<\/b>)?:.{0,300}?\sin\s+(<b>)?\S+\.php(<\/b>)?\s+on\s+line/is',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $body)) {
                return true;
            }
        }

        $root = rtrim(str_replace('\\', '/', $root), '/');

        return strlen($root) >= 6 && strpos(str_replace('\\', '/', $body), $root . '/') !== false;
    }

    /**
     * Which probed files really are there: the answer is a success, carries the
     * mark of that file, and the page a nonexistent address gives does not carry
     * it too. That last condition makes the test independent of "soft 404" sites,
     * where a nonexistent address answers 200 with an ordinary page.
     *
     * @param   array<string,array{code:int,body:string}>  $responses   path => answer
     * @param   array<string,string>                       $signatures  path => regex of the file's content
     * @param   string                                     $canaryBody  answer to a random nonexistent address
     *
     * @return  list<string>  paths
     *
     * @since   1.18.6
     */
    public static function exposedByContent(array $responses, array $signatures, string $canaryBody): array
    {
        $found = [];

        foreach ($signatures as $path => $signature) {
            $r = $responses[$path] ?? null;

            if ($r === null || !\in_array((int) $r['code'], [200, 206], true)) {
                continue;
            }

            if (preg_match($signature, (string) $r['body']) && !preg_match($signature, $canaryBody)) {
                $found[] = $path;
            }
        }

        return $found;
    }
}

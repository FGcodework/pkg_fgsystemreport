<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\HardeningHelper as H;

return [
    'headers that name the software are found' => function () {
        $f = H::fingerprintHeaders([
            'x-powered-by' => 'PHP/8.3.12', 'x-content-encoded-by' => 'Joomla! 5.4.10', 'server' => 'Apache/2.4.58 (Ubuntu)', 'content-type' => 'text/html',
        ]);
        assertSame(['X-Powered-By', 'X-Content-Encoded-By', 'Server'], array_column($f, 'name'));
        assertSame('PHP/8.3.12', $f[0]['value']);
    },

    'a Server header without a version says little' => function () {
        assertCount(0, H::fingerprintHeaders(['server' => 'nginx']));
        assertCount(0, H::fingerprintHeaders(['server' => 'Apache']));
        assertCount(0, H::fingerprintHeaders(['server' => '']));
        assertCount(1, H::fingerprintHeaders(['server' => 'nginx/1.24.0']));
        assertCount(0, H::fingerprintHeaders([]));
    },

    'empty fingerprint headers are ignored' => function () {
        assertCount(0, H::fingerprintHeaders(['x-powered-by' => '  ', 'x-generator' => '']));
        assertCount(1, H::fingerprintHeaders(['x-generator' => 'Drupal 10']));
    },

    'fingerprint severity: a named version is a warning, the rest information' => function () {
        assertSame('warning', H::fingerprintStatus(H::fingerprintHeaders(['x-powered-by' => 'PHP/8.3.12'])));
        assertSame('warning', H::fingerprintStatus(H::fingerprintHeaders(['x-content-encoded-by' => 'Joomla! 5.4.10'])));
        assertSame('info', H::fingerprintStatus(H::fingerprintHeaders(['x-powered-by' => 'PHP'])));
        assertSame('info', H::fingerprintStatus(H::fingerprintHeaders(['server' => 'Apache/2.4.58'])));
    },

    'long header values are cut' => function () {
        $f = H::fingerprintHeaders(['x-powered-by' => str_repeat('A', 500)]);
        assertSame(80, mb_strlen($f[0]['value']));
    },

    'Permissions-Policy or the older Feature-Policy counts' => function () {
        assertTrue(H::hasPermissionsPolicy(['permissions-policy' => 'camera=()']));
        assertTrue(H::hasPermissionsPolicy(['feature-policy' => "camera 'none'"]));
        assertSame(false, H::hasPermissionsPolicy(['permissions-policy' => ' ']));
        assertSame(false, H::hasPermissionsPolicy([]));
    },

    'disable_functions: which program-running functions are still available' => function () {
        assertSame(H::EXEC_FUNCTIONS, H::enabledExecFunctions(''));
        assertSame([], H::enabledExecFunctions('exec, shell_exec ,system,PASSTHRU,proc_open,popen,mail'));
        assertSame(['system', 'popen'], H::enabledExecFunctions('exec,shell_exec,passthru,proc_open'));
        assertSame(['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'], H::enabledExecFunctions('mail,pcntl_exec'));
    },

    'ini values read as on / off' => function () {
        assertTrue(H::iniOn('1'));
        assertTrue(H::iniOn('On'));
        assertTrue(H::iniOn('true'));
        assertSame(false, H::iniOn('0'));
        assertSame(false, H::iniOn(''));
        assertSame(false, H::iniOn('Off'));
        assertSame(false, H::iniOn(false));
    },

    'disk space: ok, warning and danger' => function () {
        $gb = 1073741824;
        assertSame(['status' => 'ok', 'percent' => 50], H::diskStatus(50 * $gb, 100 * $gb));
        assertSame('warning', H::diskStatus(8 * $gb, 100 * $gb)['status']);      // 8 %
        assertSame('danger', H::diskStatus(4 * $gb, 100 * $gb)['status']);       // 4 %
        assertSame('danger', H::diskStatus(300 * 1048576, 10 * $gb)['status']);  // 300 MB of 10 GB = 3 %
    },

    'disk space: little in absolute terms is bad even when the percentage is fine' => function () {
        $gb = 1073741824;
        assertSame('danger', H::diskStatus(50 * 1048576, 0.4 * $gb)['status']);   // 50 MB
        assertSame('warning', H::diskStatus(300 * 1048576, 1.5 * $gb)['status']); // 20 %, but 300 MB
        assertSame('ok', H::diskStatus(2 * $gb, 5 * $gb)['status']);
    },

    'disk space: unusable numbers give nothing' => function () {
        assertSame(null, H::diskStatus(false, 100));
        assertSame(null, H::diskStatus(10, false));
        assertSame(null, H::diskStatus(10, 0));
        assertSame(null, H::diskStatus(200, 100));
        assertSame(null, H::diskStatus(-1, 100));
        assertSame(null, H::diskStatus(null, null));
    },

    'DMARC p=none is monitoring only' => function () {
        assertTrue(H::dmarcMonitorOnly('none'));
        assertTrue(H::dmarcMonitorOnly('NONE'));
        assertTrue(H::dmarcMonitorOnly(' none '));
        assertSame(false, H::dmarcMonitorOnly('quarantine'));
        assertSame(false, H::dmarcMonitorOnly('reject'));
        assertSame(false, H::dmarcMonitorOnly(''));
    },
    'own requests carry an identifiable User-Agent' => function () {
        assertSame('FG-System-Report/1.18.5 (self-check)', H::userAgent('1.18.5'));
        assertSame('FG-System-Report (self-check)', H::userAgent(' '));
    },

    'the SiteOps Suite refusal page is recognised, a plain 403 is not' => function () {
        $siteops = [
            'content-security-policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'x-robots-tag' => 'noindex, nofollow',
        ];
        assertSame('SiteOps Suite', H::blockedByFirewall(403, $siteops));
        assertSame('', H::blockedByFirewall(200, $siteops));
        assertSame('', H::blockedByFirewall(404, $siteops));
        // Apache denying a dotfile: no such headers
        assertSame('', H::blockedByFirewall(403, ['server' => 'Apache/2.4.58', 'content-type' => 'text/html']));
        assertSame('', H::blockedByFirewall(403, []));
    },

    'Cloudflare counts only when it marks the response as mitigated' => function () {
        assertSame('Cloudflare', H::blockedByFirewall(403, ['cf-ray' => 'abc', 'cf-mitigated' => 'challenge']));
        assertSame('', H::blockedByFirewall(403, ['cf-ray' => 'abc', 'server' => 'cloudflare']));
        assertSame('Sucuri', H::blockedByFirewall(403, ['x-sucuri-id' => '15008']));
        assertSame('Imunify360', H::blockedByFirewall(403, ['server' => 'imunify360-webshield/1.21']));
    },

    'a firewall or CDN in front of the site is read from the headers' => function () {
        assertSame('Cloudflare', H::firewallFromHeaders(['cf-ray' => '8a1-PRG']));
        assertSame('Cloudflare', H::firewallFromHeaders(['server' => 'cloudflare']));
        assertSame('Sucuri', H::firewallFromHeaders(['x-sucuri-cache' => 'HIT']));
        assertSame('', H::firewallFromHeaders(['server' => 'Apache/2.4.58']));
        assertSame('', H::firewallFromHeaders([]));
    },

    'firewall plugins are told apart: enabled, disabled, not installed' => function () {
        $r = H::firewallsFromPlugins([
            ['element' => 'siteopssuite', 'enabled' => '1'],
            ['element' => 'admintools', 'enabled' => 0],
            ['element' => 'fgremovegenerator', 'enabled' => 1],
        ]);
        assertSame(['SiteOps Suite'], $r['active']);
        assertSame(['Admin Tools'], $r['disabled']);
        assertSame(['active' => [], 'disabled' => []], H::firewallsFromPlugins([]));
    },
];

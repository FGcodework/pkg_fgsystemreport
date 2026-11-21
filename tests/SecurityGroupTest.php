<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\SecurityGroupHelper as G;

/** Every SEC_ key the component can add, read from its own source. */
function sg_keys(): array
{
    $root = __DIR__ . '/../com_fgsystemreport/administrator/src/Helper/';
    $keys = [];

    foreach (['FgsystemreportHelper.php', 'ConfigAuditHelper.php', 'CoreIntegrityHelper.php', 'CriticalFilesHelper.php'] as $f) {
        // a check is the first argument of $add() / $row(), or the 'key' / $key of a row built elsewhere
        preg_match_all('~(?:\$(?:add|row)\(\s*|\'key\'\s*=>\s*|\$key\s*=\s*)\'(COM_FGSYSTEMREPORT_SEC_[A-Z0-9_]+)\'~', (string) file_get_contents($root . $f), $m);
        $keys = array_merge($keys, $m[1]);
    }

    return array_values(array_unique($keys));
}

return [
    'known checks land in their theme' => function () {
        assertSame('updates', G::groupOf('COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE'));
        assertSame('files', G::groupOf('COM_FGSYSTEMREPORT_SEC_CORE_FILES'));
        assertSame('access', G::groupOf('COM_FGSYSTEMREPORT_SEC_MFA'));
        assertSame('transport', G::groupOf('COM_FGSYSTEMREPORT_SEC_HSTS'));
        assertSame('server', G::groupOf('COM_FGSYSTEMREPORT_SEC_DISK_FREE'));
        assertSame('config', G::groupOf('COM_FGSYSTEMREPORT_SEC_DEBUG'));
        assertSame('ops', G::groupOf('COM_FGSYSTEMREPORT_SEC_MAIL_DNS'));
    },

    'rows of one check and PHP extensions follow their check' => function () {
        assertSame('access', G::groupOf('COM_FGSYSTEMREPORT_SEC_REGISTRATION_CAPTCHA'));
        assertSame('files', G::groupOf('COM_FGSYSTEMREPORT_SEC_UPLOADS_DANGEROUS'));
        assertSame('server', G::groupOf('COM_FGSYSTEMREPORT_SEC_EXT_MBSTRING'));
        assertSame('updates', G::groupOf('COM_FGSYSTEMREPORT_SEC_EXT_UPDATES'));
    },

    'unknown or foreign keys go to other' => function () {
        assertSame('other', G::groupOf('COM_FGSYSTEMREPORT_SEC_SOMETHING_NEW'));
        assertSame('other', G::groupOf('SOMETHING_ELSE'));
        assertSame('other', G::groupOf(''));
    },

    'every check the component can add has a theme' => function () {
        $unknown = [];

        foreach (sg_keys() as $k) {
            if (G::groupOf($k) === 'other') {
                $unknown[] = $k;
            }
        }

        assertSame([], $unknown, 'checks without a theme (add them to SecurityGroupHelper::MAP)');
    },

    'group() keeps order inside a theme and drops empty themes' => function () {
        $rows = [
            (object) ['key' => 'COM_FGSYSTEMREPORT_SEC_MFA', 'status' => 'ok'],
            (object) ['key' => 'COM_FGSYSTEMREPORT_SEC_JOOMLA_CORE', 'status' => 'danger'],
            (object) ['key' => 'COM_FGSYSTEMREPORT_SEC_SUPERUSERS', 'status' => 'warning'],
        ];
        $g = G::group($rows);
        assertSame(['updates', 'access'], array_keys($g));
        assertSame(['COM_FGSYSTEMREPORT_SEC_MFA', 'COM_FGSYSTEMREPORT_SEC_SUPERUSERS'], array_map(static fn ($r) => $r->key, $g['access']));
        assertSame(['danger' => 0, 'warning' => 1, 'total' => 2], G::counts($g['access']));
    },
];

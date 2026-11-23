<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\PermissionScanHelper as P;

function ps_tree(): string
{
    $d = sys_get_temp_dir() . '/fgsr_ps_' . bin2hex(random_bytes(4));
    mkdir($d . '/sub', 0755, true);
    mkdir($d . '/tmp', 0777, true);
    mkdir($d . '/open', 0755, true);
    mkdir($d . '/administrator/cache', 0755, true);
    file_put_contents($d . '/ok.php', '<?php');
    file_put_contents($d . '/sub/bad.php', '<?php');
    file_put_contents($d . '/sub/data.txt', 'x');
    file_put_contents($d . '/tmp/inside.php', '<?php');
    file_put_contents($d . '/administrator/cache/inside.php', '<?php');
    chmod($d . '/ok.php', 0664);
    chmod($d . '/sub/bad.php', 0666);
    chmod($d . '/sub/data.txt', 0666);
    chmod($d . '/tmp/inside.php', 0777);
    chmod($d . '/administrator/cache/inside.php', 0777);
    chmod($d . '/open', 0777);
    chmod($d . '/tmp', 0777);

    return $d;
}

function ps_rm(string $d): void
{
    exec('rm -rf ' . escapeshellarg($d));
}

return [
    'classify: group-writable is normal, world-writable is not' => function () {
        assertSame('', P::classify(0100644, false, 'a.php'));
        assertSame('', P::classify(0100664, false, 'a.php'));
        assertSame('world_php', P::classify(0100666, false, 'a.php'));
        assertSame('world_php', P::classify(0100777, false, 'index.PHP'));
        assertSame('world_php', P::classify(0100666, false, 'x.phtml'));
        assertSame('world_file', P::classify(0100666, false, 'a.txt'));
        assertSame('world_file', P::classify(0100666, false, 'a.php.txt'));
    },
    'classify: folders - sticky bit makes a world-writable folder harmless' => function () {
        assertSame('', P::classify(040755, true));
        assertSame('', P::classify(040775, true));
        assertSame('world_dir', P::classify(040777, true));
        assertSame('', P::classify(041777, true));
    },
    'classify: setuid and setgid files are reported' => function () {
        assertSame('special', P::classify(0104755, false, 'x'));
        assertSame('special', P::classify(0102755, false, 'x'));
        assertSame('', P::classify(0100755, false, 'x'));
    },
    'scan: finds the bad entries and skips the folders Joomla writes to' => function () {
        if (DIRECTORY_SEPARATOR === '\\') {
            return;
        }

        $d = ps_tree();
        $r = P::scan($d);
        ps_rm($d);

        assertTrue($r !== null);
        assertSame(1, $r['counts']['world_php']);
        assertSame(['sub/bad.php'], $r['examples']['world_php']);
        assertSame(1, $r['counts']['world_file']);
        assertSame(1, $r['counts']['world_dir']);
        assertSame(['open/'], $r['examples']['world_dir']);
        assertSame(0, $r['counts']['special']);
        assertTrue($r['complete']);
    },
    'scan: stops at the entry budget and says so' => function () {
        if (DIRECTORY_SEPARATOR === '\\') {
            return;
        }

        $d = ps_tree();
        $r = P::scan($d, 4.0, 2);
        ps_rm($d);

        assertTrue($r !== null);
        assertTrue(!$r['complete']);
    },
    'scan: a missing folder gives null' => function () {
        assertSame(null, P::scan('/nonexistent/fgsr/' . bin2hex(random_bytes(3))));
    },
];

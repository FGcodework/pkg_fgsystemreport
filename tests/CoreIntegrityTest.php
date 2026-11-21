<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\CoreIntegrityEngine as E;
use Fg\Component\Fgsystemreport\Administrator\Helper\CoreIntegrityHelper as H;

/** Temporary folder tree for one test. */
function ci_tmp(): string
{
    $d = sys_get_temp_dir() . '/fgsr-ci-' . bin2hex(random_bytes(4));
    mkdir($d . '/site', 0777, true);
    mkdir($d . '/work', 0777, true);

    return $d;
}

function ci_rm(string $d): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }

    rmdir($d);
}

/** @param array<string,string> $files */
function ci_zip(string $path, array $files): void
{
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $n => $c) {
        $z->addFromString($n, $c);
    }

    $z->close();
}

/** Site that matches the package, then drive the engine to the end. */
function ci_run(string $tmp, array $package, callable $tweakSite, ?string $hash = null, int $maxSteps = 50): array
{
    $zip = $tmp . '/pkg.zip';
    ci_zip($zip, $package);

    foreach ($package as $n => $c) {
        if (substr($n, -1) === '/') {
            continue;
        }

        @mkdir(dirname($tmp . '/site/' . $n), 0777, true);
        file_put_contents($tmp . '/site/' . $n, $c);
    }

    $tweakSite($tmp . '/site');

    $name  = E::packageName('5.4.1');
    $json  = ['assets' => [['name' => $name, 'digest' => 'sha256:' . ($hash ?? hash_file('sha256', $zip))]]];
    $e     = new E($tmp . '/work', $tmp . '/site', '5.4.1', static fn () => $json, static fn ($url, $file) => copy($zip, $file));
    $last  = null;

    for ($i = 0; $i < $maxSteps; $i++) {
        $last = $e->step(5.0);

        if ($last['done']) {
            break;
        }
    }

    return $last;
}

$pkg = [
    'index.php'                       => "<?php // index\n",
    'libraries/src/Foo.php'           => "<?php class Foo {}\n",
    'templates/cassiopeia/index.php'  => "<?php // tpl\n",
    'language/en-GB/joomla.ini'       => "A=\"b\"\n",
    'cache/index.html'                => "<!DOCTYPE html><title></title>\n",
    'htaccess.txt'                    => "# sample\n",
    'robots.txt.dist'                 => "User-agent: *\n",
    'installation/index.php'          => "<?php // installer\n",
];

return [
    'release versions are stable x.y.z only' => function () {
        assertTrue(E::isReleaseVersion('5.4.1'));
        assertSame(false, E::isReleaseVersion('5.4.1-rc1'));
        assertSame(false, E::isReleaseVersion('6.0.0-alpha1'));
        assertSame(false, E::isReleaseVersion('5.4'));
        assertSame('Joomla_5.4.1-Stable-Update_Package.zip', E::packageName('5.4.1'));
    },

    'parseRelease reads the asset digest' => function () {
        $h = str_repeat('ab', 32);
        $r = E::parseRelease(['assets' => [
            ['name' => 'Joomla_5.4.1-Stable-Full_Package.zip', 'digest' => 'sha256:' . str_repeat('11', 32)],
            ['name' => 'Joomla_5.4.1-Stable-Update_Package.zip', 'digest' => 'sha256:' . strtoupper($h)],
        ]], '5.4.1');
        assertSame($h, $r['hash']);
        assertSame('sha256', $r['algo']);
        assertSame('https://github.com/joomla/joomla-cms/releases/download/5.4.1/Joomla_5.4.1-Stable-Update_Package.zip', $r['url']);
    },

    'parseRelease falls back to the release notes table' => function () {
        $h    = str_repeat('cd', 32);
        $body = "| Package | SHA-256 |\n| --- | --- |\n| [Joomla_5.4.1-Stable-Update_Package.zip](https://github.com/joomla/joomla-cms/releases/download/5.4.1/Joomla_5.4.1-Stable-Update_Package.zip) | `$h` |\n";
        $r    = E::parseRelease(['assets' => [], 'body' => $body], '5.4.1');
        assertSame($h, $r['hash']);
    },

    'parseRelease refuses what it cannot vouch for' => function () {
        assertSame(null, E::parseRelease(null, '5.4.1'));
        assertSame(null, E::parseRelease([], '5.4.1'));
        assertSame(null, E::parseRelease(['assets' => [['name' => 'Joomla_5.4.1-Stable-Update_Package.zip', 'digest' => 'sha1:abc']]], '5.4.1'));
        assertSame(null, E::parseRelease(['assets' => [['name' => 'Joomla_5.4.1-Stable-Update_Package.zip', 'digest' => 'sha256:' . str_repeat('1', 32)]]], '5.4.1'));
        assertSame(null, E::parseRelease(['assets' => [['name' => 'Joomla_5.4.1-Stable-Update_Package.zip', 'digest' => 'sha256:' . str_repeat('11', 32)]]], '5.4.1-rc1'));
    },

    'isCompared skips samples, installer and bad paths' => function () {
        assertTrue(E::isCompared('index.php'));
        assertTrue(E::isCompared('libraries/src/Foo.php'));
        assertSame(false, E::isCompared('htaccess.txt'));
        assertSame(false, E::isCompared('robots.txt.dist'));
        assertSame(false, E::isCompared('installation/index.php'));
        assertSame(false, E::isCompared('../etc/passwd'));
        assertSame(false, E::isCompared('a/../../b.php'));
        assertSame(false, E::isCompared('/abs.php'));
        assertSame(false, E::isCompared(''));
    },

    'missing placeholder index.html does not matter' => function () {
        assertSame(false, E::missingMatters('cache/index.html'));
        assertSame(false, E::missingMatters('images/index.html'));
        assertTrue(E::missingMatters('libraries/index.html'));
        assertTrue(E::missingMatters('libraries/src/Foo.php'));
    },

    'critical paths: code of the core, not templates or languages' => function () {
        assertTrue(H::isCriticalPath('index.php'));
        assertTrue(H::isCriticalPath('libraries/src/Foo.php'));
        assertTrue(H::isCriticalPath('media/system/js/core.js'));
        assertSame(false, H::isCriticalPath('templates/cassiopeia/index.php'));
        assertSame(false, H::isCriticalPath('administrator/templates/atum/index.php'));
        assertSame(false, H::isCriticalPath('language/en-GB/langmetadata.xml'));
        assertSame(false, H::isCriticalPath('media/system/css/core.css'));
    },

    'evaluate: never, other version, stale, ok' => function () {
        $now = 1_800_000_000;
        assertSame('never', H::evaluate(null, '5.4.1', $now)['code']);
        assertSame('never', H::evaluate(['version' => '5.4.0', 'time' => $now], '5.4.1', $now)['code']);
        assertSame('info', H::evaluate(['version' => '5.4.1', 'time' => $now - 91 * 86400, 'nModified' => 3], '5.4.1', $now)['status']);
        assertSame('stale', H::evaluate(['version' => '5.4.1', 'time' => $now - 91 * 86400], '5.4.1', $now)['code']);
        $ok = H::evaluate(['version' => '5.4.1', 'time' => $now - 2 * 86400, 'nModified' => 0, 'nMissing' => 0], '5.4.1', $now);
        assertSame('ok', $ok['status']);
        assertSame(2, $ok['days']);
    },

    'evaluate: modified core PHP is danger, other changes warn' => function () {
        $now = 1_800_000_000;
        $r   = ['version' => '5.4.1', 'time' => $now, 'nModified' => 1, 'nMissing' => 0, 'modified' => ['libraries/src/Foo.php']];
        assertSame('danger', H::evaluate($r, '5.4.1', $now)['status']);
        assertSame('modified', H::evaluate($r, '5.4.1', $now)['code']);

        $r['modified'] = ['templates/cassiopeia/index.php', 'media/system/css/core.css'];
        $r['nModified'] = 2;
        assertSame('warning', H::evaluate($r, '5.4.1', $now)['status']);

        $m = ['version' => '5.4.1', 'time' => $now, 'nModified' => 0, 'nMissing' => 2, 'missing' => ['a.php', 'b.php']];
        $e = H::evaluate($m, '5.4.1', $now);
        assertSame('warning', $e['status']);
        assertSame('missing', $e['code']);

        $r['nMissing'] = 1;
        assertSame('both', H::evaluate($r, '5.4.1', $now)['code']);
    },

    'engine: untouched site passes, ignored files are not counted' => function () use ($pkg) {
        $t = ci_tmp();
        $r = ci_run($t, $pkg, static function (string $site) {
            unlink($site . '/cache/index.html');   // placeholder missing: fine
            file_put_contents($site . '/htaccess.txt', 'changed on purpose');
            file_put_contents($site . '/extra.php', '<?php // not in the package');
        });
        ci_rm($t);

        assertSame(null, $r['error']);
        assertTrue($r['done']);
        assertSame(100, $r['progress']);
        $res = $r['result'];
        assertSame(0, $res['nModified']);
        assertSame(0, $res['nMissing']);
        assertSame(4, $res['checked']);   // index, Foo, cassiopeia index, joomla.ini
        assertSame('5.4.1', $res['version']);
        assertSame('github', $res['source']);
    },

    'engine: finds modified and missing files' => function () use ($pkg) {
        $t = ci_tmp();
        $r = ci_run($t, $pkg, static function (string $site) {
            file_put_contents($site . '/libraries/src/Foo.php', "<?php class Foo { function __construct(){ eval(\$_POST['x']); } }\n");
            unlink($site . '/language/en-GB/joomla.ini');
        });
        ci_rm($t);

        $res = $r['result'];
        assertSame(['libraries/src/Foo.php'], $res['modified']);
        assertSame(['language/en-GB/joomla.ini'], $res['missing']);
        assertSame(1, $res['nModified']);
        assertSame(1, $res['nMissing']);
        assertSame('danger', H::evaluate($res, '5.4.1', $res['time'])['status']);
    },

    'engine: a package that does not match its SHA-256 is discarded' => function () use ($pkg) {
        $t = ci_tmp();
        $r = ci_run($t, $pkg, static function () {}, str_repeat('0', 64));
        $left = glob($t . '/work/.core-package.zip');
        $sums = glob($t . '/work/core-*.json');
        ci_rm($t);

        assertTrue($r['done']);
        assertSame('hash', $r['error']);
        assertSame(null, $r['result']);
        assertCount(0, $left);
        assertCount(0, $sums);
    },

    'engine: unknown release stops with source error' => function () {
        $t = ci_tmp();
        $e = new E($t . '/work', $t . '/site', '5.4.1', static fn () => null, static fn () => false);
        $r = $e->step(5.0);
        ci_rm($t);

        assertTrue($r['done']);
        assertSame('source', $r['error']);
    },

    'engine: failed download is reported' => function () {
        $t    = ci_tmp();
        $json = ['assets' => [['name' => E::packageName('5.4.1'), 'digest' => 'sha256:' . str_repeat('1', 64)]]];
        $e    = new E($t . '/work', $t . '/site', '5.4.1', static fn () => $json, static fn () => false);
        $e->step(5.0);
        $r = $e->step(5.0);
        ci_rm($t);

        assertSame('download', $r['error']);
    },

    'engine: development versions are not compared' => function () {
        $t = ci_tmp();
        $e = new E($t . '/work', $t . '/site', '6.0.0-alpha2', static fn () => null, static fn () => false);
        $r = $e->step(5.0);
        ci_rm($t);

        assertSame('version', $r['error']);
        assertSame(false, $r['done']);
    },

    'engine: tiny budgets resume to the same result' => function () use ($pkg) {
        $t = ci_tmp();
        $zip = $t . '/pkg.zip';

        for ($k = 0; $k < 300; $k++) {
            $pkg['libraries/gen/f' . $k . '.php'] = "<?php // $k\n" . str_repeat('x', 200);
        }

        ci_zip($zip, $pkg);

        foreach ($pkg as $n => $c) {
            @mkdir(dirname($t . '/site/' . $n), 0777, true);
            file_put_contents($t . '/site/' . $n, $c);
        }

        file_put_contents($t . '/site/index.php', '<?php // tampered');

        $json = ['assets' => [['name' => E::packageName('5.4.1'), 'digest' => 'sha256:' . hash_file('sha256', $zip)]]];
        $e    = new E($t . '/work', $t . '/site', '5.4.1', static fn () => $json, static fn ($u, $f) => copy($zip, $f));
        $steps = 0;
        $r     = null;

        do {
            $r = $e->step(0.0);
            $steps++;
        } while (!$r['done'] && $steps < 5000);

        ci_rm($t);

        assertSame(null, $r['error']);
        assertSame(['index.php'], $r['result']['modified']);
        assertTrue($steps > 4);
    },

    'engine: the second run reuses the fingerprints (no download)' => function () use ($pkg) {
        $t   = ci_tmp();
        $r1  = ci_run($t, $pkg, static function () {});
        $e   = new E($t . '/work', $t . '/site', '5.4.1', static fn () => throw new RuntimeException('no api'), static fn () => throw new RuntimeException('no download'));
        assertTrue($e->hasFingerprints());
        file_put_contents($t . '/site/libraries/src/Foo.php', '<?php // changed after the first check');
        $r2 = $e->step(5.0);
        ci_rm($t);

        assertSame(0, $r1['result']['nModified']);
        assertSame(null, $r2['error']);
        assertSame(['libraries/src/Foo.php'], $r2['result']['modified']);
    },
];

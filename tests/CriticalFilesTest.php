<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\CriticalFilesHelper as C;

$ev = static fn (string $type, string $utc) => (object) ['type' => $type, 'created_at' => $utc];
$ts = static fn (string $utc) => strtotime($utc . ' UTC');

return [
    'unchanged files produce no change' => function () {
        $b = ['index.php' => ['hash' => 'a'], 'configuration.php' => ['hash' => 'b']];
        $n = ['index.php' => ['hash' => 'a', 'mtime' => 100], 'configuration.php' => ['hash' => 'b', 'mtime' => 100]];
        assertCount(0, C::diff($b, $n, 50));
    },

    'modified, added and deleted are told apart' => function () {
        $b = ['index.php' => ['hash' => 'a'], '.htaccess' => ['hash' => ''], 'php.ini' => ['hash' => 'p']];
        $n = ['index.php' => ['hash' => 'x', 'mtime' => 300], '.htaccess' => ['hash' => 'h', 'mtime' => 310], 'php.ini' => null];
        $d = [];

        foreach (C::diff($b, $n, 50) as $c) {
            $d[$c['path']] = $c['type'];
        }

        assertSame(['index.php' => 'modified', '.htaccess' => 'added', 'php.ini' => 'deleted'], $d);
    },

    'a file that was never there is not a deletion' => function () {
        $b = ['web.config' => ['hash' => '']];
        assertCount(0, C::diff($b, ['web.config' => null], 50));
    },

    'path new in the list: old file is taken as it is, newer file is reported' => function () {
        $n = ['api/index.php' => ['hash' => 'a', 'mtime' => 40], 'web.config' => ['hash' => 'b', 'mtime' => 90]];
        $d = C::diff([], $n, 50);
        assertCount(1, $d);
        assertSame('web.config', $d[0]['path']);
        assertSame('added', $d[0]['type']);
    },

    'an update or an install shortly after the change explains it' => function () use ($ev, $ts) {
        $t = $ts('2026-05-01 10:00:00');
        assertTrue(C::isExpected('index.php', $t, [$ev('update', '2026-05-01 10:05:00')]));
        assertTrue(C::isExpected('index.php', $t, [$ev('install', '2026-05-01 10:29:00')]));
        assertSame(false, C::isExpected('index.php', $t, [$ev('update', '2026-05-01 10:45:00')]));   // too long after
        assertTrue(C::isExpected('index.php', $t, [$ev('update', '2026-05-01 09:55:00')]));          // event logged just before
        assertSame(false, C::isExpected('index.php', $t, [$ev('update', '2026-05-01 09:30:00')]));
        assertSame(false, C::isExpected('index.php', $t, [$ev('enable', '2026-05-01 10:05:00')]));
    },

    'a configuration save explains configuration.php only' => function () use ($ev, $ts) {
        $t = $ts('2026-05-01 10:00:00');
        assertTrue(C::isExpected('configuration.php', $t, [$ev('config', '2026-05-01 10:00:30')]));
        assertSame(false, C::isExpected('configuration.php', $t, [$ev('config', '2026-05-01 11:00:00')]));
        assertSame(false, C::isExpected('index.php', $t, [$ev('config', '2026-05-01 10:00:30')]));
    },

    'unknown change time is never expected' => function () use ($ev) {
        assertSame(false, C::isExpected('index.php', 0, [$ev('update', '2026-05-01 10:00:00')]));
        assertSame(false, C::isExpected('index.php', 100, []));
    },

    'severity' => function () {
        assertSame('danger', C::severity('index.php', 'modified', false));
        assertSame('danger', C::severity('administrator/includes/app.php', 'added', false));
        assertSame('warning', C::severity('configuration.php', 'modified', false));
        assertSame('warning', C::severity('.htaccess', 'modified', false));
        assertSame('warning', C::severity('php.ini', 'added', false));
        assertSame('info', C::severity('index.php', 'modified', true));
    },

    'evaluate: nothing recent is ok, old changes drop out' => function () {
        $now = 1_800_000_000;
        assertSame('ok', C::evaluate([], $now)['status']);
        $old = [['path' => 'index.php', 'type' => 'modified', 'expected' => false, 'at' => $now - 15 * 86400]];
        $e   = C::evaluate($old, $now);
        assertSame('ok', $e['status']);
        assertCount(0, $e['active']);
    },

    'evaluate: worst recent change wins and comes first' => function () {
        $now = 1_800_000_000;
        $ch  = [
            ['path' => '.htaccess', 'type' => 'modified', 'expected' => false, 'at' => $now - 100],
            ['path' => 'index.php', 'type' => 'modified', 'expected' => false, 'at' => $now - 5 * 86400],
            ['path' => 'configuration.php', 'type' => 'modified', 'expected' => true, 'at' => $now - 50],
        ];
        $e = C::evaluate($ch, $now);
        assertSame('danger', $e['status']);
        assertSame('index.php', $e['active'][0]['path']);
        assertSame('.htaccess', $e['active'][1]['path']);
        assertSame('info', $e['active'][2]['severity']);
    },

    'evaluate: only expected changes stay information' => function () {
        $now = 1_800_000_000;
        $e   = C::evaluate([['path' => 'index.php', 'type' => 'modified', 'expected' => true, 'at' => $now - 10]], $now);
        assertSame('info', $e['status']);
    },
];

<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\VelMatcher as V;

$items = static function (): array {
    $d = json_decode(file_get_contents(__DIR__ . '/fixtures/vel-sample.json'), true);

    return array_map(static fn ($i) => ['id' => (int) $i['id'], 'title' => $i['title'], 'live' => $i['status'] === '1', 'created' => substr($i['created'], 0, 10)], $d['items']);
};

$hit = static function (V $m, array $names, string $ver): array {
    return array_map(static fn ($h) => $h['id'] . ':' . $h['state'] . ':' . $h['confidence'] . ($h['fix'] !== '' ? ':' . $h['fix'] : ''), $m->match($names, $ver));
};

return [
    'name keys: plain, bracketed element, alternatives, plugin group stripped' => function () {
        assertSame(['phocagallery'], V::parseTitle('Phoca Gallery, 5.0.0,  XSS (Cross Site Scripting)')['keys']);
        assertSame(['easyfileuploader'], V::parseTitle('Easy File Uploader (mod_easyfileuploader), 2.9.6,  Other')['keys']);
        assertSame(['novarain', 'tassosframework'], V::parseTitle('Novarain/Tassos Framework, ,  SQL Injection')['keys']);
        assertSame(['helixultimate'], V::parseTitle('plg_system_helixultimate, ,  XSS (Cross Site Scripting)')['keys']);
        assertSame(['k2'], V::parseTitle('K2,2.7.0,XSS (Cross Site Scripting)')['keys']);
    },
    'version ranges are read from the title' => function () {
        assertSame('5.0.0', V::parseTitle('Phoca Gallery, 5.0.0,  XSS')['last']);
        assertSame('8.7.0', V::parseTitle('acymailing, pre 8.7.0 ,  Other')['patch']);
        assertSame('4.2', V::parseTitle('Payment Form, versions before 4.2')['patch']);
        $p = V::parseTitle('HikaShop, Versions from 4.4.1 to 4.7.2 are affected,  SQL Injection');
        assertSame(['4.4.1', '4.7.2'], [$p['from'], $p['to']]);
        assertSame(['2.1.2', '3.1.2'], V::parseTitle('Visforms pre 2.1.2 and pre 3.1.2')['patches']);
        assertSame('2.4.1', V::parseTitle('Balbooa Forms (com_baforms), All versions including 2.4.1,  Other')['last']);
        assertTrue(V::parseTitle('En Masse, all versions, SQL Injection')['all']);
        assertSame([3], V::parseTitle('Phoca Gallery 3x')['majors']);
    },
    'installed version inside / outside the listed version' => function () use ($items, $hit) {
        $m = new V($items());
        assertSame(['860:affected:likely'], $hit($m, ['com_jce', 'JCE'], '2.9.79'));
        assertSame(['860:affected:likely'], $hit($m, ['com_jce'], '2.9.5'));
        assertSame([], $hit($m, ['com_jce', 'JCE'], '2.9.88'));
        assertSame([], $hit($m, ['com_jdownloads'], '4.2.0'));
    },
    'resolved listing names the version to leave behind' => function () use ($items, $hit) {
        $m = new V($items());
        assertSame(['381:affected:likely:>2.7.0'], $hit($m, ['com_k2', 'K2'], '2.7.0'));
        assertSame([], $hit($m, ['com_k2', 'K2'], '2.11.0'));
    },
    'fixed-in version and per-branch fixes' => function () use ($items, $hit) {
        $m = new V($items());
        assertSame(['804:affected:likely:8.7.0'], $hit($m, ['com_acymailing'], '8.6.0'));
        assertSame([], $hit($m, ['com_acymailing'], '8.8.0'));
        assertSame(['250:affected:likely:3.1.2'], $hit($m, ['com_visforms'], '3.1.0'));
        assertSame([], $hit($m, ['com_visforms'], '3.2.0'));
    },
    'explicit range and open listing without a version' => function () use ($items, $hit) {
        $m = new V($items());
        $r = $hit($m, ['com_hikashop', 'HikaShop'], '4.5.0');
        assertTrue(in_array('785:affected:likely', $r, true), 'inside 4.4.1 - 4.7.2');
        assertTrue(in_array('783:check:likely', $r, true), 'open listing without a version is only "check"');
        assertSame(['783:check:likely'], $hit($m, ['com_hikashop'], '6.1.0'));
    },
    'a branch-only listing concerns that major version alone' => function () use ($items, $hit) {
        $m = new V($items());
        assertSame([], $hit($m, ['com_phocagallery', 'Phoca Gallery'], '6.0.1'));
        assertSame(['813:affected:likely'], $hit($m, ['com_phocagallery'], '5.0.0'));
    },
    'generic names are only possible; element aliases work' => function () use ($items, $hit) {
        $m = new V($items());
        assertSame(['638:affected:possible:>1.7.2'], $hit($m, ['com_forms'], '1.0'));

        // the installed element is nrframework, the list calls the product Novarain/Tassos Framework
        $t = new V([['id' => 1, 'title' => 'Novarain/Tassos Framework, 4.0.0, SQL Injection', 'live' => false, 'created' => '2026-01-01']]);
        assertSame(['1:affected:likely:>4.0.0'], $hit($t, ['plg_system_nrframework'], '3.9.0'));
        assertSame([], $hit($t, ['plg_system_nrframework'], '4.1.0'));
    },
    'unknown installed version is never "affected"' => function () use ($items) {
        $m = new V($items());

        foreach ($m->match(['com_jce'], '-') as $h) {
            assertSame('check', $h['state']);
        }
    },
    'unrelated extensions get nothing' => function () use ($items, $hit) {
        $m = new V($items());
        assertSame([], $hit($m, ['com_content', 'Articles'], '5.2.0'));
        assertSame([], $hit($m, ['com_fgsystemreport', 'FG System Report'], '1.14.0'));
    },
    'every fixture title yields a key and no listing is dropped silently' => function () use ($items) {
        foreach ($items() as $i) {
            assertTrue(V::parseTitle($i['title'])['keys'], 'keys for ' . $i['title']);
        }
    },
];

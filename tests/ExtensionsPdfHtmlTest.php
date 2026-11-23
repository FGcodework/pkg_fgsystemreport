<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\ExtensionsPdfHtml as X;

function ext_row(array $o = []): object
{
    return (object) array_merge([
        'is_package' => false, 'is_child' => false, 'has_update' => false, 'type' => 'plugin',
        'display_name' => 'Plug', 'enabled' => 1, 'installed_version' => '1.0', 'available_version' => '-', 'parent_package' => '',
    ], $o);
}

return [
    'the header row is a thead, so every page of a long list gets it' => function () {
        $h = X::head();
        assertTrue(strpos($h, '<thead>') !== false && strpos($h, '</thead>') !== false);
        assertSame(1, substr_count($h, '<table'));
        assertSame(6, substr_count($h, '<th '));
    },

    'column widths add up to 100 percent in the head and in every row' => function () {
        foreach ([X::head(), X::row(ext_row(), 1)] as $html) {
            preg_match_all('/<t[hd] align="[a-z]+" style="width:(\d+)%/', $html, $m);
            assertSame(6, count($m[1]));
            assertSame(100, array_sum($m[1]));
        }
    },

    'a part of a package has no "in <package>" line under its type, only the » mark' => function () {
        $html = X::row(ext_row(['is_child' => true, 'parent_package' => 'MyPackage', 'display_name' => 'Child']), 7);
        assertSame(false, strpos($html, 'MyPackage'));
        assertSame(false, strpos($html, '<br'));
        assertTrue(strpos($html, '&raquo; Child') !== false);
    },

    'a package is amber and bold, an update is a light red row' => function () {
        $pkg = X::row(ext_row(['is_package' => true, 'type' => 'package', 'display_name' => 'Pk']), 1);
        assertTrue(strpos($pkg, '#fff3cd') !== false && strpos($pkg, '<b>Pk</b>') !== false);
        $upd = X::row(ext_row(['has_update' => true, 'available_version' => '2.0']), 2);
        assertTrue(strpos($upd, '#fdecea') !== false && strpos($upd, '<b><span style="color:#c0392b;">2.0</span></b>') !== false);
        assertSame(false, strpos(X::row(ext_row(), 3), 'background-color'));
    },

    'a disabled extension is red and bold' => function () {
        $off = X::row(ext_row(['enabled' => 0]), 1);
        assertTrue(strpos($off, 'COM_FGSYSTEMREPORT_EXT_DISABLED') !== false);
        assertTrue(strpos($off, '<b><span style="color:#c0392b;">COM_FGSYSTEMREPORT_EXT_DISABLED') !== false);
        assertTrue(strpos(X::row(ext_row(), 1), 'color:#1e7e34') !== false);
    },

    'names and versions are escaped, the row is not split over pages' => function () {
        $html = X::row(ext_row(['display_name' => 'A<script>', 'installed_version' => '1<2']), 1);
        assertSame(false, strpos($html, '<script>'));
        assertTrue(strpos($html, 'A&lt;script&gt;') !== false && strpos($html, '1&lt;2') !== false);
        assertTrue(strpos($html, '<tr nobr="true">') === 0);
    },

    'an unknown extension type falls back to its capitalised name' => function () {
        assertTrue(strpos(X::row(ext_row(['type' => 'sometype']), 1), '>Sometype<') !== false);
    },
];

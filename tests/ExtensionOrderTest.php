<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\ExtensionOrderHelper as O;

function eo(int $id, string $name, string $type = 'plugin', int $pkg = 0): object
{
    return (object) ['extension_id' => $id, 'display_name' => $name, 'type' => $type, 'package_id' => $pkg];
}

function eo_names(array $list): array
{
    return array_map(static function ($e) { return $e->display_name; }, $list);
}

return [
    'sorted by the displayed name, whatever the raw name was' => function () {
        // the database sorts by the raw name (COM_..., plg_...): FG Changelogs came after Button - Maths and before Custom HTML
        $in = [eo(1, 'Phoca Commander', 'component'), eo(2, 'Button - Maths'), eo(3, 'FG Changelogs', 'component'), eo(4, 'Custom HTML advanced', 'module'), eo(5, '4logs', 'package')];
        assertSame(['4logs', 'Button - Maths', 'Custom HTML advanced', 'FG Changelogs', 'Phoca Commander'], eo_names(O::order($in, [5 => '4logs'])));
    },

    'case and accents do not matter' => function () {
        $in = [eo(1, 'zebra'), eo(2, 'Éclair'), eo(3, 'apple'), eo(4, 'Banana'), eo(5, 'čaj')];
        $names = eo_names(O::order($in, []));
        assertSame(['apple', 'Banana', 'čaj', 'Éclair', 'zebra'], $names);
    },

    'numbers are in numerical order' => function () {
        $in = [eo(1, 'Plugin 10'), eo(2, 'Plugin 2'), eo(3, 'Plugin 1')];
        assertSame(['Plugin 1', 'Plugin 2', 'Plugin 10'], eo_names(O::order($in, [])));
    },

    'the parts of a package follow it, in A-Z order' => function () {
        $in = [
            eo(10, 'Zeta', 'component'),
            eo(11, 'System - Part B', 'plugin', 20),
            eo(20, 'My package', 'package'),
            eo(12, 'Admin - Part A', 'plugin', 20),
            eo(13, 'Alpha', 'module'),
        ];
        assertSame(['Alpha', 'My package', 'Admin - Part A', 'System - Part B', 'Zeta'], eo_names(O::order($in, [20 => 'My package'])));
    },

    'a part whose package is not in the list stays on the top level' => function () {
        $in = [eo(1, 'Orphan', 'plugin', 99), eo(2, 'Alpha')];
        assertSame(['Alpha', 'Orphan'], eo_names(O::order($in, [])));
    },

    'equal names keep a fixed order, nothing is lost' => function () {
        $in = [eo(3, 'Same', 'plugin'), eo(1, 'Same', 'module'), eo(2, 'Same', 'component')];
        $out = O::order($in, []);
        assertSame(3, count($out));
        assertSame([2, 1, 3], array_map(static function ($e) { return $e->extension_id; }, $out));
        assertSame([], O::order([], []));
    },
    'the accent fallback (no intl) maps Slovak, Czech and German letters' => function () {
        assertSame('cesky slovencina strasse', O::stripAccents('český slovenčina straße'));
        assertSame('zlty kon', O::stripAccents('žltý koň'));
        assertSame('plain', O::stripAccents('plain'));
    },
];

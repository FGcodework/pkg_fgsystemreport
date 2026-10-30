<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\GrowthAlertHelper as G;

const MB = 1048576;

return [
    'absolute limit triggers' => function () {
        $r = G::detect(['a' => 300 * MB], ['a' => 100 * MB], 100, 0);
        assertCount(1, $r);
        assertSame(200 * MB, $r[0]['delta']);
    },
    'below both limits = no alert' => function () {
        assertSame([], G::detect(['a' => 105 * MB], ['a' => 100 * MB], 100, 25));
    },
    'percent limit triggers' => function () {
        assertCount(1, G::detect(['a' => 60 * MB], ['a' => 40 * MB], 0, 25));
    },
    'shrinking never alerts' => function () {
        assertSame([], G::detect(['a' => 1 * MB], ['a' => 500 * MB], 1, 1));
    },
    'new item ignored when newFrom is null' => function () {
        assertSame([], G::detect(['n' => 900 * MB], [], 100, 0, null));
    },
    'new item judged against newFrom' => function () {
        assertCount(1, G::detect(['n' => 900 * MB], ['x' => 1], 100, 0, 10 * MB));
        assertSame([], G::detect(['n' => 50 * MB], ['x' => 1], 100, 0, 10 * MB));
    },
    'sorted by growth, biggest first' => function () {
        $r = G::detect(['a' => 300 * MB, 'b' => 900 * MB], ['a' => 100 * MB, 'b' => 100 * MB], 100, 0);
        assertSame('b', $r[0]['path']);
    },
    'total growth' => function () {
        assertCount(1, G::detectTotal(1000 * MB, 700 * MB, 100, 0));
        assertSame([], G::detectTotal(700 * MB, 1000 * MB, 100, 0));
        assertSame([], G::detectTotal(5 * MB, 0, 1, 1));
    },
];

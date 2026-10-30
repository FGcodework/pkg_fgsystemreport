<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\HistoryHelper as H;

$run = static fn (array $rows) => (object) ['decoded' => $rows];

return [
    'matches by key across languages' => function () use ($run) {
        $prev = $run([['Verzia PHP', 'ok', 'PHP'], ['SSL', 'warning', 'SSL'], ['Stary', 'danger', 'OLD']]);
        $cur  = $run([['PHP version', 'warning', 'PHP'], ['SSL', 'ok', 'SSL'], ['New', 'warning', 'NEW']]);
        $r    = H::compare($cur, $prev);
        assertSame([['PHP version', 'ok', 'warning']], $r['worse']);
        assertSame([['SSL', 'warning', 'ok']], $r['better']);
        assertSame([['New', 'warning']], $r['added']);
        assertSame([['Stary', 'danger']], $r['removed']);
    },
    'legacy rows (no key) match by label' => function () use ($run) {
        $r = H::compare($run([['A', 'danger'], ['B', 'warning']]), $run([['A', 'ok'], ['B', 'warning']]));
        assertSame([['A', 'ok', 'danger']], $r['worse']);
        assertSame([], $r['better']);
    },
    'legacy rows in different languages fall back to position' => function () use ($run) {
        $r = H::compare($run([['Version', 'warning'], ['SSL!', 'ok']]), $run([['Verzia', 'ok'], ['SSL', 'ok']]));
        assertSame([['Version', 'ok', 'warning']], $r['worse']);
    },
    'irregular legacy indexes do not raise warnings' => function () use ($run) {
        $r = H::compare($run([5 => ['A', 'ok'], 9 => ['B', 'ok']]), $run([['X', 'ok']]));
        assertTrue(is_array($r));
    },
    'no previous run = nothing to report' => function () use ($run) {
        assertSame(['worse' => [], 'better' => [], 'added' => [], 'removed' => []], H::compare($run([['A', 'ok', 'A']]), null));
    },
];

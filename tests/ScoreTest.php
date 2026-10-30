<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper as H;

$c = static fn (string $status, string $note = 'n') => (object) ['label' => 'L', 'status' => $status, 'note' => $note];

return [
    'no findings = 100' => function () use ($c) {
        assertSame(100, H::calculateSecurityScore([$c('ok'), $c('info')])->score);
    },
    'danger -15, warning -5' => function () use ($c) {
        $s = H::calculateSecurityScore([$c('danger'), $c('warning'), $c('warning'), $c('ok')]);
        assertSame(75, $s->score);
        assertSame(1, $s->dangerCount);
        assertSame(2, $s->warningCount);
    },
    'floor at 0' => function () use ($c) {
        assertSame(0, H::calculateSecurityScore(array_fill(0, 10, $c('danger')))->score);
    },
    'top recommendations: danger first, max 3, only with a note' => function () use ($c) {
        $s = H::calculateSecurityScore([$c('warning'), $c('danger', ''), $c('danger'), $c('warning'), $c('warning'), $c('warning')]);
        assertCount(3, $s->topRecommendations);
        assertSame('danger', $s->topRecommendations[0]->status);
    },
];

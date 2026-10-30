<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\AcceptedRiskHelper as A;

/** Pre-fill the helper's in-memory cache so no database is needed. */
$withAccepted = static function (array $rows): void {
    $p = new ReflectionProperty(A::class, 'cache');
    $p->setAccessible(true);
    $p->setValue(null, $rows);
};

$check = static fn (string $key, string $status) => (object) ['key' => $key, 'label' => $key, 'status' => $status, 'value' => 'v', 'note' => 'orig'];

return [
    'key validation' => function () {
        assertTrue(A::isValidKey('COM_FGSYSTEMREPORT_SEC_PHP_VERSION'));
        assertSame(false, A::isValidKey('COM_FGSYSTEMREPORT_SEC_'));
        assertSame(false, A::isValidKey('SOMETHING_ELSE'));
        assertSame(false, A::isValidKey("COM_FGSYSTEMREPORT_SEC_X'; DROP TABLE x;--"));
    },
    'accepted warning/danger becomes info and keeps the original' => function () use ($withAccepted, $check) {
        $withAccepted(['COM_FGSYSTEMREPORT_SEC_A' => (object) ['note' => 'ok for us', 'user_name' => 'fero', 'created_at' => '2026-01-01 00:00:00']]);
        [$w, $d] = A::apply([$check('COM_FGSYSTEMREPORT_SEC_A', 'warning'), $check('COM_FGSYSTEMREPORT_SEC_A', 'danger')]);
        assertSame('info', $w->status);
        assertSame('warning', $w->orig_status);
        assertSame('danger', $d->orig_status);
        assertTrue($w->accepted);
        assertTrue(strpos($w->note, 'ok for us') !== false);
    },
    'ok/info findings and other keys are untouched' => function () use ($withAccepted, $check) {
        $withAccepted(['COM_FGSYSTEMREPORT_SEC_A' => (object) ['note' => '', 'user_name' => '', 'created_at' => '']]);
        [$ok, $other] = A::apply([$check('COM_FGSYSTEMREPORT_SEC_A', 'ok'), $check('COM_FGSYSTEMREPORT_SEC_B', 'danger')]);
        assertSame('ok', $ok->status);
        assertSame('danger', $other->status);
        assertTrue(!isset($other->accepted));
    },
];

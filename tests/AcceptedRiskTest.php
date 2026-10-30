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
    'acceptance with a future expiry still applies and shows the date' => function () use ($withAccepted, $check) {
        $until = gmdate('Y-m-d H:i:s', time() + 5 * 86400);
        $withAccepted(['COM_FGSYSTEMREPORT_SEC_A' => (object) ['note' => '', 'user_name' => 'u', 'created_at' => '2026-01-01 00:00:00', 'expires_at' => $until]]);
        [$c] = A::apply([$check('COM_FGSYSTEMREPORT_SEC_A', 'warning')]);
        assertSame('info', $c->status);
        assertTrue(strpos($c->note, substr($until, 0, 10)) !== false, 'note shows the date');
    },
    'an expired acceptance no longer applies' => function () use ($withAccepted, $check) {
        $past = gmdate('Y-m-d H:i:s', time() - 86400);
        $withAccepted(['COM_FGSYSTEMREPORT_SEC_A' => (object) ['note' => 'x', 'user_name' => 'u', 'created_at' => '2026-01-01 00:00:00', 'expires_at' => $past]]);
        [$c] = A::apply([$check('COM_FGSYSTEMREPORT_SEC_A', 'danger')]);
        assertSame('danger', $c->status);
        assertTrue($c->accept_expired);
        assertTrue(!isset($c->accepted));
    },
    'no expiry (null / empty) means it never expires' => function () use ($withAccepted, $check) {
        $withAccepted(['COM_FGSYSTEMREPORT_SEC_A' => (object) ['note' => '', 'user_name' => '', 'created_at' => '', 'expires_at' => null]]);
        [$c] = A::apply([$check('COM_FGSYSTEMREPORT_SEC_A', 'warning')]);
        assertSame('info', $c->status);
    },
    'ok/info findings and other keys are untouched' => function () use ($withAccepted, $check) {
        $withAccepted(['COM_FGSYSTEMREPORT_SEC_A' => (object) ['note' => '', 'user_name' => '', 'created_at' => '']]);
        [$ok, $other] = A::apply([$check('COM_FGSYSTEMREPORT_SEC_A', 'ok'), $check('COM_FGSYSTEMREPORT_SEC_B', 'danger')]);
        assertSame('ok', $ok->status);
        assertSame('danger', $other->status);
        assertTrue(!isset($other->accepted));
    },
];

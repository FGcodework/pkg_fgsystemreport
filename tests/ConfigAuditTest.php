<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\ConfigAuditHelper as C;

return [
    'registration: off, privileged group, captcha, activation, ok' => function () {
        assertSame(['ok', 'off'], C::evaluateRegistration(false, true, false, 0));
        assertSame(['danger', 'admin'], C::evaluateRegistration(true, true, true, 1));
        assertSame(['warning', 'captcha'], C::evaluateRegistration(true, false, false, 1));
        assertSame(['warning', 'activation'], C::evaluateRegistration(true, false, true, 0));
        assertSame(['ok', 'ok'], C::evaluateRegistration(true, false, true, 2));
    },
    'password length' => function () {
        assertSame('warning', C::evaluatePasswordLength(8));
        assertSame('ok', C::evaluatePasswordLength(12));
        assertSame('ok', C::evaluatePasswordLength(16));
    },
    'dangerous upload types are found across lists, case and dots ignored' => function () {
        assertSame([], C::dangerousUploadExtensions(['bmp,gif,jpg,png', 'pdf, txt']));
        assertSame(['php', 'phar'], C::dangerousUploadExtensions(['bmp, PHP ,gif', '.phar,pdf', 'php']));
        assertSame([], C::dangerousUploadExtensions(['', 'not-a-list']));
    },
    'text filters: only unprivileged groups with No Filtering count' => function () {
        $filters = (object) [
            '1' => (object) ['filter_type' => 'BL'],
            '2' => (object) ['filter_type' => 'NONE'],
            '8' => (object) ['filter_type' => 'NONE'],
            '6' => ['filter_type' => 'NH'],
        ];
        assertSame([2], C::openTextFilterGroups($filters, static fn (int $g) => $g === 8));
        assertSame([], C::openTextFilterGroups(null, static fn (int $g) => false));
    },
    'guessable Super User names' => function () {
        assertSame(['Admin', 'root'], C::guessableNames(['Admin', 'fero', 'root']));
        assertSame([], C::guessableNames(['fero.golias']));
    },
    'update channel' => function () {
        foreach (['default', 'lts', 'sts', 'nochange', ''] as $s) {
            assertSame(false, C::isUnusualUpdateChannel($s), $s);
        }

        assertTrue(C::isUnusualUpdateChannel('testing'));
        assertTrue(C::isUnusualUpdateChannel('custom'));
    },
];

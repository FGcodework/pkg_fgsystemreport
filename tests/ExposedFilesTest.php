<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper as H;

$sig = (new ReflectionClassConstant(H::class, 'PHP_INI_SIGNATURE'))->getValue();

return [
    'php.ini signature matches a real php.ini / .user.ini body' => function () use ($sig) {
        assertSame(1, preg_match($sig, "[PHP]\n;;;;\n; About php.ini ;"));
        assertSame(1, preg_match($sig, "memory_limit = 256M\nupload_max_filesize = 64M"));
        assertSame(1, preg_match($sig, "Hello\nerror_log = /tmp/x"));
    },
    'php.ini signature ignores ordinary pages' => function () use ($sig) {
        assertSame(0, preg_match($sig, '<html><body>Page not found</body></html>'));
        assertSame(0, preg_match($sig, '<title>Home</title> memory_limit is a PHP setting'));
    },
];

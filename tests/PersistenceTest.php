<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\ExtraChecksHelper as E;
use Fg\Component\Fgsystemreport\Administrator\Helper\ChangeLogHelper as C;

$issues = static fn (string $name, string $body): array => array_column(E::inspectConfigFile($name, $body), 'issue');

return [
    'clean Joomla .htaccess gives no findings' => function () use ($issues) {
        $body = "RewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n<IfModule mod_headers.c>\nHeader set X-Foo bar\n</IfModule>\n";
        assertSame([], $issues('.htaccess', $body));
    },
    'auto_prepend_file in .htaccess and .user.ini is found' => function () use ($issues) {
        assertSame(['prepend'], $issues('.htaccess', "php_value auto_prepend_file /var/www/x.php\n"));
        assertSame(['append'], $issues('.user.ini', "auto_append_file = \"/tmp/.a.php\"\n"));
        assertSame(['prepend'], $issues('php.ini', "[PHP]\nauto_prepend_file=evil.php\n"));
    },
    'empty / none / commented directives are ignored' => function () use ($issues) {
        assertSame([], $issues('.user.ini', "auto_prepend_file =\n"));
        assertSame([], $issues('.user.ini', "auto_prepend_file = none\n"));
        assertSame([], $issues('.user.ini', "; auto_prepend_file = x.php\n"));
        assertSame([], $issues('.htaccess', "# php_value auto_prepend_file x.php\n"));
    },
    'plain ini line is not read as a directive in .htaccess' => function () use ($issues) {
        assertSame([], $issues('.htaccess', "auto_prepend_file = x.php\n"));
    },
    'php handler on images / text is critical' => function () use ($issues) {
        assertSame(['handler_media'], $issues('.htaccess', "AddType application/x-httpd-php .jpg .png\n"));
        assertSame(['handler_media'], $issues('.htaccess', "AddHandler application/x-httpd-php .txt\n"));
        assertSame(['handler_media'], $issues('.htaccess', "<FilesMatch \"\\.(jpg|gif)$\">\nSetHandler application/x-httpd-php\n</FilesMatch>\n"));
    },
    'ordinary php handler is only a plain handler finding' => function () use ($issues) {
        assertSame(['handler_php'], $issues('.htaccess', "AddHandler application/x-httpd-php82 .php .php5\n"));
        assertSame(['handler_php'], $issues('.htaccess', "<FilesMatch \"\\.php$\">\nSetHandler application/x-httpd-php\n</FilesMatch>\n"));
    },
    'non-php handlers are ignored' => function () use ($issues) {
        assertSame([], $issues('.htaccess', "AddType image/svg+xml .svg\nAddHandler cgi-script .cgi\n"));
    },
    'scope ends with the closing tag' => function () use ($issues) {
        $body = "<FilesMatch \"\\.jpg$\">\nHeader set Cache-Control max-age=1\n</FilesMatch>\nSetHandler application/x-httpd-php\n";
        assertSame(['handler_php'], $issues('.htaccess', $body));
    },
    'security headers: all present' => function () {
        assertSame([], E::missingSecurityHeaders([
            'x-content-type-options' => 'nosniff', 'x-frame-options' => 'SAMEORIGIN', 'referrer-policy' => 'strict-origin',
        ]));
    },
    'security headers: CSP frame-ancestors replaces X-Frame-Options' => function () {
        assertSame([], E::missingSecurityHeaders([
            'x-content-type-options' => 'NoSniff', 'content-security-policy' => "default-src 'self'; frame-ancestors 'none'", 'referrer-policy' => 'no-referrer',
        ]));
    },
    'security headers: missing ones are named' => function () {
        assertSame(
            ['X-Content-Type-Options', 'X-Frame-Options / CSP frame-ancestors', 'Referrer-Policy'],
            E::missingSecurityHeaders([])
        );
        assertSame(['Referrer-Policy'], E::missingSecurityHeaders(['x-content-type-options' => 'nosniff', 'x-frame-options' => 'DENY']));
        assertSame(['X-Content-Type-Options', 'X-Frame-Options / CSP frame-ancestors', 'Referrer-Policy'],
            E::missingSecurityHeaders(['x-content-type-options' => 'sniff', 'content-security-policy' => "default-src 'self'"]));
    },
    'event detail codes fall back to the stored text' => function () {
        assertSame('v1.2.3', C::detailLabel('v1.2.3'));
        assertSame('debug: 0 > 1', C::detailLabel('debug: 0 > 1'));
        assertSame('su_created', C::detailLabel('su_created')); // stub Text returns the key -> raw code
    },
];

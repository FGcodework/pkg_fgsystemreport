<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper as H;

return [
    'Joomla! Project manifests count as core' => function () {
        assertTrue(H::isCoreExtension('{"author":"Joomla! Project","version":"5.2.0"}'));
    },
    'protected extensions count as core' => function () {
        assertTrue(H::isCoreExtension('', 1));
    },
    'third-party and broken manifests do not' => function () {
        assertSame(false, H::isCoreExtension('{"author":"Fero","version":"1.0.0"}'));
        assertSame(false, H::isCoreExtension('{"version":"1.0.0"}'));
        assertSame(false, H::isCoreExtension('not json'));
        assertSame(false, H::isCoreExtension(''));
    },
];

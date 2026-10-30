<?php
namespace {
/**
 * Minimal bootstrap: lets the pure-logic parts of the component run without a
 * Joomla installation. Only what those classes touch is stubbed.
 */

define('_JEXEC', 1);
define('JPATH_ROOT', sys_get_temp_dir());
define('JPATH_ADMINISTRATOR', sys_get_temp_dir());
define('JPATH_SITE', sys_get_temp_dir());
define('JVERSION', '5.2.0');

// PSR-4 for the component classes
spl_autoload_register(static function (string $class): void {
    $prefix = 'Fg\\Component\\Fgsystemreport\\Administrator\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = __DIR__ . '/../com_fgsystemreport/administrator/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
}

namespace Joomla\CMS\Language {
/** Returns the key (and the arguments) so tests can assert on it. */
class Text
{
    public static function _($key)
    {
        return (string) $key;
    }

    public static function sprintf($key, ...$args)
    {
        return $key . ($args ? '(' . implode(',', $args) . ')' : '');
    }
}
}

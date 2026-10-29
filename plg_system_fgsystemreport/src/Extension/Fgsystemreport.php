<?php
/**
 * @package    Plg_system_fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Fgsystemreport\Extension;

\defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\ChangeLogHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin: records changes to the site (extension installs, updates,
 * removals, enable/disable, key Global Configuration settings) into the FG
 * System Report change timeline. It does nothing on ordinary page requests:
 * every handler only runs while an administrator changes something.
 *
 * @since  1.0.0
 */
final class Fgsystemreport extends CMSPlugin implements SubscriberInterface
{
    /** Global Configuration keys whose change is worth a timeline entry (no secrets). */
    private const WATCHED = [
        'debug', 'error_reporting', 'caching', 'cache_handler', 'offline', 'mailer', 'force_ssl',
        'sef', 'sef_rewrite', 'gzip', 'session_handler', 'live_site', 'tmp_path', 'log_path', 'captcha',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onExtensionAfterInstall'   => 'onInstall',
            'onExtensionAfterUninstall' => 'onUninstall',
            'onExtensionChangeState'    => 'onChangeState',
            'onContentChangeState'      => 'onChangeState',
            'onApplicationBeforeSave'   => 'onConfigSave',
        ];
    }

    /** Event arguments as a list: same for numbered (Joomla 4) and named (5/6) events. */
    private static function args(Event $event): array
    {
        return array_values($event->getArguments());
    }

    private static function ready(): bool
    {
        if (class_exists(ChangeLogHelper::class)) {
            return true;
        }

        @file_put_contents(
            JPATH_ADMINISTRATOR . '/logs/fgsystemreport_events.log',
            gmdate('Y-m-d H:i:s') . ' ready: component helper class not found (is com_fgsystemreport installed?)' . "\n",
            FILE_APPEND
        );

        return false;
    }

    /** Handlers never break the admin, but the reason is kept for the History page. */
    private static function fail(string $where, \Throwable $e): void
    {
        if (self::ready()) {
            ChangeLogHelper::logError($where, $e);
        }
    }

    /** Short technical label of an extensions row, e.g. plg_system_foo. */
    private static function label(object $row): string
    {
        $el = (string) $row->element;

        switch ((string) $row->type) {
            case 'plugin':
                return 'plg_' . $row->folder . '_' . $el;
            case 'module':
                return $el;
            case 'component':
                return $el;
            case 'template':
                return 'tpl_' . $el;
            case 'library':
                return 'lib_' . $el;
            case 'package':
                return $el;
            default:
                return $row->type . ': ' . $el;
        }
    }

    private static function rows(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $q  = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'type', 'element', 'folder', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->whereIn($db->quoteName('extension_id'), $ids);

        return (array) $db->setQuery($q)->loadObjectList();
    }

    private static function version(object $row): string
    {
        $m = json_decode((string) $row->manifest_cache, true);

        return is_array($m) && !empty($m['version']) ? 'v' . $m['version'] : '';
    }

    public function onInstall(Event $event): void
    {
        try {
            if (!self::ready()) {
                return;
            }

            $a         = self::args($event);
            $installer = $a[0] ?? null;
            $eid       = (int) ($a[1] ?? 0);
            $route     = is_object($installer) && method_exists($installer, 'getRoute') ? (string) $installer->getRoute() : 'install';
            $type      = $route === 'update' ? 'update' : 'install';

            foreach (self::rows([$eid]) as $row) {
                ChangeLogHelper::record($type, self::label($row), self::version($row));
            }
        } catch (\Throwable $e) {
            self::fail('onInstall', $e);
        }
    }

    public function onUninstall(Event $event): void
    {
        try {
            if (!self::ready()) {
                return;
            }

            $a         = self::args($event);
            $installer = $a[0] ?? null;
            $result    = $a[2] ?? true;

            if (!$result) {
                return;
            }

            $name = '';
            $kind = '';

            if (is_object($installer) && isset($installer->manifest) && $installer->manifest instanceof \SimpleXMLElement) {
                $name = Text::_((string) $installer->manifest->name);
                $kind = (string) $installer->manifest['type'];
            }

            if ($name === '') {
                $name = 'extension #' . (int) ($a[1] ?? 0);
            }

            ChangeLogHelper::record('uninstall', $name, $kind);
        } catch (\Throwable $e) {
            self::fail('onUninstall', $e);
        }
    }

    public function onChangeState(Event $event): void
    {
        try {
            if (!self::ready()) {
                return;
            }

            $context = '';
            $pks     = [];
            $value   = null;

            foreach (self::args($event) as $arg) {
                if (is_string($arg) && $context === '') {
                    $context = $arg;
                } elseif (is_array($arg) && !$pks) {
                    $pks = $arg;
                } elseif (is_numeric($arg) && $value === null) {
                    $value = (int) $arg;
                }
            }

            // Only the extension managers: other contexts use other id spaces
            if ((strpos($context, 'com_installer') !== 0 && strpos($context, 'com_plugins') !== 0) || $value === null) {
                return;
            }

            foreach (self::rows($pks) as $row) {
                ChangeLogHelper::record($value === 1 ? 'enable' : 'disable', self::label($row), self::version($row));
            }
        } catch (\Throwable $e) {
            self::fail('onChangeState', $e);
        }
    }

    public function onConfigSave(Event $event): void
    {
        try {
            if (!self::ready()) {
                return;
            }

            $new = null;

            foreach (self::args($event) as $arg) {
                if ($arg instanceof \Joomla\Registry\Registry) {
                    $arg = $arg->toArray();
                } elseif (is_object($arg)) {
                    $arg = get_object_vars($arg);
                }

                if (is_array($arg) && array_intersect(self::WATCHED, array_keys($arg))) {
                    $new = $arg;
                    break;
                }
            }

            if ($new === null) {
                return;
            }

            $app     = Factory::getApplication();
            $changes = [];

            foreach (self::WATCHED as $key) {
                if (!array_key_exists($key, $new)) {
                    continue;
                }

                $old = (string) $app->get($key, '');
                $val = is_scalar($new[$key]) ? (string) $new[$key] : '';

                if ($old !== $val) {
                    $changes[] = $key . ': ' . ($old === '' ? '-' : $old) . ' > ' . ($val === '' ? '-' : $val);
                }
            }

            if ($changes) {
                ChangeLogHelper::record('config', 'Global Configuration', implode('; ', $changes));
            }
        } catch (\Throwable $e) {
            self::fail('onConfigSave', $e);
        }
    }
}

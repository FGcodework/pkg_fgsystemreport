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
            'onAfterRoute'              => 'onAfterRoute',
            'onApplicationBeforeSave'   => 'onConfigSave',
            'onUserBeforeSave'          => 'onUserBeforeSave',
            'onUserAfterSave'           => 'onUserAfterSave',
        ];
    }

    /** @var array<int,int[]> group ids a user had before the current save (user id => groups) */
    private static array $groupsBefore = [];

    /** Event arguments as a list: same for numbered (Joomla 4) and named (5/6) events. */
    private static function args(Event $event): array
    {
        return array_values($event->getArguments());
    }

    /**
     * Comparable string for a configuration value: configuration.php holds
     * real booleans (false) while the form posts "0" / "1", so both must
     * give the same string or an unchanged setting would be logged.
     */
    private static function normalise($v): string
    {
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        if (!is_scalar($v)) {
            return '';
        }

        $s = trim((string) $v);

        return match (strtolower($s)) {
            'true'  => '1',
            'false' => '0',
            default => $s,
        };
    }

    /** Store a timeline event and drop the cached Security Check (the change may alter its findings). */
    private static function record(string $type, string $subject, string $detail = ''): void
    {
        ChangeLogHelper::record($type, $subject, $detail);

        static $flushed = false;

        if (!$flushed && class_exists(\Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper::class)) {
            $flushed = true;
            \Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper::flushSecurityCache();
        }
    }

    private static function ready(): bool
    {
        if (class_exists(ChangeLogHelper::class)) {
            return true;
        }

        // Honour the configured log folder (Global Configuration > log_path)
        $dir = rtrim((string) Factory::getApplication()->get('log_path', ''), '/\\') ?: JPATH_ADMINISTRATOR . '/logs';

        @file_put_contents(
            $dir . '/fgsystemreport_events.log',
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
                self::record($type, self::label($row), self::version($row));
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

            $manifest = is_object($installer) ? ($installer->manifest ?? null) : null;

            if (!$manifest instanceof \SimpleXMLElement && is_object($installer) && method_exists($installer, 'getManifest')) {
                try {
                    $manifest = $installer->getManifest();
                } catch (\Throwable $e) {
                    $manifest = null;
                }
            }

            if ($manifest instanceof \SimpleXMLElement) {
                $name = Text::_((string) $manifest->name);
                $kind = (string) $manifest['type'];
            }

            if ($name === '') {
                $name = 'extension #' . (int) ($a[1] ?? 0);
            }

            self::record('uninstall', $name, $kind);
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
                self::record($value === 1 ? 'enable' : 'disable', self::label($row), self::version($row));
            }
        } catch (\Throwable $e) {
            self::fail('onChangeState', $e);
        }
    }

    /**
     * Fallback for enable/disable from the Plugins / Extensions managers: the
     * state-change events differ between Joomla versions, so the toggle
     * request itself (task plugins.publish / unpublish, manage.publish /
     * unpublish with the selected ids) is recognised as well. Identical events
     * within a minute are stored once, so a double detection is harmless.
     */
    public function onAfterRoute(Event $event): void
    {
        try {
            $app = $this->getApplication();

            if (!$app || !$app->isClient('administrator') || $app->getInput()->getMethod() !== 'POST') {
                return;
            }

            $input  = $app->getInput();
            $option = $input->getCmd('option', '');
            $task   = strtolower($input->getCmd('task', ''));
            $map    = [
                'com_plugins'   => ['plugins.publish' => 1, 'plugins.unpublish' => 0],
                'com_installer' => ['manage.publish' => 1, 'manage.unpublish' => 0],
            ];

            if (!isset($map[$option][$task]) || !self::ready()) {
                return;
            }

            $identity = $app->getIdentity();

            if (!$identity || !$identity->authorise('core.edit.state', $option)) {
                return;
            }

            $ids = (array) $input->post->get('cid', [], 'array');

            foreach (self::rows($ids) as $row) {
                self::record($map[$option][$task] === 1 ? 'enable' : 'disable', self::label($row), self::version($row));
            }
        } catch (\Throwable $e) {
            self::fail('onAfterRoute', $e);
        }
    }

    /** @return int[] group ids of a user, straight from the map table */
    private static function userGroups(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $q  = $db->getQuery(true)
            ->select($db->quoteName('group_id'))
            ->from($db->quoteName('#__user_usergroup_map'))
            ->where($db->quoteName('user_id') . ' = ' . $userId);

        return array_map('intval', (array) $db->setQuery($q)->loadColumn());
    }

    /** Is any of these groups allowed core.admin (i.e. effectively a Super User group)? */
    private static function hasSuperGroup(array $groups): bool
    {
        return (bool) array_intersect($groups, \Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper::getSuperUserGroupIds());
    }

    /** Remember the groups before a user is saved, to tell "created" from "promoted" afterwards. */
    public function onUserBeforeSave(Event $event): void
    {
        try {
            $a  = self::args($event);
            $id = (int) ((is_array($a[0] ?? null) ? ($a[0]['id'] ?? 0) : 0));

            if ($id > 0) {
                self::$groupsBefore[$id] = self::userGroups($id);
            }
        } catch (\Throwable $e) {
            // never break saving a user
        }
    }

    /**
     * Timeline entry when a Super User is created or an existing account
     * is moved into a group that has core.admin. Plain edits of accounts that
     * already were Super Users are not logged.
     */
    public function onUserAfterSave(Event $event): void
    {
        try {
            $a = self::args($event);

            if (empty($a[2]) || !is_array($a[0] ?? null)) {
                return; // save failed
            }

            if (!self::ready() || !class_exists(\Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper::class)) {
                return;
            }

            $id = (int) ($a[0]['id'] ?? 0);

            if ($id <= 0) {
                return;
            }

            $wasSuper = self::hasSuperGroup(self::$groupsBefore[$id] ?? []);
            unset(self::$groupsBefore[$id]);

            if ($wasSuper || !self::hasSuperGroup(self::userGroups($id))) {
                return;
            }

            $name = (string) ($a[0]['username'] ?? ('#' . $id));

            self::record('user', $name, !empty($a[1]) ? 'su_created' : 'su_promoted');
        } catch (\Throwable $e) {
            self::fail('onUserAfterSave', $e);
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

                $old = self::normalise($app->get($key, ''));
                $val = self::normalise($new[$key]);

                if ($old !== $val) {
                    $changes[] = $key . ': ' . ($old === '' ? '-' : $old) . ' > ' . ($val === '' ? '-' : $val);
                }
            }

            if ($changes) {
                self::record('config', 'Global Configuration', implode('; ', $changes));
            }
        } catch (\Throwable $e) {
            self::fail('onConfigSave', $e);
        }
    }
}

<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Settings of Joomla itself that make a site easier to attack: open
 * registration into a privileged group, short passwords, risky upload types,
 * unfiltered HTML for front-end groups, guessable administrator names, no
 * action log, a non-standard update channel.
 *
 * The evaluate*() / *Names() functions are pure (tests/ConfigAuditTest.php);
 * collect() reads the settings and turns them into Security Check rows.
 *
 * @since  1.14.0
 */
final class ConfigAuditHelper
{
    /** File types that must never be uploadable through the media manager. */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'shtml',
        'cgi', 'pl', 'py', 'exe', 'sh', 'htaccess', 'jsp', 'asp', 'aspx',
    ];

    /** Super User names every scanner tries first. */
    public const GUESSABLE_NAMES = ['admin', 'administrator', 'root', 'superuser', 'super', 'joomla', 'webmaster'];

    /** Minimum password length recommended for Joomla 4+ (its own default). */
    public const MIN_PASSWORD_LENGTH = 12;

    /**
     * Registration.
     *
     * @return  array{0:string,1:string}  [status, code]  code: off | admin | captcha | activation | ok
     */
    public static function evaluateRegistration(bool $enabled, bool $privilegedGroup, bool $captcha, int $activation): array
    {
        if (!$enabled) {
            return ['ok', 'off'];
        }

        if ($privilegedGroup) {
            return ['danger', 'admin'];
        }

        if (!$captcha) {
            return ['warning', 'captcha'];
        }

        return $activation === 0 ? ['warning', 'activation'] : ['ok', 'ok'];
    }

    /** Status for the configured minimum password length. */
    public static function evaluatePasswordLength(int $length): string
    {
        return $length < self::MIN_PASSWORD_LENGTH ? 'warning' : 'ok';
    }

    /**
     * Risky extensions found in comma separated lists of allowed upload types.
     *
     * @param   string[]  $lists  e.g. "bmp,gif,php"
     *
     * @return  string[]
     */
    public static function dangerousUploadExtensions(array $lists): array
    {
        $found = [];

        foreach ($lists as $list) {
            foreach (explode(',', strtolower((string) $list)) as $ext) {
                $ext = ltrim(trim($ext), '.');

                if ($ext !== '' && \in_array($ext, self::DANGEROUS_EXTENSIONS, true)) {
                    $found[$ext] = $ext;
                }
            }
        }

        return array_values($found);
    }

    /**
     * Groups that may save HTML unfiltered although they are not administrators.
     *
     * @param   array|object  $filters          Global Configuration "filters": group id => settings with filter_type
     * @param   callable      $isPrivileged     fn(int $groupId): bool  true when the group may log in to the back end / is Super User
     *
     * @return  int[]
     */
    public static function openTextFilterGroups($filters, callable $isPrivileged): array
    {
        $open = [];

        foreach ((array) $filters as $group => $filter) {
            $type = \is_object($filter) ? ($filter->filter_type ?? '') : ($filter['filter_type'] ?? '');

            if ($type === 'NONE' && !$isPrivileged((int) $group)) {
                $open[] = (int) $group;
            }
        }

        return $open;
    }

    /**
     * @param   string[]  $usernames
     *
     * @return  string[]  the guessable ones
     */
    public static function guessableNames(array $usernames): array
    {
        return array_values(array_filter($usernames, static fn ($n) => \in_array(strtolower((string) $n), self::GUESSABLE_NAMES, true)));
    }

    /** Is the Joomla update channel something other than the default / long-term-support choices? */
    public static function isUnusualUpdateChannel(string $source): bool
    {
        return !\in_array($source, ['', 'default', 'lts', 'sts', 'nochange'], true);
    }

    /**
     * Read the settings and build the rows.
     *
     * @param   array  $superUsers  Super User rows of the Security Check (username, block)
     *
     * @return  list<array{key:string,status:string,value:string,note:string}>
     */
    public static function collect(array $superUsers): array
    {
        $rows = [];
        $row  = static function (string $key, string $status, string $value, string $note = '') use (&$rows): void {
            $rows[] = ['key' => $key, 'status' => $status, 'value' => $value, 'note' => $note];
        };
        $privileged = static fn (int $group): bool => Access::checkGroup($group, 'core.login.admin') || Access::checkGroup($group, 'core.admin');

        // Registration
        try {
            $p      = ComponentHelper::getParams('com_users');
            $app    = Factory::getApplication();
            $global = (string) $app->get('captcha', '0');
            $cap    = (string) $p->get('captcha', '');
            $cap    = $cap !== '' ? $cap : $global;

            [$status, $code] = self::evaluateRegistration(
                (int) $p->get('allowUserRegistration', 0) === 1,
                $privileged((int) $p->get('new_usertype', 2)),
                $cap !== '' && $cap !== '0',
                (int) $p->get('useractivation', 1)
            );

            $note = ['admin' => 'COM_FGSYSTEMREPORT_SEC_REGISTRATION_ADMIN', 'captcha' => 'COM_FGSYSTEMREPORT_SEC_REGISTRATION_CAPTCHA', 'activation' => 'COM_FGSYSTEMREPORT_SEC_REGISTRATION_ACTIVATION'][$code] ?? '';
            $row('COM_FGSYSTEMREPORT_SEC_REGISTRATION', $status, Text::_($code === 'off' ? 'JNO' : 'JYES'), $note !== '' ? Text::_($note) : '');
        } catch (\Throwable $e) {
            // not reported when the setting cannot be read
        }

        // Password length
        try {
            $len = (int) ComponentHelper::getParams('com_users')->get('minimum_length', self::MIN_PASSWORD_LENGTH);
            $st  = self::evaluatePasswordLength($len);
            $row('COM_FGSYSTEMREPORT_SEC_PASSWORD_LENGTH', $st, (string) $len,
                $st === 'ok' ? '' : Text::sprintf('COM_FGSYSTEMREPORT_SEC_PASSWORD_LENGTH_SHORT', self::MIN_PASSWORD_LENGTH));
        } catch (\Throwable $e) {
        }

        // Uploads
        try {
            $m     = ComponentHelper::getParams('com_media');
            $bad   = self::dangerousUploadExtensions([
                $m->get('upload_extensions', ''), $m->get('restrict_uploads_extensions', ''), $m->get('image_extensions', ''),
                $m->get('audio_extensions', ''), $m->get('video_extensions', ''), $m->get('doc_extensions', ''),
            ]);

            if ($bad) {
                $row('COM_FGSYSTEMREPORT_SEC_UPLOADS', 'danger', (string) count($bad), Text::sprintf('COM_FGSYSTEMREPORT_SEC_UPLOADS_DANGEROUS', implode(', ', $bad)));
            } elseif ((int) $m->get('restrict_uploads', 1) === 0) {
                $row('COM_FGSYSTEMREPORT_SEC_UPLOADS', 'warning', Text::_('JNO'), Text::_('COM_FGSYSTEMREPORT_SEC_UPLOADS_OPEN'));
            } else {
                $row('COM_FGSYSTEMREPORT_SEC_UPLOADS', 'ok', Text::_('JYES'));
            }
        } catch (\Throwable $e) {
        }

        // Text filters
        try {
            $open = self::openTextFilterGroups(ComponentHelper::getParams('com_config')->get('filters'), $privileged);

            if ($open) {
                $db     = Factory::getContainer()->get('DatabaseDriver');
                $titles = $db->setQuery(
                    $db->getQuery(true)->select($db->quoteName('title'))->from($db->quoteName('#__usergroups'))
                        ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $open)) . ')')
                )->loadColumn() ?: [];

                $row('COM_FGSYSTEMREPORT_SEC_TEXT_FILTERS', 'danger', (string) count($open),
                    Text::sprintf('COM_FGSYSTEMREPORT_SEC_TEXT_FILTERS_OPEN', implode(', ', $titles ?: array_map('strval', $open))));
            } else {
                $row('COM_FGSYSTEMREPORT_SEC_TEXT_FILTERS', 'ok', Text::_('JNONE'));
            }
        } catch (\Throwable $e) {
        }

        // Guessable Super User names
        try {
            $names = [];

            foreach ($superUsers as $su) {
                if (empty($su->block)) {
                    $names[] = (string) $su->username;
                }
            }

            $guess = self::guessableNames($names);
            $guess
                ? $row('COM_FGSYSTEMREPORT_SEC_ADMIN_NAMES', 'warning', (string) count($guess), Text::sprintf('COM_FGSYSTEMREPORT_SEC_ADMIN_NAMES_FOUND', implode(', ', $guess)))
                : $row('COM_FGSYSTEMREPORT_SEC_ADMIN_NAMES', 'ok', Text::_('JNONE'));
        } catch (\Throwable $e) {
        }

        // Action logs
        try {
            PluginHelper::isEnabled('system', 'actionlogs') && PluginHelper::isEnabled('actionlog', 'joomla')
                ? $row('COM_FGSYSTEMREPORT_SEC_ACTIONLOGS', 'ok', Text::_('JYES'))
                : $row('COM_FGSYSTEMREPORT_SEC_ACTIONLOGS', 'warning', Text::_('JNO'), Text::_('COM_FGSYSTEMREPORT_SEC_ACTIONLOGS_OFF'));
        } catch (\Throwable $e) {
        }

        // Update channel
        try {
            $source = (string) ComponentHelper::getParams('com_joomlaupdate')->get('updatesource', 'default');

            self::isUnusualUpdateChannel($source)
                ? $row('COM_FGSYSTEMREPORT_SEC_UPDATE_CHANNEL', 'warning', $source, Text::sprintf('COM_FGSYSTEMREPORT_SEC_UPDATE_CHANNEL_CUSTOM', $source))
                : $row('COM_FGSYSTEMREPORT_SEC_UPDATE_CHANNEL', 'ok', $source !== '' ? $source : 'default');
        } catch (\Throwable $e) {
        }

        return $rows;
    }
}

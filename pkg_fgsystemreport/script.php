<?php
/**
 * @package    Pkg_Fgsystemreport
 * @license    GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

/**
 * Package installer script - Joomla installs plugins from a package disabled,
 * so this enables them: all of them on a fresh install, and the console /
 * quick icon / system plugins on the update that first brings them. A plugin an admin
 * disabled on purpose is never re-enabled by a later update.
 */
class Pkg_FgsystemreportInstallerScript
{
    private const FOLDERS = ['task', 'console', 'quickicon', 'system'];

    /** @var array folder => true when the plugin was not installed before this run */
    private $missing = [];

    /**
     * Record a non-fatal installer problem: into the Joomla log folder
     * (fgsystemreport_install.log) and as a warning on the install screen,
     * so a failed plugin auto-enable is not silent.
     */
    private function warn(\Throwable $e, string $what): void
    {
        $msg = 'FG System Report: ' . $what . ' (' . $e->getMessage() . ')';

        try {
            Log::addLogger(['text_file' => 'fgsystemreport_install.log'], Log::ALL, ['pkg_fgsystemreport']);
            Log::add($msg, Log::WARNING, 'pkg_fgsystemreport');
            Factory::getApplication()->enqueueMessage($msg, 'warning');
        } catch (\Throwable $ignored) {
            // Logging must never break the installer.
        }
    }

    public function preflight($type, $parent)
    {
        // Supported platform: Joomla 5.0+ / 6.x on PHP 8.1+ (the same as the
        // update server declares). Refuse a manual install elsewhere instead
        // of leaving a half-working component behind.
        if ($type !== 'uninstall') {
            $problems = [];

            if (version_compare(JVERSION, '5.0.0', '<')) {
                $problems[] = 'Joomla 5.0 or newer is required (this site runs Joomla ' . JVERSION . ').';
            }

            if (version_compare(PHP_VERSION, '8.1.0', '<')) {
                $problems[] = 'PHP 8.1 or newer is required (this site runs PHP ' . PHP_VERSION . ').';
            }

            if ($problems) {
                foreach ($problems as $msg) {
                    Factory::getApplication()->enqueueMessage('FG System Report: ' . $msg, 'error');
                }

                return false;
            }
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            foreach (self::FOLDERS as $folder) {
                $q = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('fgsystemreport'));

                $this->missing[$folder] = (int) $db->setQuery($q)->loadResult() === 0;
            }
        } catch (\Throwable $e) {
            $this->warn($e, 'could not check which plugins are already installed');
        }

        return true;
    }

    public function postflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            foreach (self::FOLDERS as $folder) {
                if ($type !== 'install' && empty($this->missing[$folder])) {
                    continue;
                }

                $q = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('fgsystemreport'));

                $db->setQuery($q)->execute();
            }
        } catch (\Throwable $e) {
            $this->warn($e, 'could not enable the bundled plugins automatically - enable them manually in Plugins');
        }

        return true;
    }
}

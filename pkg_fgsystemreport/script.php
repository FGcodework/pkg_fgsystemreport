<?php
/**
 * @package    Pkg_Fgsystemreport
 * @license    GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

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

    public function preflight($type, $parent)
    {
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
            // Non-fatal
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
            // Non-fatal: the plugins can be enabled manually.
        }

        return true;
    }
}

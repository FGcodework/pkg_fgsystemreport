<?php
/**
 * @package    Pkg_Fgsystemreport
 * @license    GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Package installer script - enables the task plugin after install/update
 * (Joomla installs plugins from a package disabled by default).
 */
class Pkg_FgsystemreportInstallerScript
{
    public function postflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q  = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('fgsystemreport'));

            // Only on a fresh install: an admin who disabled it on purpose
            // should not have it re-enabled by every update.
            if ($type === 'install') {
                $db->setQuery($q)->execute();
            }
        } catch (\Throwable $e) {
            // Non-fatal: the plugin can be enabled manually.
        }

        return true;
    }
}

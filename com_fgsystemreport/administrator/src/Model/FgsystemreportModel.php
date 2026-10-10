<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\DatabaseInterface;

/**
 * Fgsystemreport model.
 *
 * @since  1.0.0
 */
class FgsystemreportModel extends AdminModel
{
    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form|bool  A Form object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)  // ADDED: proper signature
    {
        return false;
    }
    
    /**
     * Get system report data
     *
     * @return  array  Report data
     *
     * @since   1.0.0
     */
    public function getReportData(): array  // ADDED: : array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $data = [];

        // 1. Total Article Categories
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $data['total_categories'] = $db->loadResult();

        // 2. Total Articles
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $data['total_articles'] = $db->loadResult();

        // 3. Total Extensions
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('name') . " IS NOT NULL AND " . $db->quoteName('name') . " != ''");
        $db->setQuery($query);
        $data['total_extensions'] = $db->loadResult();

        // 4. Total Plugins
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
        $db->setQuery($query);
        $data['total_plugins'] = $db->loadResult();

        // 5. Total Modules
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules'));
        $db->setQuery($query);
        $data['total_modules'] = $db->loadResult();

        // 6. Total Users
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__users'));
        $db->setQuery($query);
        $data['total_users'] = $db->loadResult();

        // 7. Active Users (last 30 days)
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__users'))
            ->where('lastvisitDate >= NOW() - INTERVAL 30 DAY');
        $db->setQuery($query);
        $data['active_users'] = $db->loadResult();

        return $data;
    }
}

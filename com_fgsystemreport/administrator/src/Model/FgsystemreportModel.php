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
    public function getForm($data = [], $loadData = true)
    {
        return false;
    }

    /**
     * Counts shown in the dashboard statistics boxes.
     *
     * @return  array{total_categories:int,total_articles:int,total_users:int}
     *
     * @since   1.13.32
     */
    public function getReportData(): array
    {
        $data = ['total_categories' => 0, 'total_articles' => 0, 'total_users' => 0];

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $data['total_categories'] = (int) $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                    ->where($db->quoteName('published') . ' = 1')
            )->loadResult();

            $data['total_articles'] = (int) $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__content'))
                    ->where($db->quoteName('state') . ' = 1')
            )->loadResult();

            $data['total_users'] = (int) $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__users'))
            )->loadResult();
        } catch (\Throwable $e) {
            // the boxes show 0 instead of breaking the dashboard
        }

        return $data;
    }
}

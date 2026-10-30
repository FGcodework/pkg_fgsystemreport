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
}

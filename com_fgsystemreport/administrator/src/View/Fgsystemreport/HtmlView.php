<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Fgsystemreport;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;

/**
 * View class for a single Fgsystemreport.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected $state;
    protected $item;
    protected $form;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return  void
     *
     * @throws  \Exception
     */
    public function display($tpl = null): void  // ADDED: : void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->reportdata = $this->get('ReportData');
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @throws  \Exception
     */
    protected function addToolbar(): void  // ADDED: : void
    {
        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_DASHBOARD'), 'generic');
    }
}

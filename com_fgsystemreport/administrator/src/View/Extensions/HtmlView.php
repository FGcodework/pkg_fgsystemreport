<?php
/**
 * @version    CVS: 1.0.21
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Extensions;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\View\FgHtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;

/**
 * Installed Extensions view.
 *
 * @since  1.0.21
 */
class HtmlView extends BaseHtmlView
{
    /** @var array Installed extensions list */
    public $extensionsList = [];

    /** @var int Whether core extensions are included in the list */
    public $includeCoreList = 0;

    /** @var int Whether extensions authored by "Joomla! Project" are hidden */
    public $hideOfficialList = 0;

    /** @var string Active quick filter: all|updates|third|disabled */
    public $quickFilter = 'all';

    /** @var array Item counts per quick filter (of the unfiltered list) */
    public $quickCounts = [];

    /** @var int Whether the list is grouped by extension type */
    public $groupByType = 0;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app     = Factory::getApplication();
        $context = 'com_fgsystemreport.extensions';

        // Stored in user state (session) rather than read straight from the
        // request: once set, a filter stays applied across page loads
        // without every toggle link having to carry all the others along
        // with it (previously worked around per-redirect in 1.0.45; this
        // removes the problem at the source).
        $this->includeCoreList  = (int) $app->getUserStateFromRequest($context . '.include_core_list', 'include_core_list', 0, 'int');
        $this->hideOfficialList = (int) $app->getUserStateFromRequest($context . '.hide_official_list', 'hide_official_list', 0, 'int');
        $this->groupByType      = (int) $app->getUserStateFromRequest($context . '.group_by_type', 'group_by_type', 0, 'int');

        $quick = (string) $app->getUserStateFromRequest($context . '.quick_filter', 'quick_filter', 'all', 'cmd');
        $this->quickFilter = in_array($quick, ['all', 'updates', 'third', 'disabled'], true) ? $quick : 'all';

        $full = FgsystemreportHelper::getInstalledExtensions(
            $this->includeCoreList === 1,
            $this->hideOfficialList === 1
        );

        foreach (['all', 'updates', 'third', 'disabled'] as $mode) {
            $this->quickCounts[$mode] = count(FgsystemreportHelper::applyExtensionQuickFilter($full, $mode));
        }

        $this->extensionsList = FgsystemreportHelper::applyExtensionQuickFilter($full, $this->quickFilter);

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     */
    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_EXTENSIONS'), 'cube');
    }
}

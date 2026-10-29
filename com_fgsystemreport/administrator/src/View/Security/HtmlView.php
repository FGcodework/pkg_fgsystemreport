<?php
/**
 * @version    CVS: 1.0.27
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Security;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\View\FgHtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\HistoryHelper;

/**
 * Security Check view.
 *
 * @since  1.0.27
 */
class HtmlView extends BaseHtmlView
{
    /** @var array Security check results */
    public $checks = [];

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        // Stricter than the other views (core.admin, not core.manage):
        // this page exposes Super User account details, exposed-file URLs,
        // DB prefix and other information that's more sensitive than a
        // routine extensions/statistics listing.
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $forceRefresh = (bool) Factory::getApplication()->getInput()->getInt('force', 0);
        $this->checks = FgsystemreportHelper::getSecurityChecks(null, null, $forceRefresh);

        // Record in the history on an explicit re-check, and once as a
        // baseline when no history exists yet (not on every page view).
        // Also when the score shown here differs from the latest recorded run
        // (e.g. after a risk was accepted or revoked), so the header pill,
        // the history and this page always agree.
        $latestRun = HistoryHelper::isEmpty() ? null : HistoryHelper::getLatestRun();
        $stale     = $latestRun && (int) $latestRun->score !== (int) FgsystemreportHelper::calculateSecurityScore($this->checks)->score;

        if ($forceRefresh || $stale || HistoryHelper::isEmpty()) {
            HistoryHelper::record($this->checks, 'manual');
        }

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
        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_SECURITY'), 'shield-alt');
    }
}

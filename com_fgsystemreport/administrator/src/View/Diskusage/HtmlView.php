<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Diskusage;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\View\FgHtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\DiskHistoryHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;

/**
 * Disk Usage view - the top N largest folders under the site root, with
 * an exclude list and a bounded min-heap so only the requested top N are
 * ever kept in memory during the scan. Adapted from mod_jm_treesize
 * (Maarten Blokdijk, jmodules.com), which Fero has maintained and
 * improved through many iterations, and folded in here so it benefits
 * from the same cache controller and PDF/export machinery as the rest of
 * this component instead of being a separate standalone module.
 *
 * @since  1.2.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var array [path => bytes], largest first */
    public $dirs = [];

    /** @var int Total bytes scanned (root minus excluded folders) */
    public $total = 0;

    /** @var int|null Unix timestamp of the scan, or null if never scanned */
    public $scanTime = null;

    /**
     * @var string|null $scanTime formatted in the site's configured
     *      Server Time Zone (Global Configuration), not PHP's own
     *      default timezone - date()/PHP's default can silently differ
     *      from what the site is actually configured to show.
     */
    public $scanTimeFormatted = null;

    /** @var array|null Comparison with the previous scan (see DiskHistoryHelper::compare) */
    public $comparison = null;

    /** @var string|null Previous scan time, formatted in the site time zone */
    public $prevScanFormatted = null;

    /** @var int */
    public $topN = 10;

    /** @var string Comma-separated exclude list, as entered */
    public $excludeRaw = '';

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
        $context = 'com_fgsystemreport.diskusage';

        $this->topN = (int) $app->getUserStateFromRequest($context . '.top_n', 'top_n', 10, 'int');
        $this->topN = max(1, min(50, $this->topN));

        $this->excludeRaw = (string) $app->getUserStateFromRequest(
            $context . '.exclude',
            'exclude',
            'cache,tmp,logs,.git,.svn,node_modules',
            'string'
        );

        $exclude = array_values(array_filter(array_map('trim', explode(',', $this->excludeRaw)), 'strlen'));
        $forceRefresh = (bool) $app->getInput()->getInt('rescan', 0);

        $result = FgsystemreportHelper::getDiskUsageDisplayData($exclude, $this->topN, $forceRefresh);

        $this->dirs     = $result['dirs'] ?? [];
        $this->total    = $result['total'] ?? 0;
        $this->scanTime = $result['time'] ?? null;

        // Comparison with the previous real scan using the same exclude list
        [$currentSnap, $prevSnap] = DiskHistoryHelper::getLatestTwo($exclude);

        if ($currentSnap !== null && $prevSnap !== null) {
            $this->comparison = DiskHistoryHelper::compare($this->dirs, (int) $this->total, $prevSnap);

            if ($this->comparison !== null) {
                $pd = Factory::getDate($prevSnap->scanned_at);

                try {
                    $pd->setTimezone(new \DateTimeZone($app->get('offset', 'UTC')));
                } catch (\Exception $e) {
                    // fall back to UTC
                }

                $this->prevScanFormatted = $pd->format('d.m.Y H:i', true);
            }
        }

        if ($this->scanTime !== null) {
            // Joomla\CMS\Date::setTimezone() is not plain DateTime::setTimezone() -
            // it keeps the date internally in UTC and only stores the given
            // zone in an auxiliary property that format() consults when
            // $local=true; without both of these together, the site's
            // configured Server Time Zone would be silently ignored.
            $date = Factory::getDate($this->scanTime);

            try {
                $date->setTimezone(new \DateTimeZone($app->get('offset', 'UTC')));
            } catch (\Exception $e) {
                // Invalid/missing site timezone config - fall back to UTC (already set).
            }

            $this->scanTimeFormatted = $date->format('d.m.Y H:i', true);
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
        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_DISKUSAGE'), 'archive');
    }
}

<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Report;

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\ChangeLogHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\DbHistoryHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\DiskHistoryHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\ErrorLogHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\HistoryHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Printable site health report: security score and findings, trend, recent
 * changes, errors and storage - one page for "Print / Save as PDF". Built
 * only from data that is already stored (plus the cached Security Check),
 * so opening it never triggers a disk scan. Needs core.admin like the
 * Security Check page, as findings contain sensitive details.
 *
 * @since  1.11.0
 */
class HtmlView extends BaseHtmlView
{
    public const OPTIONS = ['trend', 'changes', 'errors', 'storage', 'details'];

    /** @var array<string,bool> */
    public $opt = [];

    public $client   = '';
    public $isPrint  = false;
    public $site     = '';
    public $siteUrl  = '';
    public $generated = '';
    public $checks   = [];
    public $score;
    public $runs     = [];
    public $events   = [];
    public $errors   = null;
    public $disk     = null;
    public $db       = null;
    public $printUrl = '';
    public $tz       = 'UTC';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $input         = $app->getInput();
        $this->isPrint = $input->getCmd('tmpl', '') === 'component';
        $submitted     = $input->getInt('go', 0) === 1 || $this->isPrint;

        // Sections default to on; once the form was submitted only ticked ones are on
        foreach (self::OPTIONS as $o) {
            $this->opt[$o] = $submitted ? (bool) $input->getInt('o_' . $o, 0) : true;
        }

        $this->client = mb_substr(trim((string) $input->getString('client', '')), 0, 120);
        $this->tz     = (string) $app->get('offset', 'UTC');
        $this->site   = (string) $app->get('sitename', '');
        $this->siteUrl = rtrim(Uri::root(), '/');

        $fresh        = !$this->isPrint && (bool) $input->getInt('fresh', 0);
        $this->checks = FgsystemreportHelper::getSecurityChecks(null, null, $fresh);

        if ($fresh) {
            HistoryHelper::record($this->checks, 'manual');
        }

        $this->score = FgsystemreportHelper::calculateSecurityScore($this->checks);

        if ($this->opt['trend']) {
            $this->runs = HistoryHelper::getRuns(30);
        }

        if ($this->opt['changes']) {
            $this->events = ChangeLogHelper::getEvents(30);
        }

        if ($this->opt['errors']) {
            $this->errors = ErrorLogHelper::analyze(168, 'important');
        }

        if ($this->opt['storage']) {
            [$this->disk] = DiskHistoryHelper::getLatestTwo(['cache', 'tmp', 'logs', '.git', '.svn', 'node_modules']);
            [$this->db]   = DbHistoryHelper::getLatestTwo();
        }

        $q = ['option' => 'com_fgsystemreport', 'view' => 'report', 'tmpl' => 'component'];

        foreach (self::OPTIONS as $o) {
            if ($this->opt[$o]) {
                $q['o_' . $o] = 1;
            }
        }

        if ($this->client !== '') {
            $q['client'] = $this->client;
        }

        $this->printUrl  = 'index.php?' . http_build_query($q);
        $this->generated = $this->fmt(time());

        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_REPORT'), 'file-alt');
        parent::display($tpl);
    }

    /** Timestamp as d.m.Y H:i in the site time zone. */
    public function fmt(int $ts): string
    {
        $d = Factory::getDate($ts);

        try {
            $d->setTimezone(new \DateTimeZone($this->tz));
        } catch (\Exception $e) {
            // keep UTC
        }

        return $d->format('d.m.Y H:i', true);
    }

    /** SQL UTC datetime to site-time string. */
    public function fmtUtc(string $utc): string
    {
        return $this->fmt((int) strtotime($utc . ' UTC'));
    }
}

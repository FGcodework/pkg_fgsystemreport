<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Errors;

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\ErrorLogHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Grouped error log report. Log messages can contain paths and other
 * sensitive data, so it needs core.admin like the log viewer.
 *
 * @since  1.10.0
 */
class HtmlView extends BaseHtmlView
{
    public $hours  = 24;
    public $levels = 'important';
    public $data   = [];
    public $tz     = 'UTC';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $input        = $app->getInput();
        $h            = $input->getInt('hours', 24);
        $this->hours  = in_array($h, [24, 168, 720], true) ? $h : 24;
        $l            = (string) $input->getCmd('levels', 'important');
        $this->levels = in_array($l, ['fatal', 'important', 'all'], true) ? $l : 'important';
        $this->tz     = (string) $app->get('offset', 'UTC');
        $this->data   = ErrorLogHelper::analyze($this->hours, $this->levels);

        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_ERRORS'), 'bug');
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
}

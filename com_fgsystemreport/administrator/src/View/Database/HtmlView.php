<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Database;

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\DbDiagnosticsHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\View\FgHtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Database diagnostics (read-only).
 *
 * @since  1.7.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var array */
    public $steps = [];

    /** @var string|null */
    public $generated = null;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.manage', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $data        = DbDiagnosticsHelper::run((bool) $app->getInput()->getInt('refresh', 0));
        $this->steps = $data['steps'] ?? [];

        $d = Factory::getDate($data['time'] ?? time());

        try {
            $d->setTimezone(new \DateTimeZone($app->get('offset', 'UTC')));
        } catch (\Exception $e) {
            // keep UTC
        }

        $this->generated = $d->format('d.m.Y H:i', true);

        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_DATABASE'), 'database');
        parent::display($tpl);
    }
}

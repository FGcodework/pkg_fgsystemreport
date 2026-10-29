<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Logs;

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\LogViewHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\View\FgHtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Log viewer (read-only). Logs can contain sensitive data, so it needs
 * the stricter core.admin permission, like uninstall actions elsewhere.
 *
 * @since  1.8.0
 */
class HtmlView extends BaseHtmlView
{
    public $dir      = '';
    public $files    = [];
    public $selected = '';
    public $filter   = '';
    public $lines    = 300;
    public $result   = null;
    public $modified = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin', 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $input          = $app->getInput();
        $this->dir      = LogViewHelper::directory();
        $this->files    = LogViewHelper::listFiles($this->dir);
        $this->filter   = trim((string) $input->getString('q', ''));
        $this->lines    = in_array($input->getInt('lines', 300), [100, 300, 1000], true) ? $input->getInt('lines', 300) : 300;
        $this->selected = (string) $input->getString('file', '');

        if ($this->selected === '' && $this->files) {
            $this->selected = $this->files[0]['name'];
        }

        if ($this->selected !== '') {
            $this->result = LogViewHelper::read($this->dir, $this->selected, $this->lines, $this->filter);

            foreach ($this->files as $f) {
                if ($f['name'] === $this->selected) {
                    $d = Factory::getDate($f['modified']);

                    try {
                        $d->setTimezone(new \DateTimeZone($app->get('offset', 'UTC')));
                    } catch (\Exception $e) {
                        // keep UTC
                    }

                    $this->modified = $d->format('d.m.Y H:i', true);
                }
            }
        }

        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_LOGS'), 'file-alt');
        parent::display($tpl);
    }
}

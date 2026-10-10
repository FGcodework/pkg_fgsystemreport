<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\Icons;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;

/**
 * Icon Classes reference view - lists the icon classes (".icon-xxx")
 * available in the current admin template's CSS (FontAwesome, the
 * template's own template.css, and Joomla's bundled icomoon set), each
 * shown with its rendered glyph, so a developer building a view or
 * template override for this or any other extension can see at a glance
 * which classes exist and what they look like.
 *
 * @since  1.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var array [file_path => string[] icon names], only non-empty files */
    public $iconsByFile = [];

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

        $this->iconsByFile = FgsystemreportHelper::getIconClasses();

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
        ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_ICONS'), 'images');
    }
}

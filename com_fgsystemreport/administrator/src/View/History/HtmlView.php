<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View\History;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\HistoryHelper;

/**
 * Security History view - score trend, list of recorded runs and a
 * run-to-run comparison.
 *
 * @since  1.4.0
 */
class HtmlView extends BaseHtmlView
{
	/** @var array */
	public $runs = [];

	/** @var int Range in days */
	public $days = 90;

	/** @var object|null Selected run (with ->decoded) */
	public $selected = null;

	/** @var object|null Run before the selected one */
	public $previous = null;

	/** @var array Result of HistoryHelper::compare() */
	public $diff = [];

	/** @var int */
	public $retention = 365;

	public function display($tpl = null): void
	{
		// Same sensitivity as the Security Check page itself.
		if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_fgsystemreport')) {
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$input = Factory::getApplication()->getInput();
		$days  = $input->getInt('days', 90);
		$this->days      = in_array($days, [30, 90, 365], true) ? $days : 90;
		$this->retention = HistoryHelper::getRetentionDays();
		$this->runs      = HistoryHelper::getRuns($this->days);

		$runId = $input->getInt('run', 0);

		if ($runId <= 0 && $this->runs) {
			$runId = (int) end($this->runs)->id;
		}

		if ($runId > 0) {
			$this->selected = HistoryHelper::getRun($runId);

			if ($this->selected) {
				$this->previous = HistoryHelper::getPreviousRun($runId);
				$this->diff     = HistoryHelper::compare($this->selected, $this->previous);
			}
		}

		ToolbarHelper::title(Text::_('COM_FGSYSTEMREPORT_TITLE_HISTORY'), 'chart');

		parent::display($tpl);
	}
}

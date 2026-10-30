<?php
/**
 * @package    Plg_quickicon_fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Quickicon\Fgsystemreport\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * Quick icon plugin: shows the latest Security Check score on the Joomla
 * administrator Home page and links to the Security page of the FG System
 * Report component.
 *
 * It only reads the most recent *recorded* run (Security History) - it never
 * runs a live check, so the Home page stays fast. Runs are recorded whenever
 * the Security page is opened, by the scheduled task or by the CLI command.
 *
 * @since  1.0.0
 */
final class Fgsystemreport extends CMSPlugin implements SubscriberInterface
{
    /**
     * @var  boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Quick icon module context this icon is shown in (Home page).
     *
     * @var  string
     */
    protected $context = 'mod_quickicon';

    /**
     * Days after which the last recorded run counts as outdated.
     */
    private const STALE_DAYS = 7;

    public static function getSubscribedEvents(): array
    {
        return ['onGetIcons' => 'getIcons'];
    }

    /**
     * @param   object  $event  The onGetIcons event
     *
     * @return  void
     */
    public function getIcons($event): void
    {
        if ((string) $event->getArgument('context') !== $this->context) {
            return;
        }

        $app      = $this->getApplication();
        $identity = $app ? $app->getIdentity() : null;

        // The icon links to the Security page, which needs core.admin - do not
        // show a link that ends in a 403.
        if (!$identity || !$identity->authorise('core.admin', 'com_fgsystemreport')) {
            return;
        }

        $history = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\HistoryHelper';

        if (!class_exists($history)) {
            return;
        }

        $run = $history::getLatestRun();

        if ($run === null) {
            $text  = Text::_('PLG_QUICKICON_FGSYSTEMREPORT_NO_DATA');
            $class = '';
        } else {
            $text = Text::sprintf('PLG_QUICKICON_FGSYSTEMREPORT_SCORE', (int) $run->score);

            if ((int) $run->count_danger > 0) {
                $class = 'danger';
            } elseif ((int) $run->count_warning > 0) {
                $class = 'warning';
            } else {
                $class = 'success';
            }

            $age = (time() - strtotime($run->run_at . ' UTC')) / 86400;

            if ($age > self::STALE_DAYS) {
                $text  .= ' - ' . Text::sprintf('PLG_QUICKICON_FGSYSTEMREPORT_OUTDATED', (int) floor($age));
                $class  = $class === 'danger' ? 'danger' : 'warning';
            }
        }

        $icon = [
            'link'   => 'index.php?option=com_fgsystemreport&view=security',
            'image'  => 'icon-lock',
            'icon'   => '',
            'text'   => $text,
            'id'     => 'plg_quickicon_fgsystemreport',
            'group'  => 'MOD_QUICKICON_MAINTENANCE',
            'class'  => $class,
            'name'   => Text::_('PLG_QUICKICON_FGSYSTEMREPORT_NAME'),
            'access' => ['core.admin', 'com_fgsystemreport'],
        ];

        if (method_exists($event, 'addResult')) {
            $event->addResult([$icon]);

            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = [$icon];
        $event->setArgument('result', $result);
    }
}

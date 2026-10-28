<?php
/**
 * @package    Plg_console_fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Fgsystemreport\Extension;

\defined('_JEXEC') or die;

use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Console\Fgsystemreport\Command\SecurityCheckCommand;

/**
 * Console plugin: registers `php cli/joomla.php fgsystemreport:check`, which
 * runs the FG System Report Security Check from the command line / cron.
 *
 * Requires the FG System Report component (com_fgsystemreport).
 *
 * @since  1.0.0
 */
final class Fgsystemreport extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
        ];
    }

    /**
     * Adds the command to the console application.
     *
     * @return  void
     */
    public function registerCommands(): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        $command = new SecurityCheckCommand();

        // Symfony Console 7.4+ renamed add() to addCommand()
        if (method_exists($app, 'addCommand')) {
            $app->addCommand($command);
        } else {
            $app->add($command);
        }
    }
}

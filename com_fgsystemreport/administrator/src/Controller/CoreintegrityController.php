<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Controller;

\defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\CoreIntegrityHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Steps of the core file check, called by the Security page with fetch():
 * task=coreintegrity.step (POST, token). Answers JSON
 * {phase, progress, done, error, message}.
 *
 * Same permission as the Security page (core.admin).
 *
 * @since  1.16.0
 */
class CoreintegrityController extends BaseController
{
    public function step(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('post')) {
            $this->send(['done' => true, 'progress' => 0, 'error' => 'token', 'message' => Text::_('JINVALID_TOKEN')]);
        }

        if (!$app->getIdentity()->authorise('core.admin', 'com_fgsystemreport')) {
            $this->send(['done' => true, 'progress' => 0, 'error' => 'access', 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
        }

        if ($app->getInput()->post->getInt('fresh', 0) === 1) {
            CoreIntegrityHelper::engine()->reset();
        }

        try {
            $r = CoreIntegrityHelper::step();
        } catch (\Throwable $e) {
            $this->send(['done' => true, 'progress' => 0, 'error' => 'exception', 'message' => Text::_('COM_FGSYSTEMREPORT_CORE_ERR_EXCEPTION')]);
        }

        $this->send([
            'phase'    => $r['phase'],
            'progress' => $r['progress'],
            'done'     => $r['done'],
            'error'    => $r['error'],
            'message'  => $r['error'] ? Text::_('COM_FGSYSTEMREPORT_CORE_ERR_' . strtoupper($r['error'])) : Text::_('COM_FGSYSTEMREPORT_CORE_PHASE_' . strtoupper($r['phase'])),
        ]);
    }

    /** @return never */
    private function send(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}

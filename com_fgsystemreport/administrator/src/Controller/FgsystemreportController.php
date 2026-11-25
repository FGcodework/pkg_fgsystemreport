<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Table\Extension;

use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\AcceptedRiskHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\HistoryHelper;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Updater\Updater;
use Joomla\CMS\Session\Session;
use Fg\Component\Fgsystemreport\Administrator\Service\ReportPdfService;
use Joomla\Utilities\ArrayHelper;

class FgsystemreportController extends FormController
{
    protected $view_list = 'fgsystemreport';

    /**
     * Require the given ACL action on this component, or abort with a 403.
     *
     * @param   string  $action  ACL action, e.g. 'core.manage' or 'core.admin'
     *
     * @return  void
     */
    private function requireAccess(string $action = 'core.manage'): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise($action, 'com_fgsystemreport')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
    /**
     * Refresh the #__updates table (same as com_installer "Find Updates")
     * and return to the Installed Extensions view.
     *
     * @return  void
     */
    /**
     * Export the installed extensions list as CSV (UTF-8 with BOM,
     * semicolon-delimited so it opens cleanly in Excel).
     *
     * @return  void
     */
    public function exportCsv()
    {
        $this->requireAccess();
        // CSRF protection
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $context = 'com_fgsystemreport.extensions';
        $includeCoreList = (int) $app->getUserStateFromRequest($context . '.include_core_list', 'include_core_list', 0, 'int');
        $hideOfficialList = (int) $app->getUserStateFromRequest($context . '.hide_official_list', 'hide_official_list', 0, 'int');
        $extensions = FgsystemreportHelper::getInstalledExtensions($includeCoreList === 1, $hideOfficialList === 1);
        $quick = (string) $app->getUserStateFromRequest($context . '.quick_filter', 'quick_filter', 'all', 'cmd');
        $extensions = FgsystemreportHelper::applyExtensionQuickFilter($extensions, $quick);
        $host = preg_replace('/[^A-Za-z0-9.\-]/', '', Uri::getInstance()->getHost());
        $filename = 'extensions_' . $host . '_' . date('Y-m-d') . '.csv';
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel detects the encoding (Slovak diacritics)
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_NAME'),
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_TYPE'),
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_PACKAGE'),
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_AUTHOR'),
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_STATE'),
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_CURRENT_VERSION'),
            Text::_('COM_FGSYSTEMREPORT_EXT_COL_NEW_VERSION'),
            'Element',
        ], ';');
        foreach ($extensions as $ext) {
            $typeKey = 'COM_FGSYSTEMREPORT_TYPE_' . strtoupper($ext->type);
            $typeText = Text::_($typeKey);
            if ($typeText === $typeKey) {
                $typeText = ucfirst($ext->type);
            }
            fputcsv($out, [
                $ext->display_name,
                $typeText,
                $ext->is_child ? $ext->parent_package : ($ext->is_package ? '*' : ''),
                $ext->is_official ? Text::_('COM_FGSYSTEMREPORT_EXT_OFFICIAL') : $ext->author,
                (int) $ext->enabled === 1
                    ? Text::_('COM_FGSYSTEMREPORT_EXT_ENABLED')
                    : Text::_('COM_FGSYSTEMREPORT_EXT_DISABLED'),
                $ext->installed_version,
                $ext->has_update ? $ext->available_version : '',
                ($ext->type === 'plugin' ? 'plg_' . $ext->folder . '_' : '') . $ext->element,
            ], ';');
        }
        fclose($out);
        $app->close();
    }

    /**
     * Export the Security Check results as CSV.
     *
     * @return  void
     */
    public function exportSecurityCsv()
    {
        $this->requireAccess('core.admin');
        // CSRF protection
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        $app    = Factory::getApplication();
        $checks = FgsystemreportHelper::getSecurityChecks();
        $host   = preg_replace('/[^A-Za-z0-9.\-]/', '', Uri::getInstance()->getHost());
        $filename = 'security_check_' . $host . '_' . date('Y-m-d') . '.csv';
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel detects the encoding (Slovak diacritics)
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            Text::_('COM_FGSYSTEMREPORT_SEC_COL_CHECK'),
            Text::_('COM_FGSYSTEMREPORT_SEC_COL_STATUS'),
            Text::_('COM_FGSYSTEMREPORT_SEC_COL_VALUE'),
            Text::_('COM_FGSYSTEMREPORT_SEC_COL_NOTE'),
        ], ';');
        foreach ($checks as $check) {
            $statusLabel = Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_' . strtoupper($check->status));
            fputcsv($out, [$check->label, $statusLabel, $check->value, $check->note], ';');
        }
        fclose($out);
        $app->close();
    }

    /**
     * Export the Security Check history as CSV.
     *
     * @return  void
     */
    public function exportHistoryCsv()
    {
        $this->requireAccess('core.admin');
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        $app  = Factory::getApplication();
        $days = $app->getInput()->getInt('days', 365);
        $days = in_array($days, [30, 90, 365], true) ? $days : 365;
        $runs = HistoryHelper::getRuns($days);
        $host = preg_replace('/[^A-Za-z0-9.\-]/', '', Uri::getInstance()->getHost());
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="security_history_' . $host . '_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['run_at_utc', 'source', 'score', 'danger', 'warning', 'ok', 'joomla', 'php'], ';');
        foreach ($runs as $r) {
            fputcsv($out, [$r->run_at, $r->source, $r->score, $r->count_danger, $r->count_warning, $r->count_ok, $r->joomla_version, $r->php_version], ';');
        }
        fclose($out);
        $app->close();
    }

    /**
     * Delete the whole Security Check history.
     *
     * @return  void
     */
    public function clearHistory()
    {
        $this->requireAccess('core.admin');
        Session::checkToken('post') or die(Text::_('JINVALID_TOKEN'));
        HistoryHelper::clear();
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('COM_FGSYSTEMREPORT_HISTORY_CLEARED'));
        $app->redirect(Route::_('index.php?option=com_fgsystemreport&view=history', false));
    }

    /**
     * Mark a Security Check finding as an accepted risk (POST, core.admin).
     *
     * @return  void
     */
    public function acceptRisk()
    {
        $this->requireAccess('core.admin');
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));
        $input = Factory::getApplication()->getInput();
        $ok    = AcceptedRiskHelper::accept(
            (string) $input->post->getString('check_key', ''),
            (string) $input->post->getString('note', ''),
            max(0, min(3650, $input->post->getInt('expire_days', 0)))
        );
        $app   = Factory::getApplication();
        $app->enqueueMessage(Text::_($ok ? 'COM_FGSYSTEMREPORT_ACCEPTED_SAVED' : 'COM_FGSYSTEMREPORT_ACCEPTED_FAILED'), $ok ? 'message' : 'error');
        $app->redirect(Route::_('index.php?option=com_fgsystemreport&view=security', false));
    }

    /**
     * Withdraw an accepted risk (POST, core.admin).
     *
     * @return  void
     */
    public function revokeRisk()
    {
        $this->requireAccess('core.admin');
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $ok  = AcceptedRiskHelper::revoke((string) $app->getInput()->post->getString('check_key', ''));
        $app->enqueueMessage(Text::_($ok ? 'COM_FGSYSTEMREPORT_ACCEPTED_REVOKED' : 'COM_FGSYSTEMREPORT_ACCEPTED_FAILED'), $ok ? 'message' : 'error');
        $app->redirect(Route::_('index.php?option=com_fgsystemreport&view=security', false));
    }

    /**
     * Export the Security Check results as JSON.
     *
     * @return  void
     */
    public function exportSecurityJson()
    {
        $this->requireAccess('core.admin');
        // CSRF protection
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        $app    = Factory::getApplication();
        $checks = FgsystemreportHelper::getSecurityChecks();
        $host   = preg_replace('/[^A-Za-z0-9.\-]/', '', Uri::getInstance()->getHost());
        $filename = 'security_check_' . $host . '_' . date('Y-m-d') . '.json';
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');
        echo json_encode(array_values($checks), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $app->close();
    }

    /**
     * Dismiss the folder growth banner.
     */
    public function dismissGrowthAlert()
    {
        $this->requireAccess();
        Session::checkToken('post') or die(Text::_('JINVALID_TOKEN'));
        \Fg\Component\Fgsystemreport\Administrator\Helper\GrowthAlertHelper::dismissAll();
        $this->setRedirect(Route::_('index.php?option=com_fgsystemreport&view=diskusage', false));
    }

    /**
     * Send a test e-mail through the site mailer (Global Configuration).
     */
    public function sendTestMail()
    {
        // Sends an e-mail to the configured recipients: administrators only
        $this->requireAccess('core.admin');
        Session::checkToken('post') or die(Text::_('JINVALID_TOKEN'));
        $app     = Factory::getApplication();
        $session = $app->getSession();

        // Simple rate limit: one test mail per 30 seconds per session.
        $last = (int) $session->get('com_fgsystemreport.testmail_at', 0);

        if ($last && (time() - $last) < 30) {
            $app->enqueueMessage(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_TEST_WAIT', 30 - (time() - $last)), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_fgsystemreport&view=diskusage', false));

            return;
        }

        $session->set('com_fgsystemreport.testmail_at', time());

        $error = null;
        $ok    = \Fg\Component\Fgsystemreport\Administrator\Helper\GrowthAlertHelper::sendMail(null, $error);

        if ($ok) {
            $app->enqueueMessage(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_TEST_OK', implode(', ', \Fg\Component\Fgsystemreport\Administrator\Helper\GrowthAlertHelper::recipients())), 'message');
        } else {
            $app->enqueueMessage(Text::sprintf('COM_FGSYSTEMREPORT_GROWTH_TEST_FAIL', (string) $error), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_fgsystemreport&view=diskusage', false));
    }

    public function findUpdates()
    {
        $this->requireAccess();
        // CSRF protection
        Session::checkToken('post') or die(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        // Fetch fresh update information for all extensions (no cache)
        Updater::getInstance()->findUpdates(0, 0);
        $app->enqueueMessage(Text::_('COM_FGSYSTEMREPORT_EXT_UPDATES_REFRESHED'), 'message');
        // Filters are stored in user state (session) by the view itself
        // now, so the redirect doesn't need to carry them along.
        $this->setRedirect(
            Route::_('index.php?option=com_fgsystemreport&view=extensions', false)
        );
    }

    /**
     * Generate and stream the PDF system report.
     *
     * Input options are read from the POST request by the service.
     *
     * @return  void
     */
    public function generatePDF()
    {
        $this->requireAccess('core.admin');
        // CSRF protection
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));
        (new ReportPdfService())->generate();
    }

    /**
     * Build the PDF report (same POST checkbox selections as
     * generatePDF()) and email it as an attachment instead of
     * downloading it.
     *
     * @return  void
     */
    public function emailPDF()
    {
        $this->requireAccess('core.admin');
        // CSRF protection
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        $app          = Factory::getApplication();
        $jinput       = $app->getInput();
        $recipientRaw = trim((string) $jinput->post->getString('pdf_email_recipient', ''));

        if ($recipientRaw === '' || !filter_var($recipientRaw, FILTER_VALIDATE_EMAIL)) {
            $app->enqueueMessage(Text::_('COM_FGSYSTEMREPORT_PDF_EMAIL_INVALID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_fgsystemreport&view=fgsystemreport', false));

            return;
        }

        $sent = (new ReportPdfService())->generateAndEmail($recipientRaw);

        $app->enqueueMessage(
            $sent
                ? Text::sprintf('COM_FGSYSTEMREPORT_PDF_EMAIL_SENT', $recipientRaw)
                : Text::_('COM_FGSYSTEMREPORT_PDF_EMAIL_FAILED'),
            $sent ? 'message' : 'error'
        );
        $this->setRedirect(Route::_('index.php?option=com_fgsystemreport&view=fgsystemreport', false));
    }

    public function getExtensionCount()
    {
        $this->requireAccess();
        // CSRF protection
        Session::checkToken('post') or die(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $includeCore = $jinput->post->getInt('include_core', 0);
        // Reuse the same logic as the Installed Extensions page, so the
        // dashboard count always matches what that page actually lists.
        $count = count(FgsystemreportHelper::getInstalledExtensions($includeCore === 1));
        echo json_encode(['count' => $count]);
        $app->close();
    }

}

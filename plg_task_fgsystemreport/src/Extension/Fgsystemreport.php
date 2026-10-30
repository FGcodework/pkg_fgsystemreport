<?php
/**
 * @package    Plg_task_fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Fgsystemreport\Extension;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseAwareTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Task plugin: runs the FG System Report Security Check once a day (or on
 * whatever schedule the task is configured with) and emails a notification
 * only when something actually got worse since the last run - a new
 * critical finding, a warning that became critical, the SSL certificate or
 * backup crossing into their warning windows, or a new Super User account.
 * An unchanged, already-known problem does not trigger a repeat email every
 * single day.
 *
 * Requires the FG System Report component (com_fgsystemreport) to be
 * installed on the same site - this plugin only orchestrates scheduling
 * and notification, it reuses that component's own Security Check logic
 * rather than duplicating it.
 *
 * @since  1.0.0
 */
final class Fgsystemreport extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var array
     */
    private const TASKS_MAP = [
        'fgsystemreport.security_check_notify' => [
            'langConstPrefix' => 'PLG_TASK_FGSYSTEMREPORT_NOTIFY',
            'method'          => 'notifySecurityCheck',
            'form'            => 'security_check_notify',
        ],
        'fgsystemreport.db_growth_check' => [
            'langConstPrefix' => 'PLG_TASK_FGSYSTEMREPORT_DBGROWTH',
            'method'          => 'dbGrowthCheck',
        ],
        'fgsystemreport.disk_growth_check' => [
            'langConstPrefix' => 'PLG_TASK_FGSYSTEMREPORT_DISKGROWTH',
            'method'          => 'diskGrowthCheck',
        ],
    ];

    /**
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Take a fresh snapshot of database table sizes. The component records it,
     * compares it with the previous one and raises the growth alert.
     *
     * @param   ExecuteTaskEvent  $event  The task execution event
     *
     * @return  int
     */
    protected function dbGrowthCheck(ExecuteTaskEvent $event): int
    {
        $helper = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\DbDiagnosticsHelper';

        if (!class_exists($helper) || !class_exists('\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\DbHistoryHelper')) {
            $this->logTask('The FG System Report component (version 1.8.0 or newer) is not installed on this site - cannot run.', 'error');

            return Status::KNOCKOUT;
        }

        try {
            $helper::$source = 'scheduler';
            $result          = $helper::run(true);
        } catch (\Throwable $e) {
            $this->logTask('Database snapshot failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        $this->logTask('Database snapshot finished (' . count($result['steps'] ?? []) . ' steps).', 'info');

        return Status::OK;
    }

    /**
     * Run a fresh Disk Usage scan. The component records the snapshot,
     * compares it with the previous one and raises the folder growth alert
     * (banner, private message and, if enabled, e-mail).
     *
     * @param   ExecuteTaskEvent  $event  The task execution event
     *
     * @return  int
     */
    protected function diskGrowthCheck(ExecuteTaskEvent $event): int
    {
        $helperClass = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\FgsystemreportHelper';

        if (!class_exists($helperClass) || !class_exists('\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\GrowthAlertHelper')) {
            $this->logTask('The FG System Report component (version 1.6.0 or newer) is not installed on this site - cannot run.', 'error');

            return Status::KNOCKOUT;
        }

        try {
            $helperClass::$scanSource = 'scheduler';
            $exclude                  = ['cache', 'tmp', 'logs', '.git', '.svn', 'node_modules'];
            $result                   = $helperClass::getDiskUsageDisplayData($exclude, 50, true);
        } catch (\Throwable $e) {
            $this->logTask('Disk scan failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        if (!empty($result['partial'])) {
            // Nothing was stored, so the growth monitoring did not run: report
            // the task as failed so it is visible in Scheduled Tasks.
            $this->logTask('Disk scan stopped early (limit: ' . ($result['reason'] ?? '?') . ', ' . (int) ($result['scanned'] ?? 0) . ' entries visited). The result is partial and was NOT stored in the history, so no growth check was done. Raise the disk scan limits in the component options or add large folders to the exclude list.', 'error');

            return Status::KNOCKOUT;
        }

        $this->logTask('Disk scan finished (' . count($result['dirs'] ?? []) . ' folders recorded).', 'info');

        return Status::OK;
    }

    /**
     * Run the Security Check and email a notification if something got
     * worse since the last run.
     *
     * @param   ExecuteTaskEvent  $event  The task execution event
     *
     * @return  int  One of the Status::* constants
     */
    protected function notifySecurityCheck(ExecuteTaskEvent $event): int
    {
        $helperClass = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\FgsystemreportHelper';

        if (!class_exists($helperClass)) {
            $this->logTask('The FG System Report component (com_fgsystemreport) is not installed on this site - cannot run.', 'error');

            return Status::KNOCKOUT;
        }

        $db = $this->getDatabase();

        // Run a fully fresh check (bypass the component's own 60-minute
        // cache) - this scheduled run IS the "check again" moment.
        try {
            $checks = $helperClass::getSecurityChecks(null, null, true);
        } catch (\Throwable $e) {
            $this->logTask('Failed to run security checks: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        $scoreData = $helperClass::calculateSecurityScore($checks);

        // Record this run in the component's Security History (1.4.0+;
        // silently skipped on older component versions) and apply the
        // retention period.
        $historyClass = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\HistoryHelper';

        if (class_exists($historyClass)) {
            $historyClass::record($checks, 'scheduler');
            $historyClass::purge();
        }

        // Compare by the stable check key (the label is translated and would
        // differ after a language change); the label is for display only.
        $currentChecks = [];
        $labels        = [];

        foreach ($checks as $check) {
            $id                 = (string) ($check->key ?? $check->label);
            $currentChecks[$id] = $check->status;
            $labels[$id]        = (string) $check->label;
        }

        $superGroups = implode(',', array_map('intval', method_exists($helperClass, 'getSuperUserGroupIds') ? $helperClass::getSuperUserGroupIds() : [8]));

        $superUserCount = (int) $db->setQuery(
            'SELECT COUNT(DISTINCT u.id) FROM ' . $db->quoteName('#__users') . ' u'
            . ' INNER JOIN ' . $db->quoteName('#__user_usergroup_map') . ' m ON m.user_id = u.id'
            . ' WHERE m.group_id IN (' . $superGroups . ')'
        )->loadResult();

        $currentState = [
            'format'         => 2,
            'checks'         => $currentChecks,
            'superUserCount' => $superUserCount,
            'timestamp'      => time(),
        ];

        // Load and update our own persisted state (stashed in this
        // plugin's own params blob - no extra DB table needed for a
        // simple "what did we see last time" comparison).
        $rawParams = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('fgsystemreport'))
        )->loadResult();

        $storedParams  = json_decode((string) $rawParams, true) ?: [];
        $previousState = $storedParams['last_state'] ?? null;

        $severityRank = ['info' => 0, 'ok' => 0, 'warning' => 1, 'danger' => 2];
        $worsened     = [];

        // State saved by versions before the key-based format used translated
        // labels: take it only as a new baseline (no comparison, no false alarms).
        $migrating = $previousState !== null && (int) ($previousState['format'] ?? 1) < 2;

        if ($previousState !== null && !$migrating) {
            $prevChecks = $previousState['checks'] ?? [];

            foreach ($currentChecks as $id => $status) {
                $prevStatus = $prevChecks[$id] ?? 'ok';
                $prevRank   = $severityRank[$prevStatus] ?? 0;
                $curRank    = $severityRank[$status] ?? 0;

                if ($curRank > $prevRank) {
                    $worsened[] = $labels[$id] ?? $id;
                }
            }

            $prevSuperUserCount = (int) ($previousState['superUserCount'] ?? $superUserCount);

            if ($superUserCount > $prevSuperUserCount) {
                $worsened[] = Text::_('PLG_TASK_FGSYSTEMREPORT_NEW_SUPERUSER');
            }
        }

        // The new state is saved only once nothing is left to report: right away
        // when there is no notification to send, otherwise only after the
        // e-mail went out, so a failed send is retried (the same worsening is
        // still "new" at the next run).
        $storedParams['last_state'] = $currentState;

        $persist = function () use ($db, $storedParams): void {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($storedParams)))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('fgsystemreport'))
            )->execute();
        };

        if ($previousState === null || $migrating) {
            $persist();
            $this->logTask('First run - baseline recorded, no notification sent.', 'info');

            return Status::OK;
        }

        if (empty($worsened)) {
            $persist();
            $this->logTask('No new or worsened findings since the last run - no notification sent.', 'info');

            return Status::OK;
        }

        $params           = $event->getArgument('params');
        $recipientsParam  = trim((string) $params->get('recipients', ''));

        if ($recipientsParam !== '') {
            // Split on comma / semicolon / whitespace and keep only valid addresses
            $all        = preg_split('/[\s,;]+/', $recipientsParam, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $recipients = array_values(array_filter($all, static fn ($r) => filter_var($r, FILTER_VALIDATE_EMAIL) !== false));

            if (count($recipients) < count($all)) {
                $this->logTask('Ignored ' . (count($all) - count($recipients)) . ' invalid recipient address(es) in the task options.', 'warning');
            }
        } else {
            $recipients = $db->setQuery(
                'SELECT DISTINCT u.email FROM ' . $db->quoteName('#__users') . ' u'
                . ' INNER JOIN ' . $db->quoteName('#__user_usergroup_map') . ' m ON m.user_id = u.id'
                . ' WHERE m.group_id IN (' . $superGroups . ') AND u.block = 0'
            )->loadColumn() ?: [];
        }

        if (empty($recipients)) {
            $this->logTask('New/worsened findings detected, but no recipients configured and no active Super Users found - could not send notification.', 'warning');

            return Status::KNOCKOUT;
        }

        $siteName = (string) Factory::getApplication()->get('sitename', '');

        $bodyLines   = [];
        $bodyLines[] = Text::sprintf('PLG_TASK_FGSYSTEMREPORT_EMAIL_INTRO', $siteName, (int) $scoreData->score);
        $bodyLines[] = '';
        $bodyLines[] = Text::_('PLG_TASK_FGSYSTEMREPORT_EMAIL_CHANGES');

        foreach ($worsened as $item) {
            $bodyLines[] = '- ' . $item;
        }

        $bodyLines[] = '';
        $bodyLines[] = Text::_('PLG_TASK_FGSYSTEMREPORT_EMAIL_FOOTER');

        $body = implode("\n", $bodyLines);

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->setSubject(Text::sprintf('PLG_TASK_FGSYSTEMREPORT_EMAIL_SUBJECT', $siteName));
            $mailer->setBody($body);

            foreach ($recipients as $recipient) {
                $mailer->addRecipient($recipient);
            }

            $sent = $mailer->Send();

            if ($sent !== true) {
                $this->logTask('Notification email failed to send.', 'error');

                return Status::KNOCKOUT;
            }
        } catch (\Throwable $e) {
            $this->logTask('Mailer error: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        $persist();
        $this->logTask('Notification email sent to ' . count($recipients) . ' recipient(s): ' . implode(', ', $worsened), 'info');

        return Status::OK;
    }
}

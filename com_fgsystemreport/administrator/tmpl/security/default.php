<?php
/**
 * @version    CVS: 1.0.27
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\Helper\BusyHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;

$checks = $this->checks ?? [];

$badge = [
    'ok'      => ['bg-success',   'COM_FGSYSTEMREPORT_SEC_STATUS_OK'],
    'warning' => ['bg-warning text-dark', 'COM_FGSYSTEMREPORT_SEC_STATUS_WARNING'],
    'danger'  => ['bg-danger',    'COM_FGSYSTEMREPORT_SEC_STATUS_DANGER'],
    'info'    => ['bg-secondary', 'COM_FGSYSTEMREPORT_SEC_STATUS_INFO'],
];

$counts = ['ok' => 0, 'warning' => 0, 'danger' => 0, 'info' => 0];
foreach ($checks as $c) {
    if (isset($counts[$c->status])) {
        $counts[$c->status]++;
    }
}
$sc       = FgsystemreportHelper::calculateSecurityScore($checks);
$scState  = $sc->dangerCount > 0 || $sc->score < 60 ? 'is-bad' : ($sc->warningCount > 0 || $sc->score < 90 ? 'is-warn' : 'is-ok');
?>

<div id="jc-security-checks">
    <div class="fgsr-hero">
        <div class="fgsr-ring <?php echo $scState; ?>" style="--p:<?php echo (int) $sc->score; ?>"><span><?php echo (int) $sc->score; ?></span></div>
        <div>
            <div class="fgsr-kicker"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_HEADING'); ?></div>
            <div class="fgsr-hero-title"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_HEAD_SCORE', (int) $sc->score); ?></div>
            <div class="fgsr-hero-badges">
                <?php if ($counts['danger']) : ?>
                    <span class="badge bg-danger"><?php echo $counts['danger']; ?> &times; <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_DANGER'); ?></span>
                <?php endif; ?>
                <?php if ($counts['warning']) : ?>
                    <span class="badge bg-warning text-dark"><?php echo $counts['warning']; ?> &times; <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_WARNING'); ?></span>
                <?php endif; ?>
                <span class="badge bg-success"><?php echo $counts['ok']; ?> &times; <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_OK'); ?></span>
            </div>
            <div class="fgsr-hero-actions">
            <a class="btn btn-sm btn-outline-primary fg-busy" data-fg-busy-text="<?php echo $this->escape(Text::_('COM_FGSYSTEMREPORT_SEC_RECHECKING')); ?>" href="index.php?option=com_fgsystemreport&view=security&force=1">
                <span class="icon-refresh me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_RECHECK'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?option=com_fgsystemreport&view=history">
                <span class="icon-chart me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_HISTORY'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary"
                href="index.php?option=com_fgsystemreport&task=fgsystemreport.exportSecurityCsv&<?php echo Joomla\CMS\Session\Session::getFormToken(); ?>=1">
                <span class="icon-download me-1" aria-hidden="true"></span>CSV
            </a>
            <a class="btn btn-sm btn-outline-secondary"
                href="index.php?option=com_fgsystemreport&task=fgsystemreport.exportSecurityJson&<?php echo Joomla\CMS\Session\Session::getFormToken(); ?>=1">
                <span class="icon-download me-1" aria-hidden="true"></span>JSON
            </a>
            </div>
        </div>
    </div>
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm table-hover m-0">
            <thead>
                <tr>
                    <th style="width:28%;"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_COL_CHECK'); ?></th>
                    <th style="width:12%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_COL_STATUS'); ?></th>
                    <th style="width:25%;"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_COL_VALUE'); ?></th>
                    <th style="width:35%;"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_COL_NOTE'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check) : ?>
                <?php
                    [$cls, $key] = $badge[$check->status] ?? $badge['info'];
                    $rowClass = $check->status === 'danger' ? 'table-danger' : ($check->status === 'warning' ? 'table-warning' : '');
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo htmlspecialchars($check->label); ?></td>
                    <td class="text-center">
                        <span class="badge <?php echo $cls; ?>"><?php echo Text::_($key); ?></span>
                        <?php if (!empty($check->accepted)) : ?>
                            <br><span class="badge fgsr-badge-official mt-1"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_BADGE'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($check->value); ?></td>
                    <td>
                        <?php echo htmlspecialchars($check->note); ?>
                        <?php if (!empty($check->key) && !empty($check->accepted)) : ?>
                            <form method="post" action="index.php?option=com_fgsystemreport&amp;task=fgsystemreport.revokeRisk" class="mt-1">
                                <input type="hidden" name="check_key" value="<?php echo $this->escape($check->key); ?>">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_REVOKE'); ?></button>
                            </form>
                        <?php elseif (!empty($check->key) && in_array($check->status, ['warning', 'danger'], true)) : ?>
                            <details class="mt-1">
                                <summary class="small text-muted" style="cursor:pointer;"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_ACCEPT_TITLE'); ?></summary>
                                <form method="post" action="index.php?option=com_fgsystemreport&amp;task=fgsystemreport.acceptRisk" class="d-flex gap-2 mt-1">
                                    <input type="hidden" name="check_key" value="<?php echo $this->escape($check->key); ?>">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="text" name="note" maxlength="200" class="form-control form-control-sm" placeholder="<?php echo $this->escape(Text::_('COM_FGSYSTEMREPORT_ACCEPTED_NOTE_PLACEHOLDER')); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_SAVE'); ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>

<?php BusyHelper::render(); ?>

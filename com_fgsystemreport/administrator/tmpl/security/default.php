<?php
/**
 * @version    CVS: 1.0.27
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

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
?>

<div class="card" id="jc-security-checks">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0" style="font-size:16px;">
            <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_HEADING'); ?>
        </h3>
        <div>
            <?php if ($counts['danger']) : ?>
                <span class="badge bg-danger"><?php echo $counts['danger']; ?> &times; <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_DANGER'); ?></span>
            <?php endif; ?>
            <?php if ($counts['warning']) : ?>
                <span class="badge bg-warning text-dark"><?php echo $counts['warning']; ?> &times; <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_WARNING'); ?></span>
            <?php endif; ?>
            <span class="badge bg-success"><?php echo $counts['ok']; ?> &times; <?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_OK'); ?></span>
            <a class="btn btn-sm btn-outline-primary ms-2" href="index.php?option=com_fgsystemreport&view=security&force=1">
                <span class="icon-refresh me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_RECHECK'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary ms-2" href="index.php?option=com_fgsystemreport&view=history">
                <span class="icon-chart me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_HISTORY'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary ms-1"
                href="index.php?option=com_fgsystemreport&task=fgsystemreport.exportSecurityCsv&<?php echo Joomla\CMS\Session\Session::getFormToken(); ?>=1">
                <span class="icon-download me-1" aria-hidden="true"></span>CSV
            </a>
            <a class="btn btn-sm btn-outline-secondary ms-1"
                href="index.php?option=com_fgsystemreport&task=fgsystemreport.exportSecurityJson&<?php echo Joomla\CMS\Session\Session::getFormToken(); ?>=1">
                <span class="icon-download me-1" aria-hidden="true"></span>JSON
            </a>
        </div>
    </div>
    <div class="card-body p-0">
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
                    <td class="text-center"><span class="badge <?php echo $cls; ?>"><?php echo Text::_($key); ?></span></td>
                    <td><?php echo htmlspecialchars($check->value); ?></td>
                    <td><?php echo htmlspecialchars($check->note); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

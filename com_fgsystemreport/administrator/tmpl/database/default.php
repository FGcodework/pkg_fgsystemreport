<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\BusyHelper;
use Joomla\CMS\Language\Text;

$badge = [
    'ok'      => ['bg-success', 'COM_FGSYSTEMREPORT_SEC_STATUS_OK'],
    'warning' => ['bg-warning text-dark', 'COM_FGSYSTEMREPORT_SEC_STATUS_WARNING'],
    'danger'  => ['bg-danger', 'COM_FGSYSTEMREPORT_SEC_STATUS_DANGER'],
    'info'    => ['bg-secondary', 'COM_FGSYSTEMREPORT_SEC_STATUS_INFO'],
];

// Language keys are shown translated; anything else (variable names,
// engine/collation names, table names) is shown as is.
$tr = static function (string $s): string {
    return strpos($s, 'COM_FGSYSTEMREPORT_') === 0 ? Text::_($s) : $s;
};
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0" style="font-size:16px;">
            <?php echo Text::_('COM_FGSYSTEMREPORT_DB_HEADING'); ?>
            <small class="text-muted ms-2"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DB_GENERATED', $h($this->generated)); ?></small>
        </h3>
        <a class="btn btn-sm btn-outline-primary fg-busy" data-fg-busy-text="<?php echo $h(Text::_('COM_FGSYSTEMREPORT_DB_RUNNING')); ?>"
           href="index.php?option=com_fgsystemreport&amp;view=database&amp;refresh=1">
            <span class="icon-refresh me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_DB_RERUN'); ?>
        </a>
    </div>
</div>

<?php foreach ($this->steps as $step) :
    [$cls, $lbl] = $badge[$step['status']] ?? $badge['info']; ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo Text::_('COM_FGSYSTEMREPORT_DB_STEP_' . strtoupper($step['id'])); ?></strong>
            <span>
                <span class="me-2"><?php echo $h($step['value']); ?></span>
                <span class="badge <?php echo $cls; ?>"><?php echo Text::_($lbl); ?></span>
            </span>
        </div>
        <div class="card-body">
            <?php if ($step['note'] !== '') : ?>
                <p class="mb-2"><?php echo $h(Text::_($step['note'])); ?></p>
            <?php endif; ?>

            <?php if ($step['metrics']) : ?>
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($step['metrics'] as [$k, $v]) : ?>
                        <tr>
                            <th style="width:30%;font-weight:600;"><?php echo $h($tr($k)); ?></th>
                            <td style="word-break:break-word;"><?php echo $h($v); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($step['table']['rows'])) : ?>
                <table class="table table-sm table-striped mt-3 mb-0">
                    <thead>
                    <tr>
                        <?php foreach ($step['table']['head'] as $i => $col) : ?>
                            <th<?php echo $i ? ' class="text-end"' : ''; ?>><?php echo $h(Text::_($col)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($step['table']['rows'] as $row) : ?>
                        <tr>
                            <?php foreach ($row as $i => $cell) : ?>
                                <td<?php echo $i ? ' class="text-end"' : ''; ?>><?php echo $h($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php BusyHelper::render(); ?>

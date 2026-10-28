<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$h    = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$d    = $this->data;
$lvl  = ['fatal' => 'danger', 'error' => 'danger', 'warning' => 'warning', 'notice' => 'secondary', 'deprecated' => 'secondary'];
$type = ['extension' => 'primary', 'template' => 'info', 'core' => 'dark', 'unknown' => 'secondary'];
$name = static function (array $o): string {
    return $o['name'] !== '' ? $o['name'] : Text::_('COM_FGSYSTEMREPORT_ERRORS_UNKNOWN');
};
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="m-0" style="font-size:16px;"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_HEADING'); ?></h3>
    </div>
    <div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="option" value="com_fgsystemreport">
            <input type="hidden" name="view" value="errors">
            <div class="col-auto">
                <label class="form-label mb-0" for="fg-err-hours"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_PERIOD'); ?></label>
                <select class="form-select form-select-sm" name="hours" id="fg-err-hours" onchange="this.form.submit()">
                    <?php foreach ([24 => 'COM_FGSYSTEMREPORT_ERRORS_P24', 168 => 'COM_FGSYSTEMREPORT_ERRORS_P7', 720 => 'COM_FGSYSTEMREPORT_ERRORS_P30'] as $v => $k) : ?>
                        <option value="<?php echo $v; ?>"<?php echo $v === $this->hours ? ' selected' : ''; ?>><?php echo Text::_($k); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0" for="fg-err-levels"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_LEVELS'); ?></label>
                <select class="form-select form-select-sm" name="levels" id="fg-err-levels" onchange="this.form.submit()">
                    <?php foreach (['fatal' => 'COM_FGSYSTEMREPORT_ERRORS_L_FATAL', 'important' => 'COM_FGSYSTEMREPORT_ERRORS_L_IMPORTANT', 'all' => 'COM_FGSYSTEMREPORT_ERRORS_L_ALL'] as $v => $k) : ?>
                        <option value="<?php echo $v; ?>"<?php echo $v === $this->levels ? ' selected' : ''; ?>><?php echo Text::_($k); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <a class="btn btn-sm btn-outline-secondary" href="index.php?option=com_fgsystemreport&amp;view=logs"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_OPEN_LOGS'); ?></a>
            </div>
        </form>

        <?php if (!$d['files']) : ?>
            <p class="mb-0"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_NO_FILES'); ?></p>
        <?php else : ?>
            <p class="text-muted small mb-0">
                <?php echo Text::sprintf('COM_FGSYSTEMREPORT_ERRORS_SOURCES', $h(implode(', ', array_column($d['files'], 'label')))); ?>
                &middot; <?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_TAIL_NOTE'); ?>
                <?php if ($d['partial']) : ?> &middot; <?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_PARTIAL'); ?><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($d['files'] && !$d['groups']) : ?>
    <div class="alert alert-success"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_NONE'); ?></div>
<?php elseif ($d['groups']) : ?>
    <div class="card mb-3">
        <div class="card-header"><h3 class="m-0" style="font-size:16px;"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_BY_SOURCE'); ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_SOURCE'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_FATAL'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_TOTAL'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_GROUPS'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($d['owners'] as $o) : ?>
                    <tr>
                        <td>
                            <span class="badge bg-<?php echo $type[$o['type']] ?? 'secondary'; ?>"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_T_' . strtoupper($o['type'])); ?></span>
                            <strong><?php echo $h($name($o)); ?></strong>
                        </td>
                        <td class="text-end"><?php echo $o['fatal'] > 0 ? '<span class="text-danger fw-bold">' . (int) $o['fatal'] . '</span>' : '0'; ?></td>
                        <td class="text-end"><?php echo (int) $o['count']; ?></td>
                        <td class="text-end"><?php echo (int) $o['groups']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="m-0" style="font-size:16px;"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_GROUPED'); ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_LEVEL'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_MESSAGE'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_SOURCE'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_COUNT'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_FIRST_LAST'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($d['groups'] as $g) : ?>
                    <tr>
                        <td><span class="badge bg-<?php echo $lvl[$g['level']] ?? 'secondary'; ?>"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_LV_' . strtoupper($g['level'])); ?></span></td>
                        <td style="max-width:520px;word-break:break-word;">
                            <div><?php echo $h($g['message']); ?></div>
                            <?php if ($g['file'] !== '') : ?>
                                <div class="text-muted small"><?php echo $h($g['file'] . ($g['line'] ? ':' . $g['line'] : '')); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $h($name($g['owner'])); ?></td>
                        <td class="text-end">
                            <?php echo (int) $g['count']; ?>
                            <?php if ($g['spike']) : ?><span class="badge bg-danger" title="<?php echo $h(Text::sprintf('COM_FGSYSTEMREPORT_ERRORS_SPIKE_TIP', $g['c24'], round($g['cprev'] / 6, 1))); ?>"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_SPIKE'); ?></span><?php endif; ?>
                        </td>
                        <td class="small text-nowrap"><?php echo $h($this->fmt($g['first'])); ?><br><?php echo $h($this->fmt($g['last'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

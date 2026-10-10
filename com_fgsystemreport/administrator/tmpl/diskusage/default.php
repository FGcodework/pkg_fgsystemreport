<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;

$dirs              = $this->dirs ?? [];
$total             = $this->total ?? 0;
$scanTimeFormatted = $this->scanTimeFormatted ?? null;
$topN              = (int) ($this->topN ?? 10);
$excludeRaw        = (string) ($this->excludeRaw ?? '');
$comparison        = $this->comparison ?? null;
$prevScanFormatted = $this->prevScanFormatted ?? null;

// Signed size difference, e.g. "+12.4 MB" / "-3.1 MB"; empty for no change
$fmtDelta = static function (int $d): string {
    if ($d === 0) {
        return '&ndash;';
    }
    $cls = $d > 0 ? 'text-danger' : 'text-success';
    return '<span class="' . $cls . '">' . ($d > 0 ? '+' : '&minus;') . FgsystemreportHelper::formatFileSize(abs($d)) . '</span>';
};

$rescanUrl = 'index.php?option=com_fgsystemreport&view=diskusage&rescan=1&top_n=' . $topN
    . '&exclude=' . urlencode($excludeRaw);
?>

<div class="card" id="jc-disk-usage">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0" style="font-size:16px;">
            <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DISKUSAGE_TOP_FOLDERS', $topN); ?>
        </h3>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($rescanUrl); ?>">
            <span class="icon-refresh me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_RESCAN'); ?>
        </a>
    </div>
    <div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="option" value="com_fgsystemreport">
            <input type="hidden" name="view" value="diskusage">
            <div class="col-auto">
                <label class="form-label small mb-1" for="top_n"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_FIELD_TOPN'); ?></label>
                <input type="number" id="top_n" name="top_n" min="1" max="50" value="<?php echo $topN; ?>"
                    class="form-control form-control-sm" style="width:90px;">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1" for="exclude"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_FIELD_EXCLUDE'); ?></label>
                <input type="text" id="exclude" name="exclude" autocomplete="off"
                    value="<?php echo htmlspecialchars($excludeRaw); ?>"
                    class="form-control form-control-sm" style="width:320px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_APPLY'); ?></button>
            </div>
        </form>

        <?php if (empty($dirs)) : ?>
            <p class="text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_EMPTY'); ?></p>
        <?php else : ?>
            <table class="table table-sm table-hover table-striped">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_COL_PATH'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_COL_SIZE'); ?></th>
                        <?php if ($comparison !== null) : ?>
                            <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_COL_CHANGE'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dirs as $dir => $size) : ?>
                        <tr>
                            <td class="text-monospace" style="font-family:monospace;">
                                <?php echo htmlspecialchars(substr($dir, strlen(JPATH_ROOT)) ?: '/'); ?>
                            </td>
                            <td class="text-end text-nowrap"><?php echo FgsystemreportHelper::formatFileSize($size); ?></td>
                            <?php if ($comparison !== null) : ?>
                                <td class="text-end text-nowrap">
                                    <?php if (($comparison['deltas'][$dir] ?? null) === null) : ?>
                                        <span class="badge bg-info text-dark"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_NEW_IN_LIST'); ?></span>
                                    <?php else : ?>
                                        <?php echo $fmtDelta((int) $comparison['deltas'][$dir]); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_TOTAL'); ?></th>
                        <th class="text-end text-nowrap"><?php echo FgsystemreportHelper::formatFileSize($total); ?></th>
                        <?php if ($comparison !== null) : ?>
                            <th class="text-end text-nowrap"><?php echo $fmtDelta((int) $comparison['total_delta']); ?></th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
            <?php if ($scanTimeFormatted !== null) : ?>
                <p class="text-muted mb-1">
                    <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DISKUSAGE_LAST_SCANNED', $scanTimeFormatted); ?>
                </p>
            <?php endif; ?>
            <p class="text-muted mb-0">
                <?php if ($comparison !== null && $prevScanFormatted !== null) : ?>
                    <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DISKUSAGE_COMPARED_TO', $prevScanFormatted); ?>
                <?php else : ?>
                    <?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_NO_PREVIOUS'); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

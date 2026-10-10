<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$iconsByFile = $this->iconsByFile ?? [];
$totalIcons  = 0;
foreach ($iconsByFile as $icons) {
    $totalIcons += count($icons);
}
?>

<div class="card" id="jc-icon-classes">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0" style="font-size:16px;">
            <?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_ICONS'); ?>
        </h3>
        <span class="badge bg-secondary"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_ICONS_TOTAL', $totalIcons); ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($iconsByFile)) : ?>
            <p class="text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_ICONS_NONE_FOUND'); ?></p>
        <?php else : ?>
            <input type="text" id="jc-icons-filter" autocomplete="off" name="jc-icons-filter-nofill"
                class="form-control form-control-sm mb-3" style="max-width:260px;"
                placeholder="<?php echo Text::_('COM_FGSYSTEMREPORT_ICONS_FILTER_PLACEHOLDER'); ?>">

            <?php foreach ($iconsByFile as $filePath => $icons) : ?>
                <h4 style="font-size:13px; color:#666; margin-top:20px;">
                    <?php echo htmlspecialchars(str_replace(JPATH_ROOT, '', $filePath)); ?>
                    <span class="text-muted">(<?php echo Text::sprintf('COM_FGSYSTEMREPORT_ICONS_COUNT_IN_FILE', count($icons)); ?>)</span>
                </h4>
                <div class="jc-icon-grid" style="display:flex; flex-wrap:wrap; gap:10px;">
                    <?php foreach ($icons as $icon) : ?>
                        <div class="jc-icon-cell" data-icon-name="<?php echo htmlspecialchars($icon); ?>"
                            style="display:flex; flex-direction:column; align-items:center; justify-content:center;
                                width:140px; padding:16px 8px; border:1px solid #ddd; border-radius:4px; font-size:13px; text-align:center;">
                            <i class="icon-<?php echo htmlspecialchars($icon); ?>" style="font-size:32px; margin-bottom:10px;" aria-hidden="true"></i>
                            <span style="word-break:break-word;"><?php echo htmlspecialchars($icon); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var filter = document.getElementById('jc-icons-filter');
    if (!filter) {
        return;
    }
    filter.addEventListener('input', function () {
        var needle = filter.value.toLowerCase();
        document.querySelectorAll('.jc-icon-cell').forEach(function (cell) {
            var name = cell.getAttribute('data-icon-name').toLowerCase();
            cell.style.display = name.indexOf(needle) !== -1 ? '' : 'none';
        });
    });
});
</script>

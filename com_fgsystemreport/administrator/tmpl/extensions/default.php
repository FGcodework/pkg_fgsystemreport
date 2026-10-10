<?php
/**
 * @version    CVS: 1.0.30
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$extList = $this->extensionsList ?? [];
$includeCoreList = (int) ($this->includeCoreList ?? 0);
$hideOfficialList = (int) ($this->hideOfficialList ?? 0);
$groupByType = (int) ($this->groupByType ?? 0);

$baseParams = '&include_core_list=' . $includeCoreList . '&hide_official_list=' . $hideOfficialList
    . '&group_by_type=' . $groupByType;

$coreToggleUrl = 'index.php?option=com_fgsystemreport&view=extensions&include_core_list=' . ($includeCoreList ? 0 : 1);
$officialToggleUrl = 'index.php?option=com_fgsystemreport&view=extensions&hide_official_list=' . ($hideOfficialList ? 0 : 1);
$groupToggleUrl = 'index.php?option=com_fgsystemreport&view=extensions&group_by_type=' . ($groupByType ? 0 : 1);
$checkUrl = 'index.php?option=com_fgsystemreport&task=fgsystemreport.findUpdates' . $baseParams
    . '&' . Joomla\CMS\Session\Session::getFormToken() . '=1';
$csvUrl = 'index.php?option=com_fgsystemreport&task=fgsystemreport.exportCsv' . $baseParams
    . '&' . Joomla\CMS\Session\Session::getFormToken() . '=1';

$quickFilter = (string) ($this->quickFilter ?? 'all');
$quickCounts = $this->quickCounts ?? [];
$updCount    = (int) ($quickCounts['updates'] ?? 0);
$quickModes  = [
    'all'      => 'COM_FGSYSTEMREPORT_EXT_QF_ALL',
    'updates'  => 'COM_FGSYSTEMREPORT_EXT_QF_UPDATES',
    'third'    => 'COM_FGSYSTEMREPORT_EXT_QF_THIRD',
    'disabled' => 'COM_FGSYSTEMREPORT_EXT_QF_DISABLED',
];

// Preferred display order of types when grouping
$typeOrder = ['package', 'component', 'module', 'plugin', 'template', 'library', 'language', 'file'];

/**
 * Render one table row for an extension. Shared by the flat and the
 * grouped-by-type layouts so the markup stays in one place.
 */
$renderRow = function ($ext, $index) {
    $rowClass = '';
    if ($ext->is_package) {
        $rowClass = 'table-warning';
    } elseif ($ext->has_update) {
        $rowClass = 'table-danger';
    }
    ?>
    <tr class="<?php echo $rowClass; ?>">
        <td class="text-center"><?php echo $index; ?></td>
        <td>
            <?php if ($ext->is_package) : ?>
                <strong><?php echo htmlspecialchars($ext->display_name); ?></strong>
            <?php elseif ($ext->is_child) : ?>
                <span class="text-muted ps-3">&raquo; <?php echo htmlspecialchars($ext->display_name); ?></span>
            <?php else : ?>
                <?php echo htmlspecialchars($ext->display_name); ?>
            <?php endif; ?>
        </td>
        <td>
            <?php
                $typeKey = 'COM_FGSYSTEMREPORT_TYPE_' . strtoupper($ext->type);
                $typeText = Text::_($typeKey);
                if ($typeText === $typeKey) {
                    $typeText = ucfirst($ext->type);
                }
                echo $ext->is_package ? '<strong>' . $typeText . '</strong>' : $typeText;
            ?>
            <?php if ($ext->is_child) : ?>
                <br><small class="text-muted"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_EXT_IN_PACKAGE', htmlspecialchars($ext->parent_package)); ?></small>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($ext->is_official) : ?>
                <span class="badge bg-info text-dark"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_OFFICIAL'); ?></span>
            <?php else : ?>
                <?php echo htmlspecialchars($ext->author !== '' ? $ext->author : '-'); ?>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <?php if ((int) $ext->enabled === 1) : ?>
                <span class="badge bg-success"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_ENABLED'); ?></span>
            <?php else : ?>
                <span class="badge bg-danger"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_DISABLED'); ?></span>
            <?php endif; ?>
        </td>
        <td class="text-center"><?php echo htmlspecialchars($ext->installed_version); ?></td>
        <td class="text-center">
            <?php if ($ext->has_update) : ?>
                <strong class="text-danger"><?php echo htmlspecialchars($ext->available_version); ?></strong>
                <br>
                <a href="index.php?option=com_installer&view=update&filter[extension_id]=<?php echo (int) $ext->extension_id; ?>"
                    class="badge bg-primary" style="text-decoration:none;" target="_blank"
                    title="<?php echo Text::_('COM_FGSYSTEMREPORT_EXT_GO_TO_UPDATE'); ?>">
                    <?php echo Text::_('COM_FGSYSTEMREPORT_EXT_GO_TO_UPDATE'); ?>
                </a>
            <?php else : ?>
                <?php echo htmlspecialchars($ext->available_version); ?>
            <?php endif; ?>
        </td>
    </tr>
    <?php
};
?>

<div class="card" id="jc-installed-extensions">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0" style="font-size:16px;">
            <?php echo Text::_('COM_FGSYSTEMREPORT_EXT_HEADING'); ?>
            <span class="badge bg-secondary"><?php echo count($extList); ?></span>
            <?php if ($updCount) : ?>
                <span class="badge bg-danger"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_EXT_UPDATES_AVAILABLE', $updCount); ?></span>
            <?php endif; ?>
        </h3>
        <div class="d-flex align-items-center gap-2">
            <input type="search" id="jc-ext-filter" autocomplete="off" name="jc-ext-filter-nofill" class="form-control form-control-sm" placeholder="<?php echo Text::_('COM_FGSYSTEMREPORT_EXT_FILTER_PLACEHOLDER'); ?>" style="width:180px;">
            <a class="btn btn-sm btn-outline-success" href="<?php echo $csvUrl; ?>">
                <span class="icon-download me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_EXPORT_CSV'); ?>
            </a>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo $checkUrl; ?>" id="jc-check-updates">
                <span class="icon-refresh me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_CHECK_UPDATES'); ?>
            </a>
            <a class="btn btn-sm <?php echo $groupByType ? 'btn-secondary' : 'btn-outline-secondary'; ?>" href="<?php echo $groupToggleUrl; ?>">
                <?php echo Text::_($groupByType ? 'COM_FGSYSTEMREPORT_EXT_FLAT_LIST' : 'COM_FGSYSTEMREPORT_EXT_GROUP_BY_TYPE'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $officialToggleUrl; ?>">
                <?php echo Text::_($hideOfficialList ? 'COM_FGSYSTEMREPORT_EXT_SHOW_OFFICIAL' : 'COM_FGSYSTEMREPORT_EXT_HIDE_OFFICIAL'); ?>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $coreToggleUrl; ?>">
                <?php echo Text::_($includeCoreList ? 'COM_FGSYSTEMREPORT_EXT_HIDE_CORE' : 'COM_FGSYSTEMREPORT_EXT_SHOW_CORE'); ?>
            </a>
        </div>
    </div>

    <div class="card-body border-bottom d-flex flex-wrap align-items-center gap-2 px-3 py-3" role="group"
        aria-label="<?php echo htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_EXT_QF_LABEL')); ?>">
        <span class="text-muted me-1"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_QF_LABEL'); ?>:</span>
        <?php foreach ($quickModes as $mode => $labelKey) : ?>
            <?php $active = ($quickFilter === $mode); ?>
            <a class="btn btn-sm rounded-pill px-3 <?php echo $active ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                href="index.php?option=com_fgsystemreport&view=extensions&quick_filter=<?php echo $mode; ?>"
                <?php echo $active ? 'aria-current="true"' : ''; ?>>
                <?php echo Text::_($labelKey); ?>
                <span class="ms-1 fw-normal" style="opacity:.75;">(<?php echo (int) ($quickCounts[$mode] ?? 0); ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$groupByType) : ?>

        <div class="card-body p-0">
            <table class="table table-sm table-hover m-0">
                <thead>
                    <tr>
                        <th style="width:4%;" class="text-center">#</th>
                        <th style="width:27%;"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_NAME'); ?></th>
                        <th style="width:14%;"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_TYPE'); ?></th>
                        <th style="width:15%;"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_AUTHOR'); ?></th>
                        <th style="width:10%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_STATE'); ?></th>
                        <th style="width:17%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_CURRENT_VERSION'); ?></th>
                        <th style="width:17%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_NEW_VERSION'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($extList as $ext) : ?>
                    <?php $renderRow($ext, $i++); ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else : ?>

        <?php
            // Group by extension type, preserving the existing package -> child order within each group
            $grouped = [];
            foreach ($extList as $ext) {
                $grouped[$ext->type][] = $ext;
            }
            uksort($grouped, static function ($a, $b) use ($typeOrder) {
                $pa = array_search($a, $typeOrder, true);
                $pb = array_search($b, $typeOrder, true);
                $pa = $pa === false ? 99 : $pa;
                $pb = $pb === false ? 99 : $pb;
                return $pa <=> $pb ?: strcmp($a, $b);
            });
        ?>

        <div class="card-body p-0">
        <?php foreach ($grouped as $type => $items) : ?>
            <?php
                $typeKey = 'COM_FGSYSTEMREPORT_TYPE_' . strtoupper($type);
                $typeLabel = Text::_($typeKey);
                if ($typeLabel === $typeKey) {
                    $typeLabel = ucfirst($type);
                }
            ?>
            <div class="p-2 bg-light border-top border-bottom fw-bold">
                <?php echo htmlspecialchars($typeLabel); ?>
                <span class="badge bg-secondary"><?php echo count($items); ?></span>
            </div>
            <table class="table table-sm table-hover m-0">
                <thead>
                    <tr>
                        <th style="width:4%;" class="text-center">#</th>
                        <th style="width:27%;"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_NAME'); ?></th>
                        <th style="width:14%;"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_TYPE'); ?></th>
                        <th style="width:15%;"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_AUTHOR'); ?></th>
                        <th style="width:10%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_STATE'); ?></th>
                        <th style="width:17%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_CURRENT_VERSION'); ?></th>
                        <th style="width:17%;" class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_NEW_VERSION'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($items as $ext) : ?>
                    <?php $renderRow($ext, $i++); ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var filter = document.getElementById('jc-ext-filter');
    var checkBtn = document.getElementById('jc-check-updates');
    if (checkBtn) {
        checkBtn.addEventListener('click', function () {
            this.classList.add('disabled');
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + <?php echo json_encode(Text::_('COM_FGSYSTEMREPORT_EXT_CHECKING')); ?>;
        });
    }

    if (!filter) { return; }
    filter.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#jc-installed-extensions tbody tr').forEach(function (tr) {
            tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
    });
});
</script>

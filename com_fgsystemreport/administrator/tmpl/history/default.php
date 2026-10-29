<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$runs     = $this->runs ?? [];
$days     = (int) $this->days;
$selected = $this->selected;
$previous = $this->previous;
$diff     = $this->diff ?: ['worse' => [], 'better' => [], 'added' => [], 'removed' => []];
$token    = Session::getFormToken();
$base     = 'index.php?option=com_fgsystemreport&view=history';

$statusBadge = [
    'ok'      => 'bg-success',
    'warning' => 'bg-warning text-dark',
    'danger'  => 'bg-danger',
    'info'    => 'bg-secondary',
];
$statusLabel = static function (string $s): string {
    return Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_' . strtoupper($s));
};
$events  = $this->events ?? [];
$evLabel = static function (string $t): string {
    return Text::_('COM_FGSYSTEMREPORT_CHANGE_T_' . strtoupper($t));
};
$fmt = static function (string $utc): string {
    return HTMLHelper::_('date', $utc, Text::_('DATE_FORMAT_LC5'));
};

// ---- SVG trend chart (no library) ----
$chart = '';
$n     = count($runs);

if ($n > 0) {
    $W = 800; $H = 220; $pl = 36; $pr = 12; $pt = 12; $pb = 28;
    $t0 = strtotime($runs[0]->run_at . ' UTC');
    $t1 = strtotime($runs[$n - 1]->run_at . ' UTC');
    $span = max(1, $t1 - $t0);
    $x = static function ($ts) use ($t0, $span, $n, $W, $pl, $pr) {
        return $n === 1 ? ($W / 2) : $pl + ($ts - $t0) / $span * ($W - $pl - $pr);
    };
    $y = static function ($score) use ($H, $pt, $pb) {
        return $pt + (100 - $score) / 100 * ($H - $pt - $pb);
    };

    $chart .= '<svg viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" role="img" aria-label="'
        . htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_HISTORY_CHART_LABEL')) . '" style="max-height:260px;">';

    foreach ([0, 25, 50, 75, 100] as $g) {
        $gy = round($y($g), 1);
        $chart .= '<line x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . $gy . '" y2="' . $gy
            . '" stroke="currentColor" stroke-opacity="0.15"/>'
            . '<text x="' . ($pl - 6) . '" y="' . ($gy + 4) . '" text-anchor="end" font-size="11" fill="currentColor" fill-opacity="0.7">' . $g . '</text>';
    }

    // Change markers (installs, updates, config changes ...) as dashed lines
    if ($n > 1) {
        foreach ($events as $ev) {
            $ets = strtotime($ev->created_at . ' UTC');

            if ($ets < $t0 || $ets > $t1) {
                continue;
            }

            $ex = round($x($ets), 1);
            $chart .= '<line x1="' . $ex . '" x2="' . $ex . '" y1="' . $pt . '" y2="' . ($H - $pb)
                . '" stroke="#fd7e14" stroke-opacity="0.55" stroke-dasharray="3 3"><title>'
                . htmlspecialchars($fmt($ev->created_at) . ' - ' . $evLabel($ev->type) . ': ' . $ev->subject . ($ev->detail !== '' ? ' (' . $ev->detail . ')' : ''))
                . '</title></line>';
        }
    }

    $pts = [];
    foreach ($runs as $r) {
        $pts[] = round($x(strtotime($r->run_at . ' UTC')), 1) . ',' . round($y((int) $r->score), 1);
    }

    if ($n > 1) {
        $chart .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#0d6efd" stroke-width="2"/>';
    }

    foreach ($runs as $i => $r) {
        [$cx, $cy] = explode(',', $pts[$i]);
        $isSel = $selected && (int) $selected->id === (int) $r->id;
        $chart .= '<a href="' . $base . '&amp;days=' . $days . '&amp;run=' . (int) $r->id . '">'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($isSel ? 6 : 4) . '" fill="' . ($isSel ? '#dc3545' : '#0d6efd') . '">'
            . '<title>' . htmlspecialchars($fmt($r->run_at) . ' - ' . Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_SCORE') . ' ' . (int) $r->score) . '</title></circle></a>';
    }

    $chart .= '<text x="' . $pl . '" y="' . ($H - 8) . '" font-size="11" fill="currentColor" fill-opacity="0.7">'
        . htmlspecialchars(HTMLHelper::_('date', $runs[0]->run_at, Text::_('DATE_FORMAT_LC4'))) . '</text>';

    if ($n > 1) {
        $chart .= '<text x="' . ($W - $pr) . '" y="' . ($H - 8) . '" text-anchor="end" font-size="11" fill="currentColor" fill-opacity="0.7">'
            . htmlspecialchars(HTMLHelper::_('date', $runs[$n - 1]->run_at, Text::_('DATE_FORMAT_LC4'))) . '</text>';
    }

    $chart .= '</svg>';
}
?>

<div class="card mb-3" id="jc-security-history">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="m-0" style="font-size:16px;"><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_HEADING'); ?></h3>
        <div>
            <?php foreach ([30, 90, 365] as $d) : ?>
                <a class="btn btn-sm <?php echo $d === $days ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                    href="<?php echo $base . '&amp;days=' . $d; ?>"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_HISTORY_RANGE_DAYS', $d); ?></a>
            <?php endforeach; ?>
            <a class="btn btn-sm btn-outline-secondary ms-2"
                href="index.php?option=com_fgsystemreport&task=fgsystemreport.exportHistoryCsv&days=<?php echo $days; ?>&<?php echo $token; ?>=1">
                <span class="icon-download me-1" aria-hidden="true"></span>CSV
            </a>
            <a class="btn btn-sm btn-outline-danger ms-1"
                href="index.php?option=com_fgsystemreport&task=fgsystemreport.clearHistory&<?php echo $token; ?>=1"
                onclick="return confirm('<?php echo htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_HISTORY_CLEAR_CONFIRM'), ENT_QUOTES); ?>');">
                <span class="icon-trash me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_CLEAR'); ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$runs) : ?>
            <p class="m-0"><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_EMPTY'); ?></p>
        <?php else : ?>
            <?php echo $chart; ?>
            <p class="text-muted mt-2 mb-0">
                <?php echo Text::sprintf('COM_FGSYSTEMREPORT_HISTORY_RETENTION_NOTE', (int) $this->retention); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($selected) : ?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="m-0" style="font-size:16px;">
            <?php echo Text::sprintf('COM_FGSYSTEMREPORT_HISTORY_COMPARE_HEADING', $fmt($selected->run_at), (int) $selected->score); ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if (!$previous) : ?>
            <p class="m-0"><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_NO_PREVIOUS'); ?></p>
        <?php elseif (!$diff['worse'] && !$diff['better'] && !$diff['added'] && !$diff['removed']) : ?>
            <p class="m-0"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_HISTORY_NO_CHANGES', $fmt($previous->run_at)); ?></p>
        <?php else : ?>
            <p class="text-muted"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_HISTORY_COMPARED_TO', $fmt($previous->run_at), (int) $previous->score); ?></p>
            <table class="table table-sm table-hover m-0">
                <tbody>
                <?php foreach ($diff['worse'] as [$label, $from, $to]) : ?>
                    <tr class="table-danger">
                        <td style="width:14%;"><span class="icon-arrow-down-4" aria-hidden="true"></span> <?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_WORSE'); ?></td>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td><span class="badge <?php echo $statusBadge[$from] ?? 'bg-secondary'; ?>"><?php echo $statusLabel($from); ?></span>
                            &rarr; <span class="badge <?php echo $statusBadge[$to] ?? 'bg-secondary'; ?>"><?php echo $statusLabel($to); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($diff['better'] as [$label, $from, $to]) : ?>
                    <tr class="table-success">
                        <td><span class="icon-arrow-up-4" aria-hidden="true"></span> <?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_BETTER'); ?></td>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td><span class="badge <?php echo $statusBadge[$from] ?? 'bg-secondary'; ?>"><?php echo $statusLabel($from); ?></span>
                            &rarr; <span class="badge <?php echo $statusBadge[$to] ?? 'bg-secondary'; ?>"><?php echo $statusLabel($to); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($diff['added'] as [$label, $status]) : ?>
                    <tr>
                        <td><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_ADDED'); ?></td>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td><span class="badge <?php echo $statusBadge[$status] ?? 'bg-secondary'; ?>"><?php echo $statusLabel($status); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($diff['removed'] as [$label, $status]) : ?>
                    <tr>
                        <td><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_REMOVED'); ?></td>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td><span class="badge <?php echo $statusBadge[$status] ?? 'bg-secondary'; ?>"><?php echo $statusLabel($status); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($runs) : ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-hover m-0">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_DATE'); ?></th>
                    <th class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_SCORE'); ?></th>
                    <th class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_CHANGE'); ?></th>
                    <th class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_DANGER'); ?></th>
                    <th class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_WARNING'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_SOURCE'); ?></th>
                    <th>Joomla / PHP</th>
                    <th class="text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_CHANGES'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $prevScore = null;
            $prevAt    = null;
            $rows = [];
            foreach ($runs as $r) {
                $rows[] = [$r, $prevScore === null ? null : (int) $r->score - $prevScore, $prevAt];
                $prevScore = (int) $r->score;
                $prevAt    = $r->run_at;
            }
            foreach (array_reverse($rows) as [$r, $delta, $pAt]) :
                $isSel = $selected && (int) $selected->id === (int) $r->id;
                $runEv = [];

                if ($pAt !== null) {
                    foreach ($events as $ev) {
                        if ($ev->created_at > $pAt && $ev->created_at <= $r->run_at) {
                            $runEv[] = $evLabel($ev->type) . ': ' . $ev->subject . ($ev->detail !== '' ? ' (' . $ev->detail . ')' : '');
                        }
                    }
                }
            ?>
                <tr class="<?php echo $isSel ? 'table-active' : ''; ?>">
                    <td><a href="<?php echo $base . '&amp;days=' . $days . '&amp;run=' . (int) $r->id; ?>"><?php echo htmlspecialchars($fmt($r->run_at)); ?></a></td>
                    <td class="text-center"><strong><?php echo (int) $r->score; ?></strong></td>
                    <td class="text-center">
                        <?php if ($delta === null || $delta === 0) : ?>
                            &ndash;
                        <?php elseif ($delta > 0) : ?>
                            <span class="text-success">&#9650; +<?php echo $delta; ?></span>
                        <?php else : ?>
                            <span class="text-danger">&#9660; <?php echo $delta; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo (int) $r->count_danger; ?></td>
                    <td class="text-center"><?php echo (int) $r->count_warning; ?></td>
                    <td><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_SOURCE_' . strtoupper($r->source)); ?></td>
                    <td><?php echo htmlspecialchars($r->joomla_version . ' / ' . $r->php_version); ?></td>
                    <td class="text-center"><?php echo $runEv ? '<span class="badge bg-warning text-dark" title="' . htmlspecialchars(implode("\n", $runEv)) . '">' . count($runEv) . '</span>' : '&ndash;'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-header"><h3 class="m-0" style="font-size:16px;"><?php echo Text::_('COM_FGSYSTEMREPORT_CHANGES_HEADING'); ?></h3></div>
    <div class="card-body<?php echo $events ? ' p-0' : ''; ?>">
        <?php if (!$events) : ?>
            <p class="mb-0 text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_CHANGES_NONE'); ?></p>
            <?php $diag = \Fg\Component\Fgsystemreport\Administrator\Helper\ChangeLogHelper::diagnose(); ?>
            <p class="mt-2 mb-0 small">
                <?php echo Text::sprintf('COM_FGSYSTEMREPORT_CHANGES_DIAG', Text::_('COM_FGSYSTEMREPORT_CHANGES_DIAG_PLUGIN_' . strtoupper($diag['plugin'])), Text::_($diag['table'] ? 'JYES' : 'JNO')); ?>
                <?php if ($diag['error'] !== '') : ?>
                    <br><?php echo Text::_('COM_FGSYSTEMREPORT_CHANGES_DIAG_ERROR'); ?> <code><?php echo htmlspecialchars($diag['error']); ?></code>
                <?php endif; ?>
            </p>
        <?php else : ?>
            <table class="table table-sm m-0">
                <thead><tr>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_HISTORY_COL_DATE'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_CHANGES_WHAT'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_CHANGES_DETAIL'); ?></th>
                    <th><?php echo Text::_('COM_FGSYSTEMREPORT_CHANGES_USER'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach (array_slice(array_reverse($events), 0, 60) as $ev) : ?>
                    <tr>
                        <td class="text-nowrap"><?php echo htmlspecialchars($fmt($ev->created_at)); ?></td>
                        <td><span class="badge <?php echo ['install' => 'bg-primary', 'update' => 'bg-success', 'uninstall' => 'bg-danger', 'enable' => 'bg-success', 'disable' => 'bg-secondary', 'config' => 'bg-warning text-dark'][$ev->type] ?? 'bg-secondary'; ?>"><?php echo htmlspecialchars($evLabel($ev->type)); ?></span> <?php echo htmlspecialchars($ev->subject); ?></td>
                        <td class="small"><?php echo htmlspecialchars($ev->detail); ?></td>
                        <td class="small"><?php echo htmlspecialchars($ev->user_name); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

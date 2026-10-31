<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Joomla\CMS\Language\Text;

$h     = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$score = $this->score;
$tone  = $score->score >= 80 ? '#198754' : ($score->score >= 50 ? '#b58100' : '#dc3545');
$badge = ['danger' => 'bg-danger', 'warning' => 'bg-warning text-dark', 'ok' => 'bg-success', 'info' => 'bg-secondary'];
$issues = array_values(array_filter($this->checks, static fn ($c) => in_array($c->status, ['danger', 'warning'], true)));
usort($issues, static fn ($a, $b) => ($a->status === $b->status) ? 0 : ($a->status === 'danger' ? -1 : 1));
$passed = array_values(array_filter($this->checks, static fn ($c) => $c->status === 'ok'));
$plain  = static fn ($s) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $s), ENT_QUOTES, 'UTF-8')));
$size   = static fn ($b) => FgsystemreportHelper::formatFileSize((int) $b);
$evLbl  = static fn ($t) => Text::_('COM_FGSYSTEMREPORT_CHANGE_T_' . strtoupper($t));
?>
<style>
.fg-report { max-width: 980px; margin: 0 auto; }
.fg-report h2 { font-size: 20px; }
.fg-report h3 { font-size: 16px; margin: 0; }
.fg-report .card { margin-bottom: 14px; }
.fg-report table { font-size: 13px; }
.fg-report .fg-score { font-size: 54px; font-weight: 700; line-height: 1; }
.fg-report td, .fg-report th { vertical-align: top; }
@media print {
    @page { margin: 12mm; }
    header#header, #header, .sidebar-wrapper, #sidebar-wrapper, #subhead-container, .subhead, .fg-noprint, joomla-alert, #system-message-container { display: none !important; }
    .container-main, #content, .container-fluid, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    .fg-report .card { break-inside: avoid; border: 1px solid #999; box-shadow: none; }
    .fg-report * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<div class="fg-report">
    <?php if (!$this->isPrint) : ?>
        <div class="card fg-noprint">
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3 align-items-end">
                    <input type="hidden" name="<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>" value="1">
                    <input type="hidden" name="option" value="com_fgsystemreport">
                    <input type="hidden" name="view" value="report">
                    <input type="hidden" name="go" value="1">
                    <div class="col-md-4">
                        <label class="form-label" for="fg-rep-client"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_FOR'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="fg-rep-client" name="client" maxlength="120" value="<?php echo $h($this->client); ?>">
                    </div>
                    <div class="col-md-8">
                        <?php foreach (['trend', 'changes', 'errors', 'storage', 'extensions', 'core', 'details'] as $o) : ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="fg-rep-<?php echo $o; ?>" name="o_<?php echo $o; ?>" value="1"<?php echo $this->opt[$o] ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="fg-rep-<?php echo $o; ?>"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_OPT_' . strtoupper($o)); ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="fg-rep-fresh" name="fresh" value="1">
                            <label class="form-check-label" for="fg-rep-fresh"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_OPT_FRESH'); ?></label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_APPLY'); ?></button>
                        <a class="btn btn-sm btn-outline-secondary" href="index.php?option=com_fgsystemreport&amp;view=fgsystemreport#fg-pdf-block"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_LINK_INVENTORY'); ?> &rarr;</a>
                        <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?php echo $h($this->printUrl); ?>"><span class="icon-print me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_OPEN_PRINT'); ?></a>
                    </div>
                </form>
            </div>
        </div>
    <?php else : ?>
        <div class="fg-noprint mb-3">
            <button type="button" class="btn btn-primary" onclick="window.print()"><span class="icon-print me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_PRINT'); ?></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_HEADING'); ?></h2>
                    <div><strong><?php echo $h($this->site); ?></strong> &middot; <?php echo $h($this->siteUrl); ?></div>
                    <?php if ($this->client !== '') : ?>
                        <div><?php echo Text::sprintf('COM_FGSYSTEMREPORT_REPORT_PREPARED_FOR', $h($this->client)); ?></div>
                    <?php endif; ?>
                    <div class="text-muted small mt-1">
                        <?php echo Text::sprintf('COM_FGSYSTEMREPORT_REPORT_GENERATED', $h($this->generated)); ?>
                        &middot; Joomla <?php echo $h(JVERSION); ?> &middot; PHP <?php echo $h(PHP_VERSION); ?>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-muted small"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_SCORE'); ?></div>
                    <div class="fg-score" style="color:<?php echo $tone; ?>;"><?php echo (int) $score->score; ?><span style="font-size:20px;color:#6c757d;">/100</span></div>
                    <div class="small"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_REPORT_COUNTS', (int) $score->dangerCount, (int) $score->warningCount, count($passed)); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_FINDINGS'); ?></h3></div>
        <div class="card-body<?php echo $issues ? ' p-0' : ''; ?>">
            <?php if (!$issues) : ?>
                <p class="mb-0"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_NO_FINDINGS'); ?></p>
            <?php else : ?>
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th style="width:90px;"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_COL_STATUS'); ?></th>
                        <th><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_COL_CHECK'); ?></th>
                        <?php if ($this->opt['details']) : ?><th><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_COL_NOTE'); ?></th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($issues as $c) : ?>
                        <tr>
                            <td><span class="badge <?php echo $badge[$c->status]; ?>"><?php echo Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_' . strtoupper($c->status)); ?></span></td>
                            <td><strong><?php echo $h($plain($c->label)); ?></strong><?php echo $plain($c->value) !== '' ? '<div class="text-muted small">' . $h($plain($c->value)) . '</div>' : ''; ?></td>
                            <?php if ($this->opt['details']) : ?><td><?php echo $h($plain($c->note)); ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($passed) : ?>
        <div class="card">
            <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_PASSED'); ?></h3></div>
            <div class="card-body small" style="columns:2;column-gap:24px;">
                <?php foreach ($passed as $c) : ?>
                    <div><span style="color:#198754;">&#10003;</span> <?php echo $h($plain($c->label)); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->opt['trend']) : ?>
        <div class="card">
            <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_TREND'); ?></h3></div>
            <div class="card-body">
                <?php
                $runs = $this->runs;
                $n    = count($runs);

                if ($n < 2) :
                    ?><p class="mb-0 text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_TREND_NONE'); ?></p><?php
                else :
                    $W = 700; $H = 120; $pl = 28; $pr = 8; $pt = 8; $pb = 8;
                    $t0 = strtotime($runs[0]->run_at . ' UTC');
                    $t1 = strtotime($runs[$n - 1]->run_at . ' UTC');
                    $span = max(1, $t1 - $t0);
                    $pts = [];

                    foreach ($runs as $r) {
                        $px = $pl + (strtotime($r->run_at . ' UTC') - $t0) / $span * ($W - $pl - $pr);
                        $py = $pt + (100 - (int) $r->score) / 100 * ($H - $pt - $pb);
                        $pts[] = round($px, 1) . ',' . round($py, 1);
                    }

                    $delta = (int) $runs[$n - 1]->score - (int) $runs[0]->score;
                    ?>
                    <svg viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" width="100%" style="max-height:140px;" role="img">
                        <?php foreach ([0, 50, 100] as $g) : $gy = $pt + (100 - $g) / 100 * ($H - $pt - $pb); ?>
                            <line x1="<?php echo $pl; ?>" x2="<?php echo $W - $pr; ?>" y1="<?php echo round($gy, 1); ?>" y2="<?php echo round($gy, 1); ?>" stroke="#999" stroke-opacity="0.35"/>
                            <text x="<?php echo $pl - 4; ?>" y="<?php echo round($gy, 1) + 4; ?>" text-anchor="end" font-size="10" fill="#666"><?php echo $g; ?></text>
                        <?php endforeach; ?>
                        <polyline points="<?php echo implode(' ', $pts); ?>" fill="none" stroke="#0d6efd" stroke-width="2"/>
                    </svg>
                    <div class="small text-muted">
                        <?php echo $h($this->fmtUtc($runs[0]->run_at)); ?> &rarr; <?php echo $h($this->fmtUtc($runs[$n - 1]->run_at)); ?>:
                        <strong style="color:<?php echo $delta < 0 ? '#dc3545' : ($delta > 0 ? '#198754' : 'inherit'); ?>;"><?php echo (int) $runs[0]->score; ?> &rarr; <?php echo (int) $runs[$n - 1]->score; ?> (<?php echo ($delta > 0 ? '+' : '') . $delta; ?>)</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->opt['changes']) : ?>
        <div class="card">
            <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_CHANGES'); ?></h3></div>
            <div class="card-body<?php echo $this->events ? ' p-0' : ''; ?>">
                <?php if (!$this->events) : ?>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_CHANGES_NONE'); ?></p>
                <?php else : ?>
                    <table class="table table-sm mb-0"><tbody>
                    <?php foreach (array_slice(array_reverse($this->events), 0, 25) as $ev) : ?>
                        <tr>
                            <td class="text-nowrap"><?php echo $h($this->fmtUtc($ev->created_at)); ?></td>
                            <td><?php echo $h($evLbl($ev->type)); ?>: <strong><?php echo $h($ev->subject); ?></strong><?php echo $ev->detail !== '' ? ' <span class="text-muted">' . $h(\Fg\Component\Fgsystemreport\Administrator\Helper\ChangeLogHelper::detailLabel($ev->detail)) . '</span>' : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->opt['errors']) : ?>
        <div class="card">
            <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_ERRORS'); ?></h3></div>
            <div class="card-body<?php echo ($this->errors['owners'] ?? []) ? ' p-0' : ''; ?>">
                <?php if (empty($this->errors['files']) || empty($this->errors['owners'])) : ?>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_ERRORS_NONE'); ?></p>
                <?php else : ?>
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_SOURCE'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_FATAL'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_TOTAL'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach (array_slice($this->errors['owners'], 0, 8) as $o) : ?>
                            <tr>
                                <td><?php echo $h($o['name'] !== '' ? $o['name'] : Text::_('COM_FGSYSTEMREPORT_ERRORS_UNKNOWN')); ?> <span class="text-muted">(<?php echo Text::_('COM_FGSYSTEMREPORT_ERRORS_T_' . strtoupper($o['type'])); ?>)</span></td>
                                <td class="text-end"><?php echo (int) $o['fatal']; ?></td>
                                <td class="text-end"><?php echo (int) $o['count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->opt['storage']) : ?>
        <div class="card">
            <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_STORAGE'); ?></h3></div>
            <div class="card-body">
                <?php if (!$this->disk && !$this->db) : ?>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_STORAGE_NONE'); ?></p>
                <?php else : ?>
                    <div class="row g-3">
                        <?php foreach ([['disk', $this->disk, isset($this->disk->dirs) ? $this->disk->dirs : [], 'COM_FGSYSTEMREPORT_REPORT_DISK_TOP', 'scanned_at'],
                            ['db', $this->db, isset($this->db->tables) ? $this->db->tables : [], 'COM_FGSYSTEMREPORT_REPORT_DB_TOP', 'scanned_at']] as [$k, $snap, $rows, $title, $dateField]) :
                            if (!$snap) {
                                continue;
                            }

                            arsort($rows);
                            ?>
                            <div class="col-md-6">
                                <div class="fw-bold mb-1"><?php echo Text::sprintf($title, $h($this->fmtUtc($snap->$dateField)), $h($size($snap->total))); ?></div>
                                <table class="table table-sm mb-0"><tbody>
                                <?php foreach (array_slice($rows, 0, 10, true) as $name => $bytes) : ?>
                                    <tr><td style="word-break:break-all;"><?php echo $h($name); ?></td><td class="text-end text-nowrap"><?php echo $h($size($bytes)); ?></td></tr>
                                <?php endforeach; ?>
                                </tbody></table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->opt['extensions']) : ?>
        <?php
        $exts    = $this->extensions;
        $upd     = count(array_filter($exts, static fn ($e) => !empty($e->has_update)));
        $off     = count(array_filter($exts, static fn ($e) => (int) $e->enabled !== 1));
        ?>
        <div class="card">
            <div class="card-header"><h3><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_EXTENSIONS'); ?></h3></div>
            <div class="card-body<?php echo $exts ? ' p-0' : ''; ?>">
                <?php if (!$exts) : ?>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_EXT_NONE'); ?></p>
                <?php else : ?>
                    <p class="small px-3 pt-2 mb-1"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_REPORT_EXT_SUMMARY', count($exts), $upd, $off); ?></p>
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_NAME'); ?></th>
                            <th><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_TYPE'); ?></th>
                            <th><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_STATE'); ?></th>
                            <th><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_CURRENT_VERSION'); ?></th>
                            <th><?php echo Text::_('COM_FGSYSTEMREPORT_EXT_COL_NEW_VERSION'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($exts as $e) :
                            $tk = 'COM_FGSYSTEMREPORT_TYPE_' . strtoupper($e->type);
                            $tl = Text::_($tk) === $tk ? ucfirst($e->type) : Text::_($tk);
                            ?>
                            <tr style="break-inside:avoid;<?php echo !empty($e->has_update) ? 'background:#fdecea;' : ''; ?>">
                                <td><?php echo !empty($e->is_child) ? '<span class="text-muted">&raquo;</span> ' : ''; ?><?php echo !empty($e->is_package) ? '<strong>' . $h($e->display_name) . '</strong>' : $h($e->display_name); ?></td>
                                <td><?php echo $h($tl); ?></td>
                                <td><?php echo (int) $e->enabled === 1 ? Text::_('COM_FGSYSTEMREPORT_EXT_ENABLED') : '<strong style="color:#dc3545;">' . Text::_('COM_FGSYSTEMREPORT_EXT_DISABLED') . '</strong>'; ?></td>
                                <td><?php echo $h($e->installed_version); ?></td>
                                <td><?php echo !empty($e->has_update) ? '<strong style="color:#dc3545;">' . $h($e->available_version) . '</strong>' : $h($e->available_version); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <p class="text-muted small text-center"><?php echo Text::_('COM_FGSYSTEMREPORT_REPORT_FOOTER'); ?></p>
</div>

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
use Fg\Component\Fgsystemreport\Administrator\Helper\CoreIntegrityHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Fg\Component\Fgsystemreport\Administrator\Helper\SecurityGroupHelper;

$checks = $this->checks ?? [];
$groups = SecurityGroupHelper::group($checks);

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
$core     = $this->core ?? null;
$coreEval = CoreIntegrityHelper::evaluate($core, JVERSION, time());
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
            <a class="btn btn-sm btn-outline-primary fg-busy" data-fg-busy-text="<?php echo $this->escape(Text::_('COM_FGSYSTEMREPORT_SEC_RECHECKING')); ?>" href="index.php?option=com_fgsystemreport&view=security&force=1&<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>=1">
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
    <div class="card mb-3" id="fgsr-core">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <strong><?php echo Text::_('COM_FGSYSTEMREPORT_CORE_TITLE'); ?></strong>
                    <div class="small text-muted">
                        <?php if ($coreEval['code'] === 'never') : ?>
                            <?php echo Text::_('COM_FGSYSTEMREPORT_CORE_NEVER'); ?>
                        <?php else : ?>
                            <?php echo Text::sprintf('COM_FGSYSTEMREPORT_CORE_LAST', $this->escape((string) $core['version']), HTMLHelper::_('date', gmdate('Y-m-d H:i:s', (int) $core['time']), Text::_('DATE_FORMAT_LC5')), (int) $core['checked'], (int) $core['nModified'], (int) $core['nMissing']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="fgsr-core-run">
                    <span class="icon-refresh me-1" aria-hidden="true"></span><?php echo Text::_('COM_FGSYSTEMREPORT_CORE_RUN'); ?>
                </button>
            </div>
            <div class="progress mt-2 d-none" id="fgsr-core-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" style="width:0%"></div></div>
            <div class="small mt-2" id="fgsr-core-msg" aria-live="polite"></div>
            <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_FGSYSTEMREPORT_CORE_EXPLAIN'); ?></p>
            <?php if ($core && ($core['nModified'] || $core['nMissing']) && $core['version'] === JVERSION) : ?>
                <details class="mt-2">
                    <summary class="small" style="cursor:pointer;"><?php echo Text::_('COM_FGSYSTEMREPORT_CORE_FILES_LIST'); ?></summary>
                    <?php if ($core['modified']) : ?>
                        <div class="small fw-bold mt-2"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_CORE_MODIFIED_HEAD', (int) $core['nModified']); ?></div>
                        <ul class="small mb-1" style="max-height:260px;overflow:auto;"><?php foreach ($core['modified'] as $f) : ?><li><code><?php echo $this->escape($f); ?></code></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <?php if ($core['missing']) : ?>
                        <div class="small fw-bold mt-2"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_CORE_MISSING_HEAD', (int) $core['nMissing']); ?></div>
                        <ul class="small mb-1" style="max-height:260px;overflow:auto;"><?php foreach ($core['missing'] as $f) : ?><li><code><?php echo $this->escape($f); ?></code></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <?php if ($core['nModified'] > count($core['modified']) || $core['nMissing'] > count($core['missing'])) : ?>
                        <div class="small text-muted"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_CORE_LIST_CAPPED', 500); ?></div>
                    <?php endif; ?>
                </details>
            <?php endif; ?>
        </div>
    </div>
    <nav class="d-flex flex-wrap gap-2 mb-2" aria-label="<?php echo $this->escape(Text::_('COM_FGSYSTEMREPORT_SECGROUP_NAV')); ?>">
        <?php foreach ($groups as $gid => $rows) : $n = SecurityGroupHelper::counts($rows); ?>
            <a class="btn btn-sm btn-outline-secondary" href="#fgsr-g-<?php echo $gid; ?>">
                <?php echo Text::_('COM_FGSYSTEMREPORT_SECGROUP_' . strtoupper($gid)); ?>
                <?php if ($n['danger']) : ?><span class="badge bg-danger"><?php echo (int) $n['danger']; ?></span><?php endif; ?>
                <?php if ($n['warning']) : ?><span class="badge bg-warning text-dark"><?php echo (int) $n['warning']; ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

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
            <?php foreach ($groups as $gid => $rows) : $n = SecurityGroupHelper::counts($rows); ?>
            <tbody>
                <tr id="fgsr-g-<?php echo $gid; ?>">
                    <th colspan="4" class="bg-body-tertiary">
                        <?php echo Text::_('COM_FGSYSTEMREPORT_SECGROUP_' . strtoupper($gid)); ?>
                        <span class="text-muted fw-normal small">(<?php echo (int) $n['total']; ?>)</span>
                        <?php if ($n['danger']) : ?><span class="badge bg-danger ms-1"><?php echo (int) $n['danger']; ?></span><?php endif; ?>
                        <?php if ($n['warning']) : ?><span class="badge bg-warning text-dark ms-1"><?php echo (int) $n['warning']; ?></span><?php endif; ?>
                    </th>
                </tr>
            <?php foreach ($rows as $check) : ?>
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
                        <?php elseif (!empty($check->accept_expired)) : ?>
                            <br><span class="badge bg-warning text-dark mt-1"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_EXPIRED_BADGE'); ?></span>
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
                                    <select name="expire_days" class="form-select form-select-sm w-auto" aria-label="<?php echo $this->escape(Text::_('COM_FGSYSTEMREPORT_ACCEPTED_EXPIRE_LABEL')); ?>" title="<?php echo $this->escape(Text::_('COM_FGSYSTEMREPORT_ACCEPTED_EXPIRE_LABEL')); ?>">
                                        <?php foreach ([30, 90, 180, 365] as $d) : ?>
                                            <option value="<?php echo $d; ?>"<?php echo $d === 90 ? ' selected' : ''; ?>><?php echo Text::sprintf('COM_FGSYSTEMREPORT_ACCEPTED_DAYS', $d); ?></option>
                                        <?php endforeach; ?>
                                        <option value="0"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_NO_EXPIRY'); ?></option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo Text::_('COM_FGSYSTEMREPORT_ACCEPTED_SAVE'); ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('fgsr-core-run');
    if (!btn || !window.fetch) { return; }
    var bar = document.getElementById('fgsr-core-progress'), msg = document.getElementById('fgsr-core-msg');
    var token = <?php echo json_encode(\Joomla\CMS\Session\Session::getFormToken()); ?>;
    var url = 'index.php?option=com_fgsystemreport&task=coreintegrity.step&format=json';
    var after = 'index.php?option=com_fgsystemreport&view=security&force=1&' + token + '=1';
    var failed = <?php echo json_encode(Text::_('COM_FGSYSTEMREPORT_CORE_ERR_REQUEST')); ?>;
    var started = false;
    function show(text, bad) { msg.textContent = text; msg.className = 'small mt-2' + (bad ? ' text-danger' : ''); }
    function progress(p) {
        bar.classList.remove('d-none');
        bar.setAttribute('aria-valuenow', p);
        bar.firstElementChild.style.width = p + '%';
    }
    function call(fresh) {
        var fd = new FormData();
        fd.append(token, '1');
        if (fresh) { fd.append('fresh', '1'); }
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function loop(fresh) {
        call(fresh).then(function (d) {
            progress(d.progress || 0);
            if (d.error) { show(d.message, true); btn.disabled = false; return; }
            show(d.message, false);
            if (d.done) { window.location.href = after; return; }
            loop(false);
        }).catch(function () { show(failed, true); btn.disabled = false; });
    }
    btn.addEventListener('click', function () {
        btn.disabled = true;
        progress(0);
        show('', false);
        loop(true);
    });
})();
</script>

<?php BusyHelper::render(); ?>

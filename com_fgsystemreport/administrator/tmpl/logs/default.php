<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Joomla\CMS\Language\Text;

$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$r = $this->result;
?>
<div class="card">
    <div class="card-header">
        <h3 class="m-0" style="font-size:16px;"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_HEADING'); ?></h3>
    </div>
    <div class="card-body">
        <?php if (!$this->files) : ?>
            <p class="mb-0"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_NO_FILES'); ?></p>
        <?php else : ?>
            <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="option" value="com_fgsystemreport">
                <input type="hidden" name="view" value="logs">
                <div class="col-auto">
                    <label class="form-label mb-0" for="fg-log-file"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_FILE'); ?></label>
                    <select class="form-select form-select-sm" name="file" id="fg-log-file" onchange="this.form.submit()">
                        <?php foreach ($this->files as $f) : ?>
                            <option value="<?php echo $h($f['name']); ?>"<?php echo $f['name'] === $this->selected ? ' selected' : ''; ?>>
                                <?php echo $h($f['name'] . ' (' . FgsystemreportHelper::formatFileSize($f['size']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0" for="fg-log-q"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_FILTER'); ?></label>
                    <input type="search" class="form-control form-control-sm" name="q" id="fg-log-q" value="<?php echo $h($this->filter); ?>" placeholder="ERROR">
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0" for="fg-log-lines"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_LINES'); ?></label>
                    <select class="form-select form-select-sm" name="lines" id="fg-log-lines">
                        <?php foreach ([100, 300, 1000] as $n) : ?>
                            <option value="<?php echo $n; ?>"<?php echo $n === $this->lines ? ' selected' : ''; ?>><?php echo $n; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('COM_FGSYSTEMREPORT_DISKUSAGE_APPLY'); ?></button>
                </div>
            </form>

            <p class="text-muted small">
                <?php echo $h($this->dir); ?>
                <?php if ($this->modified !== '') : ?> &middot; <?php echo Text::sprintf('COM_FGSYSTEMREPORT_LOGS_MODIFIED', $h($this->modified)); ?><?php endif; ?>
                <?php if ($r && $r['ok']) : ?> &middot; <?php echo Text::sprintf('COM_FGSYSTEMREPORT_LOGS_SHOWING', count($r['lines']), $r['matched']); ?><?php endif; ?>
                <?php if ($r && $r['truncated']) : ?> &middot; <?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_TRUNCATED'); ?><?php endif; ?>
            </p>

            <?php if ($r && !$r['ok']) : ?>
                <div class="alert alert-danger"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_ERROR'); ?></div>
            <?php elseif ($r && !$r['lines']) : ?>
                <p class="mb-0"><?php echo Text::_('COM_FGSYSTEMREPORT_LOGS_EMPTY'); ?></p>
            <?php elseif ($r) : ?>
                <pre class="border rounded p-2" style="max-height:70vh;overflow:auto;font-size:12px;line-height:1.45;white-space:pre-wrap;word-break:break-word;"><?php
                foreach ($r['lines'] as $line) {
                    $cls = '';

                    if (preg_match('/\b(CRITICAL|ALERT|EMERGENCY|ERROR)\b/', $line)) {
                        $cls = 'text-danger';
                    } elseif (preg_match('/\bWARNING\b/', $line)) {
                        $cls = 'text-warning';
                    }

                    echo $cls !== '' ? '<span class="' . $cls . '">' . $h($line) . "</span>\n" : $h($line) . "\n";
                }
                ?></pre>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
\Fg\Component\Fgsystemreport\Administrator\Helper\GrowthAlertHelper::renderBanner();
?>
<div>
    <?php
        $helperClass = \Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper::class;
        // Fetch the full extensions list (including core) ONCE and reuse it
        // everywhere on this page - for the "Extensions" stat box (filtered
        // down to the non-core view in PHP below, same result as calling
        // getInstalledExtensions(false) again) and passed into
        // getSecurityChecks() so it doesn't repeat the same full table scan
        // (with a lang->load() per extension) a second time internally.
        $dashExtListAll = $helperClass::getInstalledExtensions(true);
        $dashExtList    = array_values(array_filter($dashExtListAll, static function ($ext) {
            return empty($ext->locked);
        }));
        $dashUpdateCount = 0;
        foreach ($dashExtList as $dashExt) {
            if ($dashExt->has_update) {
                $dashUpdateCount++;
            }
        }
        $dashLatestJoomla   = $helperClass::fetchLatestJoomlaVersion();
        $dashSecurityChecks = $helperClass::getSecurityChecks($dashLatestJoomla, $dashExtListAll);
        $dashSecCounts      = ['warning' => 0, 'danger' => 0];
        foreach ($dashSecurityChecks as $dashCheck) {
            if (isset($dashSecCounts[$dashCheck->status])) {
                $dashSecCounts[$dashCheck->status]++;
            }
        }
    ?>
    <div class="fgsr-tiles">
        <a class="fgsr-tile <?php echo $dashUpdateCount > 0 ? 'is-warn' : 'is-ok'; ?>" href="index.php?option=com_fgsystemreport&view=extensions">
            <span class="fgsr-tile-title"><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_EXTENSIONS'); ?></span>
            <span class="fgsr-tile-value">
                <?php if ($dashUpdateCount > 0) : ?>
                    <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_EXT_UPDATES_AVAILABLE', $dashUpdateCount); ?>
                <?php else : ?>
                    <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_EXT_UP_TO_DATE'); ?>
                <?php endif; ?>
            </span>
            <span class="fgsr-tile-more"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_VIEW_MORE', Text::_('COM_FGSYSTEMREPORT_TITLE_EXTENSIONS')); ?> &rarr;</span>
        </a>
        <a class="fgsr-tile <?php echo $dashSecCounts['danger'] > 0 ? 'is-bad' : ($dashSecCounts['warning'] > 0 ? 'is-warn' : 'is-ok'); ?>" href="index.php?option=com_fgsystemreport&view=security">
            <span class="fgsr-tile-title"><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_SECURITY'); ?></span>
            <span class="fgsr-tile-value">
                <?php if ($dashSecCounts['danger'] > 0) : ?>
                    <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_SEC_CRITICAL', $dashSecCounts['danger']); ?>
                <?php elseif ($dashSecCounts['warning'] > 0) : ?>
                    <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_SEC_WARNINGS', $dashSecCounts['warning']); ?>
                <?php else : ?>
                    <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_SEC_NO_ISSUES'); ?>
                <?php endif; ?>
            </span>
            <span class="fgsr-tile-more"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_VIEW_MORE', Text::_('COM_FGSYSTEMREPORT_TITLE_SECURITY')); ?> &rarr;</span>
        </a>
        <a class="fgsr-tile" href="index.php?option=com_fgsystemreport&view=report">
            <span class="fgsr-tile-title"><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_REPORT'); ?></span>
            <span class="fgsr-tile-value"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_TILE_REPORT'); ?></span>
            <span class="fgsr-tile-more"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_VIEW_MORE', Text::_('COM_FGSYSTEMREPORT_TITLE_REPORT')); ?> &rarr;</span>
        </a>
    </div>
    <!-- SECTION 2: System Information & Statistics -->
    <div class="fgsr-sec-head">
        <div>
            <div class="fgsr-kicker"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_KICKER_SITE'); ?></div>
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STATS_HEADING'); ?></h3>
        </div>
        <button type="button" id="copySystemInfo" class="btn btn-sm btn-outline-primary">
            <i class="icon-copy"></i> <span id="copyText"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_COPY_BTN'); ?></span>
        </button>
    </div>
    <div class="fgsr-stats jc-audit-wrapper">
        <div class="fgsr-stat jc-audit-box">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_CATEGORIES'); ?></h3>
            <p class="fgsr-stat-value"><?php echo $this->reportdata['total_categories']; ?></p>
        </div>
        <div class="fgsr-stat jc-audit-box">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_ARTICLES'); ?></h3>
            <p class="fgsr-stat-value"><?php echo $this->reportdata['total_articles']; ?></p>
        </div>
        <div class="fgsr-stat jc-audit-box">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_USERS'); ?></h3>
            <p class="fgsr-stat-value"><?php echo $this->reportdata['total_users']; ?></p>
        </div>
        <!--Total Published Menus-->
        <div class="fgsr-stat jc-audit-box">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_MENUS'); ?></h3>
            <p class="fgsr-stat-value">
                <?php 
                $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                $query = $db->getQuery(true)
                    ->select($db->quoteName('id')) // Select only the id field for counting
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('published') . ' = 1')
                    ->where($db->quoteName('menutype') . ' != ' . $db->quote('main')) // Exclude 'main' menu type
                    ->where($db->quoteName('title') . ' != ' . $db->quote('Menu_Item_Root')); // Exclude 'Menu_Item_Root' title
                $db->setQuery($query);
                $menus = $db->loadObjectList();
                echo '<strong>' . count($menus) . '</strong>';
                ?>
            </p>
        </div>
        <!--Total Published Menus-->
        <!--Total Tags-->
        <div class="fgsr-stat jc-audit-box">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_TAGS'); ?></h3>
            <p class="fgsr-stat-value">
                <?php 
                $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                $query = $db->getQuery(true)
                    ->select($db->quoteName('id')) // Select only the id field for counting
                    ->from($db->quoteName('#__tags'))
                    ->where($db->quoteName('published') . ' = 1')
                    ->where($db->quoteName('title') . ' != ' . $db->quote('root')); // Exclude tags with title 'root'
                $db->setQuery($query);
                $tags = $db->loadObjectList();
                echo '<strong>' . count($tags) . '</strong> ';
                ?>
            </p>
        </div>
        <!--Total Tags-->
        <div class="fgsr-stat jc-audit-box" id="box_extensions" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_EXTENSIONS'); ?></h3>
            <p class="fgsr-stat-value">
                <?php
                echo '<strong>' . count($dashExtList) . '</strong>';
                ?>
            </p>
        </div>
        <div class="fgsr-stat jc-audit-box" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_PHP_VERSION'); ?></h3>
            <p class="fgsr-stat-value"><?php echo phpversion(); ?></p>
        </div>
        <div class="fgsr-stat jc-audit-box" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_MYSQL_VERSION'); ?></h3>
            <p class="fgsr-stat-value">
                <?php 
                    $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                    echo htmlspecialchars($db->getVersion());
                ?>
            </p>
        </div>
        <div class="fgsr-stat jc-audit-box" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_JOOMLA_VERSION'); ?></h3>
            <p class="fgsr-stat-value">
                <?php 
                    $jversion = new \Joomla\CMS\Version();
                    $joomlaVersion = $jversion->getShortVersion();
                    $jvClass = ($dashLatestJoomla && version_compare($joomlaVersion, (string) $dashLatestJoomla, '<')) ? 'text-warning' : ($dashLatestJoomla ? 'text-success' : '');
                    echo '<span class="' . $jvClass . '">' . htmlspecialchars($joomlaVersion) . '</span>';
                ?>
            </p>
        </div>
        <!-- Latest Joomla version -->
        <div class="fgsr-stat jc-audit-box" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_NEXT_JOOMLA_VERSION'); ?></h3>
            <p class="fgsr-stat-value">
                <?php
                $latestJoomla = $dashLatestJoomla;
                if ($latestJoomla !== null) {
                    echo '<span class="text-success">' . htmlspecialchars($latestJoomla) . '</span>';
                } else {
                    echo '<span class="text-danger">' . Text::_('COM_FGSYSTEMREPORT_DASH_VERSION_CHECK_FAILED') . '</span>';
                }
                ?>
            </p>
        </div>
        <!-- Latest Joomla version -->
        <!--Total LANGUAGES -->
        <div class="fgsr-stat jc-audit-box" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_LANGUAGES'); ?></h3>
            <p class="fgsr-stat-value">
                <?php
                    $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                    $query = $db->getQuery(true)
                        ->select($db->quoteName(['title']))
                        ->from($db->quoteName('#__languages'))
                        ->where($db->quoteName('published') . ' = 1');
                    $db->setQuery($query);
                    $languages = $db->loadColumn();
                    $langs = array_map(function($t){ return trim(strstr($t, '(', true)); }, $languages);
                    // echo '<strong>' . count($langs) . '</strong><br>';
                    if (count($langs) > 1) echo htmlspecialchars(implode(', ', $langs));
                    elseif (!empty($langs)) echo htmlspecialchars($langs[0]);
                ?>
            </p>
        </div>
        <div class="fgsr-stat jc-audit-box" data-copy="1">
            <h3 class="fgsr-stat-label"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_CURRENT_TEMPLATE'); ?></h3>
            <p class="fgsr-stat-value">
                <?php
                // Get current frontend template
                $query = $db->getQuery(true)
                    ->select($db->quoteName('template'))
                    ->from($db->quoteName('#__template_styles'))
                    ->where($db->quoteName('client_id') . ' = 0') // Frontend
                    ->where($db->quoteName('home') . ' = 1'); // Default template
                $db->setQuery($query);
                $defaultTemplate = $db->loadResult();
                echo '<span>' . htmlspecialchars((string) $defaultTemplate) . '</span>';
                ?>
            </p>
        </div>
    </div>
    <!-- SECTION 1: PDF Download Configuration -->
    <!-- Download report option as CSV or PDF -->
    <div class="card" id="fg-pdf-block"><div class="card-body">
        <form method="post" action="index.php?option=com_fgsystemreport&task=fgsystemreport.generatePDF" style="margin:0;">
            <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
            <div class="fgsr-sec-head" style="margin-top:0;">
                <div>
                    <div class="fgsr-kicker"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_KICKER_PDF'); ?></div>
                    <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_HEADING'); ?></h3>
                    <a href="index.php?option=com_fgsystemreport&amp;view=report" class="small"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_LINK_HEALTH_REPORT'); ?> &rarr;</a>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="email" name="pdf_email_recipient" class="form-control form-control-sm" style="width:230px;" placeholder="<?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_EMAIL_PLACEHOLDER'); ?>">
                    <button type="submit" formaction="index.php?option=com_fgsystemreport&task=fgsystemreport.emailPDF" class="btn btn-sm btn-outline-primary">
                        <i class="icon-envelope"></i> <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_EMAIL_BTN'); ?>
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="icon-download"></i> <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_DOWNLOAD_BTN'); ?>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                 <div class="row">
                    <div class="col-sm-12">
                        <!-- Group checkboxes into a form group with margin bottom for better spacing -->
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input  class="form-check-input" id="include_core" type="checkbox" name="include_core" value="1">
                                    <label class="form-check-label" for="include_core"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_INCLUDE_CORE'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_system_info" type="checkbox" name="show_system_info" value="1">
                                    <label class="form-check-label" for="show_system_info"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SERVER_INFO'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_joomla_info" type="checkbox" name="show_joomla_info" value="1">
                                    <label class="form-check-label" for="show_joomla_info"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_JOOMLA_INFO'); ?></label>
                                </div>
                            </div>

                        </div>
                        <!-- Additional group of checkboxes for cleaner spacing -->
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_version" type="checkbox" name="show_joomla_version" value="1">
                                    <label class="form-check-label" for="show_version"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_JOOMLA_VERSION'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_domain" type="checkbox" name="show_domain" value="1">
                                    <label class="form-check-label" for="show_domain"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_DOMAIN'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_categories" type="checkbox" name="show_categories" value="1">
                                    <label class="form-check-label" for="show_categories"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_CATEGORIES'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_articles" type="checkbox" name="show_articles" value="1">
                                    <label class="form-check-label" for="show_articles"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_ARTICLES'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_users" type="checkbox" name="show_users" value="1">
                                    <label class="form-check-label" for="show_users"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_USERS'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_menus" type="checkbox" name="show_menus" value="1">
                                    <label class="form-check-label" for="show_menus"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_MENUS'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_tags" type="checkbox" name="show_tags" value="1">
                                    <label class="form-check-label" for="show_tags"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_TAGS'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_extensions" type="checkbox" name="show_extensions_count" value="1">
                                    <label class="form-check-label" for="show_extensions_count"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_EXTENSIONS'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_template_overrides" type="checkbox" name="show_template_overrides" value="1">
                                    <label class="form-check-label" for="show_template_overrides"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_OVERRIDES'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2" id="nextJoomlaVersionWrapper">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_next_version" type="checkbox" name="show_next_joomla_version" value="1">
                                    <label class="form-check-label" for="show_next_joomla_version"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_NEXT_JOOMLA'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_security_check" type="checkbox" name="show_security_check" value="1">
                                    <label class="form-check-label" for="show_security_check"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_SECURITY_CHECK'); ?></label>
                                </div>
                            </div>
                        </div>
                        <!-- End of new options -->
                    </div>
                </div>
            </div>
        </form>
    </div></div>

    <?php $ownVersion = $helperClass::getOwnVersion(); ?>
    <?php if ($ownVersion !== '') : ?>
    <div class="text-muted small text-center mb-3">
        <?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_COMPONENT_VERSION', $ownVersion); ?>
    </div>
    <?php endif; ?>

    </div>
</div>   
<script>
var jcDashCopyDoneText    = <?php echo json_encode(Text::_('COM_FGSYSTEMREPORT_DASH_COPY_DONE')); ?>;
var jcDashCsrfTokenName   = <?php echo json_encode(Joomla\CMS\Session\Session::getFormToken()); ?>;
var jcDashCopyDefaultText = <?php echo json_encode(Text::_('COM_FGSYSTEMREPORT_DASH_COPY_BTN')); ?>;
document.addEventListener('DOMContentLoaded', function () {
    // Recalculate the "Extensions" count when the core-extensions checkbox changes
    var includeCoreCheckbox = document.getElementById('include_core');
    if (includeCoreCheckbox) {
        includeCoreCheckbox.addEventListener('change', function () {
            var includeCore = this.checked ? 1 : 0;
            var params = new URLSearchParams();
            params.append('include_core', includeCore);
            params.append(jcDashCsrfTokenName, '1');
            fetch('index.php?option=com_fgsystemreport&task=fgsystemreport.getExtensionCount', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(function (response) { return response.text(); })
                .then(function (text) {
                    try {
                        var data = JSON.parse(text);
                        var target = document.querySelector('#box_extensions p');
                        if (target) {
                            target.textContent = data.count;
                        }
                    } catch (e) {
                        console.error('Invalid JSON response');
                    }
                })
                .catch(function () {
                    console.error('Fetch error');
                });
        });
    }
    // Copy the site-statistics summary to the clipboard
    var copyBtn = document.getElementById('copySystemInfo');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var copyText = document.getElementById('copyText');
            var lines = [];
            document.querySelectorAll('.jc-audit-box[data-copy="1"]').forEach(function (box) {
                var label = box.querySelector('h3');
                var value = box.querySelector('p');
                if (label && value) {
                    lines.push(label.textContent.trim() + ': ' + value.textContent.trim());
                }
            });
            navigator.clipboard.writeText(lines.join('\n')).then(function () {
                if (copyText) { copyText.textContent = jcDashCopyDoneText; }
                copyBtn.classList.add('btn-success');
                setTimeout(function () {
                    if (copyText) { copyText.textContent = jcDashCopyDefaultText; }
                    copyBtn.classList.remove('btn-success');
                }, 1500);
            }).catch(function (err) {
                console.error(err);
            });
        });
    }
    // Show/hide the "Show Next Joomla Version" checkbox depending on
    // whether "Include Joomla Information" is checked
    var joomlaInfoCheckbox = document.getElementById('show_joomla_info');
    var nextVersionWrapper = document.getElementById('nextJoomlaVersionWrapper');
    var nextVersionCheckbox = document.getElementById('show_next_version');
    function toggleNextJoomlaVersion() {
        if (!joomlaInfoCheckbox || !nextVersionWrapper) {
            return;
        }
        if (joomlaInfoCheckbox.checked) {
            nextVersionWrapper.style.display = '';
        } else {
            nextVersionWrapper.style.display = 'none';
            if (nextVersionCheckbox) {
                nextVersionCheckbox.checked = false;
            }
        }
    }
    toggleNextJoomlaVersion();
    if (joomlaInfoCheckbox) {
        joomlaInfoCheckbox.addEventListener('change', toggleNextJoomlaVersion);
    }
});
</script>

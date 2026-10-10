<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
\Fg\Component\Fgsystemreport\Administrator\Helper\GrowthAlertHelper::renderBanner();
?>
<style>
.jc-audit-wrapper {
    display: grid;                 /* uniform column width; unlike flexbox, a short last row */
    grid-template-columns: repeat(4, 1fr); /* 12 stat boxes -> exactly 3 equal rows of 4 */
    gap: 15px;                     /* consistent spacing, matches the quick-link cards above */
    margin: 20px 0;
}
.jc-audit-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px 18px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.jc-audit-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.jc-audit-box h3 {
    font-size: 13px;
    color: #777;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: 8px;
    font-weight: normal;
}
.jc-audit-box p {
    font-size: 18pt;
    font-weight: bold;
    color: #2b7a78;
    margin: 0;
}
@media (max-width: 992px) {
    .jc-audit-wrapper {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 560px) {
    .jc-audit-wrapper {
        grid-template-columns: 1fr;
    }
}
.jc-audit-btn {
    display: inline-block;
    margin-top: 0px;
    overflow: hidden;
}
.jc-audit-btn form button {
    display: block;
    width: 100%;
    padding: 12px 20px;
    background-color: #2b7a78;  /* matches your box numbers */
    color: #fff;
    font-size: 14pt;
    font-weight: bold;
    border: none;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.2s, transform 0.2s;
}
.jc-audit-btn form button:hover {
    background-color: #3aa79a;
    transform: translateY(-2px);
}
</style>
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
    <div style="display:flex; gap:15px; margin-bottom:25px; flex-wrap:wrap;">
        <a href="index.php?option=com_fgsystemreport&view=extensions"
           style="flex:1 1 280px; text-decoration:none; color:inherit; display:block;
                  background:#fff; border:1px solid #ddd; border-left:5px solid <?php echo $dashUpdateCount > 0 ? '#c0392b' : '#4BB649'; ?>;
                  border-radius:8px; padding:15px 18px; box-shadow:0 2px 5px rgba(0,0,0,0.06);">
            <div style="font-size:13px; color:#777; text-transform:uppercase; letter-spacing:.03em;"><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_EXTENSIONS'); ?></div>
            <div style="font-size:20px; font-weight:bold; margin-top:4px;">
                <?php if ($dashUpdateCount > 0) : ?>
                    <span style="color:#c0392b;"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_EXT_UPDATES_AVAILABLE', $dashUpdateCount); ?></span>
                <?php else : ?>
                    <span style="color:#4BB649;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_EXT_UP_TO_DATE'); ?></span>
                <?php endif; ?>
            </div>
            <div style="font-size:13px; color:#269DD8; margin-top:6px;"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_VIEW_MORE', Text::_('COM_FGSYSTEMREPORT_TITLE_EXTENSIONS')); ?> &rarr;</div>
        </a>
        <a href="index.php?option=com_fgsystemreport&view=security"
           style="flex:1 1 280px; text-decoration:none; color:inherit; display:block;
                  background:#fff; border:1px solid #ddd; border-left:5px solid <?php echo $dashSecCounts['danger'] > 0 ? '#c0392b' : ($dashSecCounts['warning'] > 0 ? '#e6a23c' : '#4BB649'); ?>;
                  border-radius:8px; padding:15px 18px; box-shadow:0 2px 5px rgba(0,0,0,0.06);">
            <div style="font-size:13px; color:#777; text-transform:uppercase; letter-spacing:.03em;"><?php echo Text::_('COM_FGSYSTEMREPORT_TITLE_SECURITY'); ?></div>
            <div style="font-size:20px; font-weight:bold; margin-top:4px;">
                <?php if ($dashSecCounts['danger'] > 0) : ?>
                    <span style="color:#c0392b;"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_SEC_CRITICAL', $dashSecCounts['danger']); ?></span>
                <?php elseif ($dashSecCounts['warning'] > 0) : ?>
                    <span style="color:#e6a23c;"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_SEC_WARNINGS', $dashSecCounts['warning']); ?></span>
                <?php else : ?>
                    <span style="color:#4BB649;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_SEC_NO_ISSUES'); ?></span>
                <?php endif; ?>
            </div>
            <div style="font-size:13px; color:#269DD8; margin-top:6px;"><?php echo Text::sprintf('COM_FGSYSTEMREPORT_DASH_VIEW_MORE', Text::_('COM_FGSYSTEMREPORT_TITLE_SECURITY')); ?> &rarr;</div>
        </a>
    </div>
    <!-- SECTION 2: System Information & Statistics -->
    <div style="margin-top: 30px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #4BB649; padding-bottom: 10px;">
            <h3 style="margin: 0;"> <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STATS_HEADING'); ?></h3>
            <button type="button" id="copySystemInfo" style="padding: 8px 12px; background-color: #4BB649; color: #fff; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                <i class="icon-copy"></i> <span id="copyText"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_COPY_BTN'); ?></span>
            </button>
        </div>
    </div>
    <div class="jc-audit-wrapper">
        <div class="jc-audit-box">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_CATEGORIES'); ?></h3>
            <p><?php echo $this->reportdata['total_categories']; ?></p>
        </div>
        <div class="jc-audit-box">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_ARTICLES'); ?></h3>
            <p><?php echo $this->reportdata['total_articles']; ?></p>
        </div>
        <div class="jc-audit-box">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_USERS'); ?></h3>
            <p><?php echo $this->reportdata['total_users']; ?></p>
        </div>
        <!--Total Published Menus-->
        <div class="jc-audit-box">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_MENUS'); ?></h3>
            <p>
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
        <div class="jc-audit-box">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_TAGS'); ?></h3>
            <p>
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
        <div class="jc-audit-box" id="box_extensions" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_EXTENSIONS'); ?></h3>
            <p>
                <?php
                echo '<strong>' . count($dashExtList) . '</strong>';
                ?>
            </p>
        </div>
        <div class="jc-audit-box" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_PHP_VERSION'); ?></h3>
            <p><?php echo phpversion(); ?></p>
        </div>
        <div class="jc-audit-box" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_MYSQL_VERSION'); ?></h3>
            <p>
                <?php 
                    $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                    echo htmlspecialchars($db->getVersion());
                ?>
            </p>
        </div>
        <div class="jc-audit-box" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_JOOMLA_VERSION'); ?></h3>
            <p>
                <?php 
                    $jversion = new \Joomla\CMS\Version();
                    $joomlaVersion = $jversion->getShortVersion();
                    echo "<span style='color: #F6921E;'>$joomlaVersion</span><br>";
                ?>
            </p>
        </div>
        <!-- Latest Joomla version -->
        <div class="jc-audit-box" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_NEXT_JOOMLA_VERSION'); ?></h3>
            <p>
                <?php
                $latestJoomla = $dashLatestJoomla;
                if ($latestJoomla !== null) {
                    echo '<span style="color:#4BB649;">' . htmlspecialchars($latestJoomla) . '</span>';
                } else {
                    echo '<span style="color:red;">' . Text::_('COM_FGSYSTEMREPORT_DASH_VERSION_CHECK_FAILED') . '</span>';
                }
                ?>
            </p>
        </div>
        <!-- Latest Joomla version -->
        <!--Total LANGUAGES -->
        <div class="jc-audit-box" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_LANGUAGES'); ?></h3>
            <p>
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
        <div class="jc-audit-box" data-copy="1">
            <h3><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_STAT_CURRENT_TEMPLATE'); ?></h3>
            <p>
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
    <div class="jc-audit-btn" style="position: relative; display: block; margin-bottom: 15px;">
        <form method="post" action="index.php?option=com_fgsystemreport&task=fgsystemreport.generatePDF" style="margin:0;">
            <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
            <div style="margin-top: 30px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #269DD8; padding-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin: 0;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_HEADING'); ?></h3>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <input type="email" name="pdf_email_recipient" placeholder="<?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_EMAIL_PLACEHOLDER'); ?>"
                            style="padding:7px 10px; border:1px solid #ccc; border-radius:4px; width:220px;">
                        <button type="submit" formaction="index.php?option=com_fgsystemreport&task=fgsystemreport.emailPDF"
                            style="padding:8px 12px; background-color:#fff; color:#269DD8; border:1px solid #269DD8; border-radius:4px; cursor:pointer;width:auto;">
                            <i class="icon-envelope"></i> <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_EMAIL_BTN'); ?>
                        </button>
                        <button type="submit" style="padding:8px 12px; background-color:#269DD8; color:#fff; border:none; border-radius:4px; cursor:pointer;width:auto;">
                            <i class="icon-download"></i> <?php echo Text::_('COM_FGSYSTEMREPORT_DASH_PDF_DOWNLOAD_BTN'); ?>
                        </button>
                    </div>
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
                                    <label class="form-check-label" for="include_core" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_INCLUDE_CORE'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_system_info" type="checkbox" name="show_system_info" value="1">
                                    <label class="form-check-label" for="show_system_info" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SERVER_INFO'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_joomla_info" type="checkbox" name="show_joomla_info" value="1">
                                    <label class="form-check-label" for="show_joomla_info" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_JOOMLA_INFO'); ?></label>
                                </div>
                            </div>

                        </div>
                        <!-- Additional group of checkboxes for cleaner spacing -->
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_version" type="checkbox" name="show_joomla_version" value="1">
                                    <label class="form-check-label" for="show_version" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_JOOMLA_VERSION'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_domain" type="checkbox" name="show_domain" value="1">
                                    <label class="form-check-label" for="show_domain" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_DOMAIN'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_categories" type="checkbox" name="show_categories" value="1">
                                    <label class="form-check-label" for="show_categories" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_CATEGORIES'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_articles" type="checkbox" name="show_articles" value="1">
                                    <label class="form-check-label" for="show_articles" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_ARTICLES'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_users" type="checkbox" name="show_users" value="1">
                                    <label class="form-check-label" for="show_users" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_USERS'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_menus" type="checkbox" name="show_menus" value="1">
                                    <label class="form-check-label" for="show_menus" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_MENUS'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_tags" type="checkbox" name="show_tags" value="1">
                                    <label class="form-check-label" for="show_tags" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_TAGS'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_extensions" type="checkbox" name="show_extensions_count" value="1">
                                    <label class="form-check-label" for="show_extensions_count" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_EXTENSIONS'); ?></label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_template_overrides" type="checkbox" name="show_template_overrides" value="1">
                                    <label class="form-check-label" for="show_template_overrides" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_OVERRIDES'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2" id="nextJoomlaVersionWrapper">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_next_version" type="checkbox" name="show_next_joomla_version" value="1">
                                    <label class="form-check-label" for="show_next_joomla_version" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_NEXT_JOOMLA'); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="control-group row mb-2">
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input checked class="form-check-input" id="show_security_check" type="checkbox" name="show_security_check" value="1">
                                    <label class="form-check-label" for="show_security_check" style="font-size: 14px;"><?php echo Text::_('COM_FGSYSTEMREPORT_DASH_OPT_SHOW_SECURITY_CHECK'); ?></label>
                                </div>
                            </div>
                        </div>
                        <!-- End of new options -->
                    </div>
                </div>
            </div>
        </form>
    </div>
    <br><br>

    <?php $ownVersion = $helperClass::getOwnVersion(); ?>
    <?php if ($ownVersion !== '') : ?>
    <div style="text-align:center; color:#999; font-size:11px; margin-bottom:15px;">
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
                copyBtn.style.backgroundColor = '#218838';
                setTimeout(function () {
                    if (copyText) { copyText.textContent = jcDashCopyDefaultText; }
                    copyBtn.style.backgroundColor = '#4BB649';
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

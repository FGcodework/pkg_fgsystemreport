<?php
/**
 * @version    CVS: 1.0.28
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Fg\Component\Fgsystemreport\Administrator\Service;
// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Language\Text;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
use Fg\Component\Fgsystemreport\Administrator\Pdf\FgSystemPDF;
/**
 * Builds and outputs the PDF system report.
 *
 * @since  1.0.28
 */
class ReportPdfService
{
    /**
     * Build the full PDF report and return the FgSystemPDF object, without
     * outputting it anywhere - shared by generate() (stream to browser)
     * and generateAndEmail() (send as an attachment), so both honour the
     * exact same POST checkbox selections and produce an identical report.
     *
     * @return  FgSystemPDF
     */
    protected function buildPdf(): FgSystemPDF
    {
        $app = Factory::getApplication();
        $jinput = $app->getInput();

        // Check filter checkboxes
        $includeCore    = $jinput->post->getInt('include_core', 0);
        $showDomain        = $jinput->post->getInt('show_domain', 0);
        $showCategories    = $jinput->post->getInt('show_categories', 0);
        $showArticles      = $jinput->post->getInt('show_articles', 0);
        $showUsers         = $jinput->post->getInt('show_users', 0);
        $showJoomlaVersion = $jinput->post->getInt('show_joomla_version', 0);
        $showSystemInfo    = $jinput->post->getInt('show_system_info', 0);
        $showJoomlaInfo    = $jinput->post->getInt('show_joomla_info', 0);
        $showMenus = $jinput->post->getInt('show_menus', 0);
        $showTags  = $jinput->post->getInt('show_tags', 0);
        // New options
        $showExtensionsCount = $jinput->post->getInt('show_extensions_count', 0);
        $showNextJoomlaVersion = $jinput->post->getInt('show_next_joomla_version', 0);
        $showTemplateOverrides = $jinput->post->getInt('show_template_overrides', 0);
        $showSecurityCheck = $jinput->post->getInt('show_security_check', 0);

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $counts = [];

        // Basic counts
        if ($showArticles) {
            $db->setQuery('SELECT COUNT(*) FROM #__content WHERE state = 1');
            $counts[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_ARTICLES')] = $db->loadResult();
        }

        if ($showCategories) {
            $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                    ->where($db->quoteName('published') . ' = 1')
            );
            $counts[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_CATEGORIES')] = $db->loadResult();
        }

        if ($showUsers) {
            $db->setQuery('SELECT COUNT(*) FROM #__users');
            $counts[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_USERS')] = $db->loadResult();
        }

        // menus
        if ($showMenus) {
            $query = $db->getQuery(true)
                ->select('COUNT(id)')
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('menutype') . ' != ' . $db->quote('main'))
                ->where($db->quoteName('title') . ' != ' . $db->quote('Menu_Item_Root'));
            $db->setQuery($query);
            $counts[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_MENUS')] = $db->loadResult();
        }

        // Tags
        if ($showTags) {
            $query = $db->getQuery(true)
                ->select('COUNT(id)')
                ->from($db->quoteName('#__tags'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('title') . ' != ' . $db->quote('root'));
            $db->setQuery($query);
            $counts[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_TAGS')] = $db->loadResult();
        }

        // Extensions count - only if showExtensionsCount is checked
        if ($showExtensionsCount) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('name') . ' IS NOT NULL AND ' . $db->quoteName('name') . ' != ' . $db->quote(''));

            if ($includeCore != 1) {
                $query->where($db->quoteName('locked') . ' = 0');
            }

            $db->setQuery($query);
            $counts[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_EXTENSIONS')] = $db->loadResult();
        }

        // $db->setQuery($query);
        // $counts['Extensions'] = $db->loadResult();

        // ✅ Use custom subclass for better header/footer
        $pdf = new FgSystemPDF();
        $pdf->setShowDomain($showDomain);
        $pdf->setShowJoomlaVersion($showJoomlaVersion);
        $pdf->SetCreator('FG System Report');
        $pdf->SetAuthor('FG System Report');
        $pdf->SetTitle('FG Quick Insight Report');
        $pdf->SetMargins(15, 35, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // ---- Executive Summary (Security Check score) ----
        if ($showSecurityCheck) {
            $securityChecks = FgsystemreportHelper::getSecurityChecks();
            $scoreData      = FgsystemreportHelper::calculateSecurityScore($securityChecks);

            if ($scoreData->score >= 80) {
                $scoreColor = '#4BB649';
            } elseif ($scoreData->score >= 50) {
                $scoreColor = '#e6a23c';
            } else {
                $scoreColor = '#c0392b';
            }

            $recommendationsHtml = '';
            if (!empty($scoreData->topRecommendations)) {
                $recommendationsHtml .= '<ol style="margin:4px 0 0 0; padding-left:16px;">';
                foreach ($scoreData->topRecommendations as $rec) {
                    $recommendationsHtml .= '<li style="font-size:9px; margin-bottom:3px;"><strong>'
                        . htmlspecialchars($rec->label) . ':</strong> ' . htmlspecialchars($rec->note) . '</li>';
                }
                $recommendationsHtml .= '</ol>';
            } else {
                $recommendationsHtml = '<span style="font-size:9px; color:#4BB649;">'
                    . Text::_('COM_FGSYSTEMREPORT_PDF_SCORE_NO_ISSUES') . '</span>';
            }

            $pdf->SetFont('dejavusanssub', 'B', 12);
            $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_PDF_EXEC_SUMMARY_HEADING'), 0, 1, 'L');
            $pdf->Ln(1);

            $summaryHtml = '
                <table cellpadding="6" style="border:1px solid #ddd; border-radius:4px;">
                    <tr>
                        <td width="25%" align="center" style="border-right:1px solid #ddd;">
                            <span style="font-size:28pt; font-weight:bold; color:' . $scoreColor . ';">' . (int) $scoreData->score . '</span><br/>
                            <span style="font-size:8px; color:#777;">' . Text::_('COM_FGSYSTEMREPORT_PDF_SCORE_OUT_OF_100') . '</span>
                        </td>
                        <td width="75%">
                            <span style="font-size:10px;">
                                <strong style="color:#c0392b;">' . (int) $scoreData->dangerCount . '</strong> ' . Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_DANGER') . '&nbsp;&nbsp;&nbsp;
                                <strong style="color:#e6a23c;">' . (int) $scoreData->warningCount . '</strong> ' . Text::_('COM_FGSYSTEMREPORT_SEC_STATUS_WARNING') . '
                            </span><br/>
                            <span style="font-size:9px; font-weight:bold;">' . Text::_('COM_FGSYSTEMREPORT_PDF_TOP_RECOMMENDATIONS') . '</span>
                            ' . $recommendationsHtml . '
                        </td>
                    </tr>
                </table>';

            $pdf->writeHTML($summaryHtml, true, false, true, false, '');
            $pdf->Ln(4);

            // ---- Full Security Check table ----
            $pdf->SetFont('dejavusanssub', 'B', 14);
            $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_SEC_HEADING'), 0, 1, 'L');
            $pdf->Ln(1);

            $statusColors = [
                'ok'      => ['#eafaf1', '#1e7e34'],
                'warning' => ['#fdf3e3', '#b8720a'],
                'danger'  => ['#fdecea', '#c0392b'],
                'info'    => ['#f1f1f1', '#666666'],
            ];
            $statusLabels = [
                'ok'      => 'COM_FGSYSTEMREPORT_SEC_STATUS_OK',
                'warning' => 'COM_FGSYSTEMREPORT_SEC_STATUS_WARNING',
                'danger'  => 'COM_FGSYSTEMREPORT_SEC_STATUS_DANGER',
                'info'    => 'COM_FGSYSTEMREPORT_SEC_STATUS_INFO',
            ];

            $secHtml = '
                <table cellpadding="4" style="border-collapse: collapse; width:100%;">
                    <tr style="background-color:#f0f0f0; font-weight:bold;">
                        <th align="left" style="width:26%; border:1px solid #ddd; font-size:10px;">' . Text::_('COM_FGSYSTEMREPORT_SEC_COL_CHECK') . '</th>
                        <th align="center" style="width:12%; border:1px solid #ddd; font-size:10px;">' . Text::_('COM_FGSYSTEMREPORT_SEC_COL_STATUS') . '</th>
                        <th align="left" style="width:24%; border:1px solid #ddd; font-size:10px;">' . Text::_('COM_FGSYSTEMREPORT_SEC_COL_VALUE') . '</th>
                        <th align="left" style="width:38%; border:1px solid #ddd; font-size:10px;">' . Text::_('COM_FGSYSTEMREPORT_SEC_COL_NOTE') . '</th>
                    </tr>';

            foreach ($securityChecks as $check) {
                [$bg, $fg] = $statusColors[$check->status] ?? $statusColors['info'];
                $statusLabel = Text::_($statusLabels[$check->status] ?? 'COM_FGSYSTEMREPORT_SEC_STATUS_INFO');

                $secHtml .= '<tr>
                        <td align="left" style="border:1px solid #ddd; font-size:9px; background-color:' . $bg . ';">' . htmlspecialchars($check->label) . '</td>
                        <td align="center" style="border:1px solid #ddd; font-size:9px; background-color:' . $bg . '; color:' . $fg . '; font-weight:bold;">' . htmlspecialchars($statusLabel) . '</td>
                        <td align="left" style="border:1px solid #ddd; font-size:9px; background-color:' . $bg . ';">' . htmlspecialchars($check->value) . '</td>
                        <td align="left" style="border:1px solid #ddd; font-size:8px; background-color:' . $bg . ';">' . htmlspecialchars($check->note) . '</td>
                    </tr>';
            }

            $secHtml .= '</table>';

            $pdf->writeHTML($secHtml, true, false, true, false, '');
            $pdf->Ln(4);
        }

        // ---- Summary Section ----
        if (!empty($counts)) {
            $pdf->SetFont('dejavusanssub', 'B', 12);
            $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_PDF_SUMMARY_HEADING'), 0, 1, 'L');
            $pdf->Ln(1);

            $pdf->SetFont('dejavusanssub', '', 10);
            $html = '
                <style>
                    .summary-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-family: dejavusanssub;
                        margin: 15px;
                    }
                    .summary-table td {
                        width: 25%;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        padding:10px;
                        text-align: center;

                    }
                    .summary-title {
                        font-size: 13px;
                        color: #333;
                        font-weight: bold;
                        text-transform: uppercase;
                    }
                    .summary-value {
                        font-size: 20px;
                        font-weight: bold;
                        color: #007bff;
                        margin-bottom: 6px;
                    }

                </style>

                <table cellspacing="0" cellpadding="3" class="summary-table">
                    <tr>';

           // ✅ Loop counts: 3 items per row
            $colCount = 0;
            foreach ($counts as $key => $value) {
                $html .= '
                    <td>
                        <table style="width:100%; border-collapse: collapse;">
                            <tr><td class="summary-title">'.htmlspecialchars($key).'</td></tr>
                            <tr><td class="summary-value">'.(int)$value.'</td></tr>
                        </table>
                    </td>';
                $colCount++;

                if ($colCount % 3 == 0 && $colCount < count($counts)) {
                    $html .= '</tr><tr>'; // new row after every 3 items
                }
            }

            $html .= '</tr></table><br/><br/>';
            $pdf->writeHTML($html, true, false, true, false, '');
        }

        // show system information
        if ($showSystemInfo) {
            $serverInfo = [];
            $serverInfo[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_PHP_VERSION')] = phpversion();
            $serverInfo[Text::_('COM_FGSYSTEMREPORT_PDF_STAT_MYSQL_VERSION')] = $db->getVersion();

            $pdf->SetFont('dejavusanssub', 'B', 14);
            $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_PDF_SERVER_INFO_HEADING'), 0, 1, 'L');
            $pdf->Ln(3);
            $html1 = '<table cellspacing="0" cellpadding="3" style="border-collapse: collapse; width:100%;">';
            $counter = 1;
            foreach ($serverInfo as $key => $value) {
                $html1 .= '<tr>
                    <td align="left" style="width:35%; font-size:10px; border:1px solid #ddd; vertical-align: middle;">'.htmlspecialchars($key).'</td>
                    <td align="left" style="width:65%; font-size:10px; border:1px solid #ddd; vertical-align: middle;">'.htmlspecialchars($value).'</td>
                </tr>';
                $counter++;
            }

            $html1 .= '</table>';
            $pdf->writeHTML($html1, true, false, true, false, '');
        }
        // show system information
        if ($showJoomlaInfo) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($query);
            $languages = $db->loadColumn();

            // Remove text in parentheses
            $enabledLanguages = array_map(function($lang) {
                return trim(preg_replace('/\s*\([^)]*\)/', '', $lang));
            }, $languages);

            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_ENABLED_LANGUAGES')] = !empty($enabledLanguages) ? implode(', ', $enabledLanguages) : Text::_('JNONE');
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_DEFAULT_LANGUAGE')] = $app->get('language');

                if ($showNextJoomlaVersion) {
                    $latestVersion = FgsystemreportHelper::fetchLatestJoomlaVersion() ?? Text::_('COM_FGSYSTEMREPORT_PDF_UNKNOWN');
                    $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_NEXT_JOOMLA_VERSION')] = $latestVersion;
                }
            $query = $db->getQuery(true)
                ->select($db->quoteName('template'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('home') . ' = 1');
            $db->setQuery($query);
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_DEFAULT_TEMPLATE')] = $db->loadResult();

            // Configuration-awareness items: not risks to flag, just facts
            // an admin would want to see at a glance to confirm the site
            // is set up the way they intend.
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_PHP_LOCALE')] = setlocale(LC_ALL, 0) ?: Text::_('JNONE');
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_PHP_TIMEZONE')] = date_default_timezone_get();
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_SESSION')] = Text::sprintf(
                'COM_FGSYSTEMREPORT_PDF_SESSION_VALUE',
                (int) $app->get('lifetime', 15),
                $app->get('session_handler', 'database')
            );
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_CACHING')] = ((int) $app->get('caching', 0)) > 0
                ? Text::_('JENABLED')
                : Text::_('JDISABLED');
            $mailFrom = (string) $app->get('mailfrom', '');
            $fromName = (string) $app->get('fromname', '');
            $otherInformation[Text::_('COM_FGSYSTEMREPORT_PDF_MAIL_FROM')] = $mailFrom !== ''
                ? $fromName . ' <' . $mailFrom . '>'
                : Text::_('JNONE');
            $otherInformation['DATE_FORMAT_CALENDAR_DATE'] = Text::_('DATE_FORMAT_CALENDAR_DATE');

            $pdf->SetFont('dejavusanssub', 'B', 14);
            $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_PDF_JOOMLA_INFO_HEADING'), 0, 1, 'L');
            $pdf->Ln(3);
            $html1 = '<table cellspacing="0" cellpadding="3" style="border-collapse: collapse; width:100%;">';
            $counter = 1;
            foreach ($otherInformation as $key => $value) {
                $html1 .= '<tr>
                    <td align="left" style="width:35%; font-size:10px; border:1px solid #ddd; vertical-align: middle;">'.htmlspecialchars($key).'</td>
                    <td align="left" style="width:65%; font-size:10px; border:1px solid #ddd; vertical-align: middle;">'.htmlspecialchars($value).'</td>
                </tr>';
                $counter++;
            }

            $html1 .= '</table>';
            $pdf->writeHTML($html1, true, false, true, false, '');
        }
        // Scan template overrides
        if ($showTemplateOverrides) {
            $overrideSummary = $this->scanTemplateOverrides(); // returns array with components, modules, plugins, layouts, languages, sp_page_builder
            $hasOverrides = false;
            foreach ($overrideSummary as $items) {
                foreach ($items as $files) {
                    if (is_array($files) && count($files)) {
                        $hasOverrides = true;
                        break 2; // break both loops
                    }
                }
            }
            if ($hasOverrides) {
                $pdf->SetFont('dejavusanssub', 'B', 14);
                $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_PDF_TEMPLATE_OVERRIDES_HEADING'), 0, 1, 'L');
                $pdf->Ln(3);

                // Build table
                $html3 = '<table cellspacing="0" cellpadding="3" style="border-collapse: collapse; width:100%;">
                    <tr>
                        <th align="center" style="width:5%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">#</th>
                        <th align="center" style="width:40%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_EXT_COL_TYPE').'</th>
                        <th align="center" style="width:55%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_PDF_COL_FILE_VIEW').'</th>
                    </tr>';

                $counter = 1;
                $singularTypes = [
                    'components' => Text::_('COM_FGSYSTEMREPORT_TYPE_COMPONENT'),
                    'modules' => Text::_('COM_FGSYSTEMREPORT_TYPE_MODULE'),
                    'plugins' => Text::_('COM_FGSYSTEMREPORT_TYPE_PLUGIN'),
                    'layouts' => Text::_('COM_FGSYSTEMREPORT_PDF_TYPE_LAYOUT'),
                    'languages' => Text::_('COM_FGSYSTEMREPORT_TYPE_LANGUAGE'),
                    'sp_page_builder' => Text::_('COM_FGSYSTEMREPORT_PDF_TYPE_SPPAGEBUILDER')
                ];
                // Loop through each type
                foreach ($overrideSummary as $type => $items) {
                    if (!empty($items)) {
                        $singularType = $singularTypes[$type] ?? ucfirst($type);
                        foreach ($items as $name => $files) {
                            if (is_array($files) && count($files)) {

                                    $displayName = basename($name);
                                    $html3 .= '<tr>
                                        <td align="center" style="border:1px solid #ddd; font-size:10px; vertical-align: middle;">'.$counter.'</td>
                                        <td align="center" style="border:1px solid #ddd; font-size:10px; vertical-align: middle;">'.$singularType.'</td>
                                        <td align="left" style="border:1px solid #ddd; font-size:10px; vertical-align: middle;">'.htmlspecialchars($displayName).'</td>
                                    </tr>';
                                    $counter++;

                            }
                        }
                    }
                }

                $html3 .= '</table>';
                $pdf->writeHTML($html3, true, false, true, false, '');
                $pdf->Ln(5);
            }
        }

        //all extensions 
        $pdf->SetFont('dejavusanssub', 'B', 14);
        $pdf->Cell(0, 10, Text::_('COM_FGSYSTEMREPORT_EXT_HEADING'), 0, 1, 'L');
        $pdf->Ln(3);

        $extensions = FgsystemreportHelper::getInstalledExtensions($includeCore == 1);
        // Example placeholder table
        $html2 = '
            <table cellspacing="0" cellpadding="3" style="border-collapse: collapse; width:100%;">
                <tr>
                    <th align="center" style="width:5%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">#</th>
                    <th align="center" style="width:28%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_EXT_COL_NAME').'</th>
                    <th align="center" style="width:15%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_EXT_COL_TYPE').'</th>
                    <th align="center" style="width:12%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_EXT_COL_STATE').'</th>
                    <th align="center" style="width:20%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_EXT_COL_CURRENT_VERSION').'</th>
                    <th align="center" style="width:20%; border:1px solid #ddd; font-size:11px; vertical-align: middle;">'.Text::_('COM_FGSYSTEMREPORT_EXT_COL_NEW_VERSION').'</th>
                </tr>
                ';

        // The table is written in chunks: TCPDF's writeHTML() memory use grows
        // steeply with the size of one HTML string (measured: ~98 MB for 1500
        // rows in one call vs ~22 MB in chunks of 100), so a site with very
        // many extensions cannot exhaust memory_limit. Each chunk repeats the
        // header row.
        $extHead = $html2;
        $counter = 1;
        foreach ($extensions as $ext) {
            $isPackage = $ext->is_package;
            $isChild = $ext->is_child;
            $hasUpdate = $ext->has_update;
            // Row background: package = amber, update available = light red tint
            if ($isPackage) {
                $rowBg = ' background-color:#fff3cd;';
            } elseif ($hasUpdate) {
                $rowBg = ' background-color:#fdecea;';
            } else {
                $rowBg = '';
            }
            $typeKey = 'COM_FGSYSTEMREPORT_TYPE_' . strtoupper($ext->type);
            $typeLabel = Text::_($typeKey);
            if ($typeLabel === $typeKey) {
                $typeLabel = ucfirst($ext->type);
            }
            if ($isPackage) {
                $typeLabel = '<b>'.$typeLabel.'</b>';
            } elseif ($isChild) {
                $typeLabel .= '<br /><span style="font-size:8px; color:#888888;">'.Text::sprintf('COM_FGSYSTEMREPORT_EXT_IN_PACKAGE', htmlspecialchars($ext->parent_package)).'</span>';
            }
            if ($isPackage) {
                $nameCell = '<b>'.htmlspecialchars($ext->display_name).'</b>';
            } elseif ($isChild) {
                $nameCell = '<span style="color:#555555;">&nbsp;&nbsp;&raquo; '.htmlspecialchars($ext->display_name).'</span>';
            } else {
                $nameCell = htmlspecialchars($ext->display_name);
            }
            // State column (enabled/disabled)
            if ((int) $ext->enabled === 1) {
                $stateCell = '<span style="color:#1e7e34;">'.Text::_('COM_FGSYSTEMREPORT_EXT_ENABLED').'</span>';
            } else {
                $stateCell = '<b><span style="color:#c0392b;">'.Text::_('COM_FGSYSTEMREPORT_EXT_DISABLED').'</span></b>';
            }
            // New Version cell: highlight available update
            if ($hasUpdate) {
                $versionCell = '<b><span style="color:#c0392b;">'.htmlspecialchars($ext->available_version).'</span></b>';
            } else {
                $versionCell = htmlspecialchars($ext->available_version);
            }
            $html2 .= '<tr>
                <td align="center" style="width:5%; font-size:10px; border:1px solid #ddd; vertical-align: middle;'.$rowBg.'">'.$counter.'</td>
                <td align="left" style="width:28%; font-size:10px; border:1px solid #ddd; vertical-align: middle;'.$rowBg.'">'.$nameCell.'</td>
                <td align="center" style="width:15%; font-size:10px; border:1px solid #ddd; vertical-align: middle;'.$rowBg.'">'.$typeLabel.'</td>
                <td align="center" style="width:12%; font-size:10px; border:1px solid #ddd; vertical-align: middle;'.$rowBg.'">'.$stateCell.'</td>
                <td align="center" style="width:20%; font-size:10px; border:1px solid #ddd; vertical-align: middle;'.$rowBg.'">'.htmlspecialchars($ext->installed_version).'</td>
                <td align="center" style="width:20%; font-size:10px; border:1px solid #ddd; vertical-align: middle;'.$rowBg.'">'.$versionCell.'</td>
            </tr>';
            $counter++;

            if (($counter - 1) % 100 === 0) {
                $pdf->writeHTML($html2 . '</table>', true, false, true, false, '');
                $html2 = $extHead;
            }
        }

        // Remainder (or the header-only table when there are no extensions)
        if ($html2 !== $extHead || $counter === 1) {
            $pdf->writeHTML($html2 . '</table>', true, false, true, false, '');
        }

        $pdf->Ln(5);

        return $pdf;
    }

    /**
     * Build the PDF and stream it to the browser as a download.
     *
     * @return  void
     */
    public function generate()
    {
        $pdf = $this->buildPdf();

        while (ob_get_level()) {
            ob_end_clean();
        }

        $pdf->Output('fg_system_report.pdf', 'D');
        Factory::getApplication()->close();
    }

    /**
     * Build the PDF and email it as an attachment instead of downloading
     * it - same report, same checkbox selections, just a different
     * delivery method.
     *
     * @param   string  $recipientEmail  Address to send the report to
     *
     * @return  bool  True if the mailer accepted the message
     */
    public function generateAndEmail(string $recipientEmail): bool
    {
        $pdf        = $this->buildPdf();
        $pdfContent = $pdf->Output('fg_system_report.pdf', 'S');

        $app      = Factory::getApplication();
        $siteName = (string) $app->get('sitename', '');

        try {
            $mailer = Factory::getContainer()->get(\Joomla\CMS\Mail\MailerFactoryInterface::class)->createMailer();
            $mailer->setSubject(Text::sprintf('COM_FGSYSTEMREPORT_PDF_EMAIL_SUBJECT', $siteName));
            $mailer->setBody(Text::sprintf('COM_FGSYSTEMREPORT_PDF_EMAIL_BODY', $siteName));
            $mailer->isHtml(false);
            $mailer->addRecipient($recipientEmail);
            $mailer->addStringAttachment($pdfContent, 'fg_system_report.pdf', 'base64', 'application/pdf');

            return $mailer->Send() === true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    public function scanTemplateOverrides($template = null)
    {
        $app = Factory::getApplication();
        if (!$template) {
            if ($app->isClient('site')) {
            $template = $app->getTemplate(); // frontend template when running from site
            } else {
                // backend request — get default frontend template from #__template_styles
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $db->setQuery("SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1 LIMIT 1");
                $template = $db->loadResult();
            }
        }

        $templatePath = JPATH_SITE . '/templates/' . $template;
        $htmlPath = $templatePath . '/html';
        // echo "<pre/>";print_r($htmlPath);exit;

        $summary = [
            'components' => [],
            'modules' => [],
            'plugins' => [],
            'layouts' => [],
            'languages' => [],
            'sp_page_builder' => []
        ];

        if (is_dir($htmlPath)) {
            $folders = array_filter(glob($htmlPath . '/*'), 'is_dir');

            foreach ($folders as $folder) {
                $name = basename($folder);

                // Components
                if (strpos($name, 'com_') === 0) {
                    $summary['components'][$name] = $this->getAllPhpFiles($folder);
                }

                // Modules
                elseif (strpos($name, 'mod_') === 0) {
                    $summary['modules'][$name] = $this->getAllPhpFiles($folder);
                }

                // Plugins
                elseif ($name === 'plugins') {
                    $summary['plugins'] = $this->getPluginOverrideFiles($folder);
                }

                // Layouts
                elseif ($name === 'layouts') {
                    $summary['layouts'] = $this->getAllPhpFiles($folder);
                }

                // Languages
                elseif (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $name)) {
                    $summary['languages'][$name] = $this->getAllPhpFiles($folder);
                }
            }
        }

        // SP Page Builder overrides (template root)
        $spbFolders = [
            $templatePath . '/sppagebuilder',
            $templatePath . '/com_sppagebuilder'
        ];
        foreach ($spbFolders as $spbPath) {
            if (is_dir($spbPath)) {
                $summary['sp_page_builder'][basename($spbPath)] = $this->getAllPhpFiles($spbPath);
            }
        }

        return $summary;
    }
    /**
     * Get all PHP files recursively with relative path
     */
    public function getAllPhpFiles($dir)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = str_replace(JPATH_SITE . '/', '', $file->getPathname());
            }
        }

        return $files;
    }
    /**
     * Get plugin overrides recursively
     */
    public function getPluginOverrideFiles($pluginFolder)
    {
        $result = [];
        $groups = array_filter(glob($pluginFolder . '/*'), 'is_dir');

        foreach ($groups as $groupPath) {
            $groupName = basename($groupPath);
            $plugins = array_filter(glob($groupPath . '/*'), 'is_dir');

            foreach ($plugins as $pluginPath) {
                $pluginName = basename($pluginPath);
                $result["$groupName/$pluginName"] = $this->getAllPhpFiles($pluginPath);
            }
        }
        return $result;
    }
}

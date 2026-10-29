<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\View;

defined('_JEXEC') or die;

use Fg\Component\Fgsystemreport\Administrator\Helper\HistoryHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as JoomlaHtmlView;
use Joomla\CMS\Uri\Uri;

/**
 * Base view of the component: wraps every page in the FG UI shell (scoped
 * stylesheet, brand header with the latest score and the page tabs).
 * A view that renders its own clean page (print view) sets $fgChrome = false.
 *
 * @since  1.12.0
 */
class FgHtmlView extends JoomlaHtmlView
{
    /** Show the brand header and tabs. */
    protected $fgChrome = true;

    /** view => [tab label key, needs core.admin] */
    private const TABS = [
        'fgsystemreport' => ['COM_FGSYSTEMREPORT_TAB_DASHBOARD', false],
        'extensions'     => ['COM_FGSYSTEMREPORT_TAB_EXTENSIONS', false],
        'security'       => ['COM_FGSYSTEMREPORT_TAB_SECURITY', true],
        'history'        => ['COM_FGSYSTEMREPORT_TAB_HISTORY', true],
        'report'         => ['COM_FGSYSTEMREPORT_TAB_REPORT', true],
        'diskusage'      => ['COM_FGSYSTEMREPORT_TAB_DISKUSAGE', false],
        'database'       => ['COM_FGSYSTEMREPORT_TAB_DATABASE', false],
        'errors'         => ['COM_FGSYSTEMREPORT_TAB_ERRORS', true],
        'logs'           => ['COM_FGSYSTEMREPORT_TAB_LOGS', true],
        'icons'          => ['COM_FGSYSTEMREPORT_TAB_ICONS', false],
    ];

    public function display($tpl = null): void
    {
        $this->fgAssets();

        ob_start();

        try {
            parent::display($tpl);
        } finally {
            $out = (string) ob_get_clean();
        }

        echo '<div class="fgsr">' . ($this->fgChrome ? $this->fgHeader() : '') . $out . '</div>';
    }

    /** Adds the scoped stylesheet once per request. */
    private function fgAssets(): void
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;
        $file = JPATH_ADMINISTRATOR . '/components/com_fgsystemreport/assets/css/fg-ui.css';

        if (!is_file($file)) {
            return;
        }

        try {
            Factory::getApplication()->getDocument()->getWebAssetManager()
                ->addInlineStyle((string) file_get_contents($file), [], ['name' => 'com_fgsystemreport.ui']);
        } catch (\Throwable $e) {
            // unstyled but functional
        }
    }

    private function fgVersion(): string
    {
        static $ver = null;

        if ($ver === null) {
            $ver = '';

            try {
                $info = Installer::parseXMLInstallFile(JPATH_ADMINISTRATOR . '/components/com_fgsystemreport/fgsystemreport.xml');
                $ver  = is_array($info) ? (string) ($info['version'] ?? '') : '';
            } catch (\Throwable $e) {
                // leave empty
            }
        }

        return $ver;
    }

    private function fgHeader(): string
    {
        $h        = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $app      = Factory::getApplication();
        $identity = $app->getIdentity();
        $current  = (string) $app->getInput()->getCmd('view', 'fgsystemreport');
        $isAdmin  = $identity && $identity->authorise('core.admin', 'com_fgsystemreport');
        $logo     = Uri::root(true) . '/administrator/components/com_fgsystemreport/assets/images/fg-logo.webp';
        $ver      = $this->fgVersion();

        $pill = '';

        if ($isAdmin) {
            try {
                $run = HistoryHelper::getLatestRun();
            } catch (\Throwable $e) {
                $run = null;
            }

            if ($run) {
                $cls  = (int) $run->count_danger > 0 ? 'is-bad' : ((int) $run->count_warning > 0 ? 'is-warn' : 'is-ok');
                $pill = '<a class="fgsr-pill ' . $cls . '" href="index.php?option=com_fgsystemreport&amp;view=security">'
                    . $h(Text::sprintf('COM_FGSYSTEMREPORT_HEAD_SCORE', (int) $run->score)) . '</a>';
            }
        }

        $tabs = '';

        foreach (self::TABS as $view => [$key, $needsAdmin]) {
            if ($needsAdmin && !$isAdmin) {
                continue;
            }

            $tabs .= '<a class="fgsr-tab' . ($view === $current ? ' is-active' : '') . '" href="index.php?option=com_fgsystemreport&amp;view=' . $view . '">'
                . $h(Text::_($key)) . '</a>';
        }

        return '<div class="fgsr-head"><div class="fgsr-brand"><img src="' . $h($logo) . '" alt="" width="42" height="42">'
            . '<div><div class="fgsr-brand-name">FG System Report</div><div class="fgsr-brand-sub">'
            . $h(Text::_('COM_FGSYSTEMREPORT_HEAD_TAGLINE')) . ($ver !== '' ? ' &middot; v' . $h($ver) : '') . '</div></div></div>'
            . $pill . '</div><nav class="fgsr-nav" aria-label="FG System Report">' . $tabs . '</nav>';
    }
}

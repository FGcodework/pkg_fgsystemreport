<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

// No direct access
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * The Security Check table of the PDF report, laid out in the same themes as
 * the Security page (SecurityGroupHelper) so both read alike.
 *
 * @since  1.18.8
 */
final class SecurityPdfHtml
{
    private const COLORS = [
        'ok'      => ['#eafaf1', '#1e7e34'],
        'warning' => ['#fdf3e3', '#b8720a'],
        'danger'  => ['#fdecea', '#c0392b'],
        'info'    => ['#f1f1f1', '#666666'],
    ];

    private const LABELS = [
        'ok'      => 'COM_FGSYSTEMREPORT_SEC_STATUS_OK',
        'warning' => 'COM_FGSYSTEMREPORT_SEC_STATUS_WARNING',
        'danger'  => 'COM_FGSYSTEMREPORT_SEC_STATUS_DANGER',
        'info'    => 'COM_FGSYSTEMREPORT_SEC_STATUS_INFO',
    ];

    /**
     * HTML pieces for TCPDF::writeHTML(), one per theme. The column heads are
     * part of the first piece. Each theme is a table whose first row is a pale
     * grey band (theme, number of checks, problems); when a theme runs over a
     * page the band is repeated at the top of the next one. The caller starts
     * a piece on a new page only when its first rows would not fit
     * (TCPDF::checkPageBreak), so pages are filled instead of left half empty.
     *
     * @param   object[]  $checks  rows with key, label, status, value, note (and accepted / accept_expired)
     *
     * @return  string[]
     *
     * @since   1.18.8
     */
    public static function chunks(array $checks): array
    {
        $chunks = [];

        foreach (SecurityGroupHelper::group($checks) as $gid => $rows) {
            $chunks[] = self::table('<thead>' . self::band($gid, $rows) . '</thead>' . self::rows($rows));
        }

        if ($chunks) {
            $chunks[0] = self::table(self::head(), 'font-weight:bold;') . $chunks[0];
        }

        return $chunks;
    }

    /** All pieces in one string (tests, or a caller that does not manage page breaks). */
    public static function render(array $checks): string
    {
        return implode('', self::chunks($checks));
    }

    private static function table(string $inner, string $style = ''): string
    {
        return '<table cellpadding="4" style="border-collapse:collapse; width:100%;' . $style . '">' . $inner . '</table>';
    }

    private static function head(): string
    {
        $th = static function (string $w, string $align, string $key): string {
            return '<th align="' . $align . '" style="width:' . $w . '; border:1px solid #ddd; background-color:#f0f0f0; font-size:10px;">'
                . htmlspecialchars(Text::_($key)) . '</th>';
        };

        return '<tr>' . $th('24%', 'left', 'COM_FGSYSTEMREPORT_SEC_COL_CHECK') . $th('16%', 'center', 'COM_FGSYSTEMREPORT_SEC_COL_STATUS')
            . $th('22%', 'left', 'COM_FGSYSTEMREPORT_SEC_COL_VALUE') . $th('38%', 'left', 'COM_FGSYSTEMREPORT_SEC_COL_NOTE') . '</tr>';
    }

    private static function band(string $gid, array $rows): string
    {
        $n    = SecurityGroupHelper::counts($rows);
        $text = '<strong>' . htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_SECGROUP_' . strtoupper($gid))) . '</strong> (' . (int) $n['total'] . ')';

        if ($n['danger']) {
            $text .= ' &nbsp;<strong style="color:#c0392b;">' . (int) $n['danger'] . ' ' . htmlspecialchars(Text::_(self::LABELS['danger'])) . '</strong>';
        }

        if ($n['warning']) {
            $text .= ' &nbsp;<strong style="color:#b8720a;">' . (int) $n['warning'] . ' ' . htmlspecialchars(Text::_(self::LABELS['warning'])) . '</strong>';
        }

        return '<tr><td colspan="4" style="border:1px solid #ddd; background-color:#e9ebee; font-size:10px;">' . $text . '</td></tr>';
    }

    private static function rows(array $rows): string
    {
        $html = '';

        foreach ($rows as $check) {
            [$bg, $fg] = self::COLORS[$check->status] ?? self::COLORS['info'];

            $status = htmlspecialchars(Text::_(self::LABELS[$check->status] ?? self::LABELS['info']));

            if (!empty($check->accepted)) {
                $status .= '<br/><span style="font-size:7px; font-weight:normal;">' . htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_ACCEPTED_BADGE')) . '</span>';
            } elseif (!empty($check->accept_expired)) {
                $status .= '<br/><span style="font-size:7px; font-weight:normal;">' . htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_ACCEPTED_EXPIRED_BADGE')) . '</span>';
            }

            $html .= '<tr nobr="true">'
                . '<td align="left" style="width:24%; border:1px solid #ddd; font-size:9px; background-color:' . $bg . ';">' . htmlspecialchars((string) $check->label) . '</td>'
                . '<td align="center" style="width:16%; border:1px solid #ddd; font-size:9px; background-color:' . $bg . '; color:' . $fg . '; font-weight:bold;">' . $status . '</td>'
                . '<td align="left" style="width:22%; border:1px solid #ddd; font-size:9px; background-color:' . $bg . ';">' . htmlspecialchars((string) $check->value) . '</td>'
                . '<td align="left" style="width:38%; border:1px solid #ddd; font-size:8px; background-color:' . $bg . ';">' . htmlspecialchars((string) $check->note) . '</td>'
                . '</tr>';
        }

        return $html;
    }
}

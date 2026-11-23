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
 * Rows of the "Installed Extensions" table of the PDF report.
 *
 * Compact on purpose: one line per extension where the name fits. A package
 * is amber and bold, its parts follow it with a "»" in front of the name (so
 * no "in <package>" line under the type), and an update is a light red row.
 *
 * @since  1.18.10
 */
final class ExtensionsPdfHtml
{
    /** Column widths in percent: #, name, type, state, current version, new version. */
    private const W = [6, 37, 13, 11, 16, 17];

    private const CELL = 'border:1px solid #ddd; vertical-align:middle;';

    /** Opening tag and header row (a thead, so a long table repeats it on every page). */
    public static function head(): string
    {
        $th = static function (int $i, string $key): string {
            return '<th align="center" style="width:' . self::W[$i] . '%; ' . self::CELL . ' font-size:10px;">' . ($key === '#' ? '#' : htmlspecialchars(Text::_($key))) . '</th>';
        };

        return '<table cellspacing="0" cellpadding="2" style="border-collapse:collapse; width:100%;"><thead><tr>'
            . $th(0, '#') . $th(1, 'COM_FGSYSTEMREPORT_EXT_COL_NAME') . $th(2, 'COM_FGSYSTEMREPORT_EXT_COL_TYPE')
            . $th(3, 'COM_FGSYSTEMREPORT_EXT_COL_STATE') . $th(4, 'COM_FGSYSTEMREPORT_EXT_COL_CURRENT_VERSION')
            . $th(5, 'COM_FGSYSTEMREPORT_EXT_COL_NEW_VERSION') . '</tr></thead>';
    }

    /**
     * One table row.
     *
     * @param   object  $ext      is_package, is_child, has_update, type, display_name, enabled, installed_version, available_version
     * @param   int     $counter  number of the row
     *
     * @since   1.18.10
     */
    public static function row(object $ext, int $counter): string
    {
        $bg = $ext->is_package ? ' background-color:#fff3cd;' : ($ext->has_update ? ' background-color:#fdecea;' : '');

        $typeKey   = 'COM_FGSYSTEMREPORT_TYPE_' . strtoupper((string) $ext->type);
        $typeLabel = Text::_($typeKey);

        if ($typeLabel === $typeKey) {
            $typeLabel = ucfirst((string) $ext->type);
        }

        $typeLabel = htmlspecialchars($typeLabel);
        $name      = htmlspecialchars((string) $ext->display_name);

        if ($ext->is_package) {
            $typeLabel = '<b>' . $typeLabel . '</b>';
            $name      = '<b>' . $name . '</b>';
        } elseif ($ext->is_child) {
            $name = '<span style="color:#555555;">&nbsp;&nbsp;&raquo; ' . $name . '</span>';
        }

        $state = (int) $ext->enabled === 1
            ? '<span style="color:#1e7e34;">' . htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_EXT_ENABLED')) . '</span>'
            : '<b><span style="color:#c0392b;">' . htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_EXT_DISABLED')) . '</span></b>';

        $new = $ext->has_update
            ? '<b><span style="color:#c0392b;">' . htmlspecialchars((string) $ext->available_version) . '</span></b>'
            : htmlspecialchars((string) $ext->available_version);

        $td = static function (int $i, string $align, string $content) use ($bg): string {
            return '<td align="' . $align . '" style="width:' . self::W[$i] . '%; ' . self::CELL . ' font-size:9px;' . $bg . '">' . $content . '</td>';
        };

        return '<tr nobr="true">' . $td(0, 'center', (string) $counter) . $td(1, 'left', $name) . $td(2, 'center', $typeLabel)
            . $td(3, 'center', $state) . $td(4, 'center', htmlspecialchars((string) $ext->installed_version)) . $td(5, 'center', $new) . '</tr>';
    }
}

<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

// No direct access
\defined('_JEXEC') or die;

/**
 * Order of the installed-extensions list: alphabetical by the name the person
 * sees (the translated display name, not the raw name in the database, which
 * is an language constant such as COM_FOO or an element name and sorts
 * differently), with the parts of a package directly under their package.
 *
 * @since  1.18.11
 */
final class ExtensionOrderHelper
{
    /**
     * Key a name is sorted by: no case, no accents, so "Éxample" sits with "E".
     *
     * @since  1.18.11
     */
    public static function sortKey(string $name): string
    {
        static $tr = null;

        if ($tr === null) {
            $tr = class_exists(\Transliterator::class) ? (\Transliterator::create('Any-Latin; Latin-ASCII; Lower()') ?: false) : false;
        }

        $key = $tr ? (string) $tr->transliterate($name) : self::stripAccents(mb_strtolower($name, 'UTF-8'));

        return trim($key);
    }

    /**
     * Without the intl extension: the accents of Slovak, Czech, German and the common western languages.
     *
     * @since  1.18.11
     */
    public static function stripAccents(string $lower): string
    {
        return strtr($lower, [
            'á' => 'a', 'ä' => 'a', 'à' => 'a', 'â' => 'a', 'č' => 'c', 'ç' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ï' => 'i', 'ĺ' => 'l', 'ľ' => 'l', 'ň' => 'n', 'ñ' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ò' => 'o',
            'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ß' => 'ss', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ù' => 'u', 'ý' => 'y', 'ž' => 'z',
        ]);
    }

    /**
     * @param   object[]  $extensions    rows with extension_id, display_name, type, package_id
     * @param   array     $packageNames  extension_id of a package => its name (the packages that are in the list)
     *
     * @return  object[]  top level rows A-Z, each package followed by its parts A-Z
     *
     * @since   1.18.11
     */
    public static function order(array $extensions, array $packageNames): array
    {
        $cmp = static function (object $a, object $b): int {
            return strnatcmp(self::sortKey((string) $a->display_name), self::sortKey((string) $b->display_name))
                ?: strcmp((string) $a->type, (string) $b->type)
                ?: ((int) $a->extension_id <=> (int) $b->extension_id);
        };

        $children = [];
        $top      = [];

        foreach ($extensions as $ext) {
            if (!empty($ext->package_id) && isset($packageNames[$ext->package_id])) {
                $children[$ext->package_id][] = $ext;
            } else {
                $top[] = $ext;
            }
        }

        usort($top, $cmp);

        $ordered = [];

        foreach ($top as $ext) {
            $ordered[] = $ext;

            if ($ext->type === 'package' && isset($children[$ext->extension_id])) {
                $parts = $children[$ext->extension_id];
                usort($parts, $cmp);

                foreach ($parts as $part) {
                    $ordered[] = $part;
                }
            }
        }

        return $ordered;
    }
}

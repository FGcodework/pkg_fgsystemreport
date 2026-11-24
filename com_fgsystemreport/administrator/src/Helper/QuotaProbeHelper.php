<?php

/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * One-off diagnostic: runs a few fixed, read-only commands that may tell how
 * much room the hosting account has, and returns their raw output as text.
 * Nothing is parsed or saved; the output is meant to be read by a person.
 *
 * The commands are constants - no input of any kind goes into them.
 *
 * @since  1.18.17
 */
final class QuotaProbeHelper
{
    /** Label => command (run through /bin/sh, stderr included). */
    public const COMMANDS = [
        'user'         => 'id -un',
        'quota'        => 'quota -w',
        'quota (user)' => 'quota -u -w',
        'tools'        => 'command -v quota cloudlinux-limits lvectl lveinfo repquota',
    ];

    private const MAX_BYTES = 4096;

    /**
     * @return  string  report text, or '' when exec() is not available
     */
    public static function run(): string
    {
        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));

        if (!\function_exists('exec') || \in_array('exec', $disabled, true)) {
            return '';
        }

        $cmds       = self::COMMANDS;
        $cmds['df'] = 'df -k ' . escapeshellarg(JPATH_ROOT);
        $report = [];

        foreach ($cmds as $label => $cmd) {
            $out  = [];
            $code = null;
            @exec($cmd . ' 2>&1', $out, $code);
            $text = trim(implode("\n", $out));

            if (\strlen($text) > self::MAX_BYTES) {
                $text = substr($text, 0, self::MAX_BYTES) . "\n[...]";
            }

            $report[] = '$ ' . $cmd . "\n" . ($text === '' ? '(no output)' : $text) . "\n[exit " . ($code ?? '?') . ']';
        }

        return implode("\n\n", $report);
    }
}

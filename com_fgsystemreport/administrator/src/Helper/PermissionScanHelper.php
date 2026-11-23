<?php

/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Looks through the site's files for permissions that let *other users on
 * the server* change the code, or that give a file the rights of its owner.
 *
 * Deliberately NOT the strict "files 644, folders 755" rule: on shared
 * hosting 664/775 is normal (the web user and the owner share a group), so
 * flagging it would bury the real problems. What is flagged:
 *  - world-writable files (777, 666, ...), and PHP files above all;
 *  - world-writable folders without the sticky bit;
 *  - setuid / setgid files.
 *
 * The walk is bounded (time and number of entries), never follows symlinks
 * and skips the folders Joomla writes to by design.
 *
 * @since  1.18.13
 */
final class PermissionScanHelper
{
    /** Folders (relative to the site root) that are not walked. */
    public const SKIP = ['tmp', 'cache', 'logs', 'administrator/cache', 'administrator/logs'];

    /** Folder names that are not walked at any depth. */
    public const SKIP_NAMES = ['.git', 'node_modules'];

    public const MAX_ENTRIES = 60000;

    public const MAX_SECONDS = 4.0;

    /**
     * Classify one entry.
     *
     * @param   int     $perms   fileperms() value
     * @param   bool    $isDir   whether the entry is a folder
     * @param   string  $name    file name (used to spot PHP files)
     *
     * @return  string  'world_php' | 'world_file' | 'world_dir' | 'special' | '' (nothing to report)
     */
    public static function classify(int $perms, bool $isDir, string $name = ''): string
    {
        $worldWritable = ($perms & 0002) !== 0;

        if ($isDir) {
            // A world-writable folder with the sticky bit (like /tmp) only lets
            // users delete their own files: not a problem.
            return $worldWritable && ($perms & 01000) === 0 ? 'world_dir' : '';
        }

        if ($worldWritable) {
            return preg_match('/\.(php\d?|phtml|phar)$/i', $name) ? 'world_php' : 'world_file';
        }

        return ($perms & 06000) !== 0 ? 'special' : '';
    }

    /**
     * Walk $root and collect findings.
     *
     * @param   string  $root        folder to walk
     * @param   float   $maxSeconds  time budget
     * @param   int     $maxEntries  entry budget
     *
     * @return  array|null  null when it cannot be done (Windows, unreadable root); otherwise
     *                      ['counts'=>[kind=>int], 'examples'=>[kind=>list of relative paths],
     *                       'scanned'=>int, 'complete'=>bool]
     */
    public static function scan(string $root, float $maxSeconds = self::MAX_SECONDS, int $maxEntries = self::MAX_ENTRIES): ?array
    {
        if (DIRECTORY_SEPARATOR === '\\' || !is_dir($root) || !is_readable($root)) {
            return null;
        }

        $root     = rtrim(str_replace('\\', '/', $root), '/');
        $skip     = array_map(static fn ($rel) => $root . '/' . $rel, self::SKIP);
        $kinds    = ['world_php', 'world_file', 'world_dir', 'special'];
        $counts   = array_fill_keys($kinds, 0);
        $examples = array_fill_keys($kinds, []);
        $scanned  = 0;
        $complete = true;
        $start    = microtime(true);

        try {
            $dir = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
            $it  = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator($dir, static function (\SplFileInfo $f) use ($skip): bool {
                    if ($f->isLink()) {
                        return false;
                    }

                    if (!$f->isDir()) {
                        return true;
                    }

                    return !in_array(str_replace('\\', '/', $f->getPathname()), $skip, true)
                        && !in_array($f->getFilename(), self::SKIP_NAMES, true);
                }),
                \RecursiveIteratorIterator::SELF_FIRST,
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($it as $f) {
                if (++$scanned > $maxEntries || (microtime(true) - $start) > $maxSeconds) {
                    $complete = false;
                    break;
                }

                $kind = self::classify((int) $f->getPerms(), $f->isDir(), $f->getFilename());

                if ($kind === '') {
                    continue;
                }

                $counts[$kind]++;

                if (count($examples[$kind]) < 5) {
                    $examples[$kind][] = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/') . ($f->isDir() ? '/' : '');
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return ['counts' => $counts, 'examples' => $examples, 'scanned' => min($scanned, $maxEntries), 'complete' => $complete];
    }
}

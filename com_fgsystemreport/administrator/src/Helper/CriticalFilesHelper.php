<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Watches the files an attacker changes first: the entry points, the files
 * that start the framework, configuration.php and the server / PHP settings
 * files. A SHA-1 of each is kept from the first Security Check on; a change
 * is recorded in the change timeline and shown by the Security Check for
 * SHOW_DAYS days.
 *
 * A change that matches something an administrator did - an extension or
 * Joomla update installed, Global Configuration saved - at about the time the
 * file changed is shown as information, anything else as a finding.
 *
 * diff(), isExpected(), severity() and evaluate() are pure
 * (tests/CriticalFilesTest.php); collect() reads the files and the database.
 *
 * @since  1.16.0
 */
final class CriticalFilesHelper
{
    private const TABLE = '#__fgsystemreport_critfile';

    /** Days a detected change stays in the Security Check. */
    public const SHOW_DAYS = 14;

    /** Seconds before / after a file changed in which an update or a configuration save explains it. */
    private const WINDOW_BEFORE = 1800;
    private const WINDOW_AFTER  = 600;

    public const FILES = [
        'index.php', 'administrator/index.php', 'api/index.php',
        'includes/defines.php', 'includes/framework.php', 'includes/app.php',
        'administrator/includes/defines.php', 'administrator/includes/framework.php', 'administrator/includes/app.php',
        'configuration.php',
        '.htaccess', 'administrator/.htaccess', 'web.config',
        '.user.ini', 'php.ini', 'administrator/.user.ini', 'administrator/php.ini',
    ];

    /** @var bool|null */
    private static $tableReady = null;

    /** @var string */
    private static $tableError = '';

    // ------------------------------------------------------------------ pure

    /**
     * Changes between the stored state and the files as they are now.
     *
     * @param   array<string,array{hash:string}>             $baseline      stored: path => hash ('' = file was absent)
     * @param   array<string,array{hash:string,mtime:int}|null>  $now      current: path => data, null = file absent
     * @param   int                                           $baselineTime  UNIX time the first baseline was taken
     *
     * @return  list<array{path:string,type:string,mtime:int}>  type: modified | added | deleted
     */
    public static function diff(array $baseline, array $now, int $baselineTime): array
    {
        $changes = [];

        foreach ($now as $path => $cur) {
            $old = $baseline[$path] ?? null;

            if ($old === null) {
                // Path new in the list: a file that already existed is simply taken as it is.
                if ($cur !== null && $cur['mtime'] > $baselineTime) {
                    $changes[] = ['path' => $path, 'type' => 'added', 'mtime' => $cur['mtime']];
                }

                continue;
            }

            $oldHash = (string) $old['hash'];

            if ($cur === null) {
                if ($oldHash !== '') {
                    $changes[] = ['path' => $path, 'type' => 'deleted', 'mtime' => 0];
                }
            } elseif ($oldHash === '') {
                $changes[] = ['path' => $path, 'type' => 'added', 'mtime' => $cur['mtime']];
            } elseif ($oldHash !== $cur['hash']) {
                $changes[] = ['path' => $path, 'type' => 'modified', 'mtime' => $cur['mtime']];
            }
        }

        return $changes;
    }

    /**
     * Does an update / installation or a Global Configuration save explain a change of this file?
     *
     * @param   string    $path   File
     * @param   int       $time   When it changed (UNIX)
     * @param   object[]  $events Change timeline entries: created_at (UTC), type
     */
    public static function isExpected(string $path, int $time, array $events): bool
    {
        if ($time <= 0) {
            return false;
        }

        foreach ($events as $ev) {
            $at   = strtotime((string) ($ev->created_at ?? '') . ' UTC');
            $type = (string) ($ev->type ?? '');

            if ($at === false) {
                continue;
            }

            $delta = $at - $time;   // positive: the event was recorded after the file changed

            if ($path === 'configuration.php' && $type === 'config' && abs($delta) <= self::WINDOW_AFTER) {
                return true;
            }

            if (\in_array($type, ['install', 'update', 'uninstall'], true) && $delta >= -self::WINDOW_AFTER && $delta <= self::WINDOW_BEFORE) {
                return true;
            }
        }

        return false;
    }

    /** @return  string  danger | warning | info */
    public static function severity(string $path, string $type, bool $expected): string
    {
        if ($expected) {
            return 'info';
        }

        // PHP that runs on every request, other than the configuration
        if (str_ends_with($path, '.php') && $path !== 'configuration.php' && !str_starts_with($path, 'php.')) {
            return 'danger';
        }

        return 'warning';
    }

    /**
     * Status of the Security Check row.
     *
     * @param   array<int,array{path:string,type:string,expected:bool,at:int}>  $changes  stored changes (at = detection time)
     *
     * @return  array{status:string,active:array}  active: the changes still shown, most serious first
     */
    public static function evaluate(array $changes, int $now): array
    {
        $rank   = ['danger' => 0, 'warning' => 1, 'info' => 2];
        $active = [];

        foreach ($changes as $c) {
            if ((int) $c['at'] >= $now - self::SHOW_DAYS * 86400) {
                $c['severity'] = self::severity((string) $c['path'], (string) $c['type'], (bool) $c['expected']);
                $active[]      = $c;
            }
        }

        usort($active, static fn (array $a, array $b): int => $rank[$a['severity']] <=> $rank[$b['severity']] ?: $b['at'] <=> $a['at']);

        $status = $active ? ($active[0]['severity']) : 'ok';

        return ['status' => $status, 'active' => $active];
    }

    // ------------------------------------------------------------------ storage

    public static function ensureTable(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            if (stripos($db->getServerType(), 'postgre') !== false) {
                $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
                    . '"path" VARCHAR(190) NOT NULL PRIMARY KEY, "hash" VARCHAR(40) NOT NULL DEFAULT \'\', "mtime" BIGINT NOT NULL DEFAULT 0, '
                    . '"seen_at" TIMESTAMP NOT NULL, "change_type" VARCHAR(12) NOT NULL DEFAULT \'\', "changed_at" TIMESTAMP NULL, "expected" SMALLINT NOT NULL DEFAULT 0)';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
                    . '`path` VARCHAR(190) NOT NULL, `hash` VARCHAR(40) NOT NULL DEFAULT \'\', `mtime` BIGINT NOT NULL DEFAULT 0, '
                    . '`seen_at` DATETIME NOT NULL, `change_type` VARCHAR(12) NOT NULL DEFAULT \'\', `changed_at` DATETIME NULL DEFAULT NULL, '
                    . '`expected` TINYINT NOT NULL DEFAULT 0, PRIMARY KEY (`path`)) DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
            }

            $db->setQuery($sql)->execute();
            self::$tableReady = true;
        } catch (\Throwable $e) {
            self::$tableReady = false;
            self::$tableError = $e->getMessage();
            ChangeLogHelper::logError('criticalFiles table', $e);
        }

        return self::$tableReady;
    }

    /**
     * Current state of the watched files.
     *
     * @return  array<string,array{hash:string,mtime:int}|null>
     */
    public static function read(string $root = JPATH_ROOT): array
    {
        $now = [];

        foreach (self::FILES as $rel) {
            $file = $root . '/' . $rel;

            if (!is_file($file)) {
                $now[$rel] = null;

                continue;
            }

            $hash = @sha1_file($file);

            // unreadable: leave it out rather than report a change that is not one
            if ($hash === false) {
                continue;
            }

            $now[$rel] = ['hash' => $hash, 'mtime' => (int) @filemtime($file)];
        }

        return $now;
    }

    /**
     * Compare, store, record the timeline and build the Security Check row.
     *
     * @return  array{key:string,status:string,value:string,note:string}  an information row naming the cause when the table cannot be used
     */
    public static function collect(): array
    {
        if (!self::ensureTable()) {
            return self::unavailable(self::$tableError);
        }

        try {
            $db   = Factory::getContainer()->get('DatabaseDriver');
            $time = time();
            $sql  = Factory::getDate($time)->toSql();
            $rows = (array) $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE)))->loadObjectList('path');
            $now  = self::read();
            $key  = 'COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES';

            // First run: take the files as they are, report nothing.
            if (!$rows) {
                foreach ($now as $rel => $cur) {
                    $row = (object) [
                        'path' => $rel, 'hash' => $cur['hash'] ?? '', 'mtime' => (int) ($cur['mtime'] ?? 0), 'seen_at' => $sql,
                        'change_type' => '', 'changed_at' => null, 'expected' => 0,
                    ];
                    $db->insertObject(self::TABLE, $row, 'path');
                }

                return ['key' => $key, 'status' => 'ok', 'value' => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_BASELINE', \count(array_filter($now))), 'note' => ''];
            }

            $baseline     = [];
            $baselineTime = $time;

            foreach ($rows as $rel => $r) {
                $baseline[$rel] = ['hash' => (string) $r->hash];
                $baselineTime   = min($baselineTime, (int) strtotime($r->seen_at . ' UTC'));
            }

            $changes = self::diff($baseline, $now, $baselineTime);

            if ($changes) {
                $events = ChangeLogHelper::getEvents(self::SHOW_DAYS + 1, 500);

                foreach ($changes as $c) {
                    $rel      = $c['path'];
                    $cur      = $now[$rel] ?? null;
                    $expected = self::isExpected($rel, $c['mtime'] ?: $time, $events);
                    $obj      = (object) [
                        'path' => $rel, 'hash' => $cur['hash'] ?? '', 'mtime' => (int) ($cur['mtime'] ?? 0),
                        'change_type' => $c['type'], 'changed_at' => $sql, 'expected' => $expected ? 1 : 0,
                    ];

                    if (isset($rows[$rel])) {
                        $db->updateObject(self::TABLE, $obj, 'path');
                    } else {
                        $obj->seen_at = $sql;
                        $db->insertObject(self::TABLE, $obj, 'path');
                    }

                    ChangeLogHelper::record('file', $rel, 'file_' . $c['type']);
                }

                $rows = (array) $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE)))->loadObjectList('path');
            } else {
                // paths of the list that were never stored (list grew): take them as they are
                foreach ($now as $rel => $cur) {
                    if (!isset($rows[$rel])) {
                        $row = (object) [
                            'path' => $rel, 'hash' => $cur['hash'] ?? '', 'mtime' => (int) ($cur['mtime'] ?? 0), 'seen_at' => $sql,
                            'change_type' => '', 'changed_at' => null, 'expected' => 0,
                        ];
                        $db->insertObject(self::TABLE, $row, 'path');
                    }
                }
            }

            $stored = [];

            foreach ($rows as $rel => $r) {
                if ((string) $r->change_type !== '' && $r->changed_at) {
                    $stored[] = ['path' => (string) $rel, 'type' => (string) $r->change_type, 'expected' => (bool) $r->expected, 'at' => (int) strtotime($r->changed_at . ' UTC')];
                }
            }

            $eval = self::evaluate($stored, $time);

            if ($eval['status'] === 'ok') {
                return ['key' => $key, 'status' => 'ok', 'value' => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_WATCHED', \count(array_filter($now))), 'note' => ''];
            }

            $notes = [];

            foreach (\array_slice($eval['active'], 0, 6) as $c) {
                $notes[] = $c['path'] . ' (' . Text::_('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_T_' . strtoupper($c['type'])) . ', ' . gmdate('Y-m-d H:i', $c['at']) . ' UTC'
                    . ($c['expected'] ? ', ' . Text::_('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_EXPECTED') : '') . ')';
            }

            return [
                'key'    => $key,
                'status' => $eval['status'],
                'value'  => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_CHANGED', \count($eval['active'])),
                'note'   => implode('; ', $notes) . ' ' . Text::sprintf('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_HINT', self::SHOW_DAYS),
            ];
        } catch (\Throwable $e) {
            ChangeLogHelper::logError('criticalFiles', $e);

            return self::unavailable($e->getMessage());
        }
    }

    /** The row when the watch cannot work: say why instead of leaving the row out. */
    private static function unavailable(string $why): array
    {
        return [
            'key'    => 'COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES',
            'status' => 'info',
            'value'  => Text::_('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_UNAVAILABLE'),
            'note'   => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CRITICAL_FILES_UNAVAILABLE_NOTE', mb_substr($why, 0, 300)),
        ];
    }
}

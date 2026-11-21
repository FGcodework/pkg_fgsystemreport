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
use Joomla\CMS\Log\Log;

/**
 * Core file check: storage of the result, how it is judged and the Security
 * Check row. The comparison itself is CoreIntegrityEngine.
 *
 * The check is started from the Security page (it downloads about 30 MB, so it
 * never runs on its own); the Security Check shows the latest result.
 *
 * evaluate() and isCriticalPath() are pure (tests/CoreIntegrityTest.php).
 *
 * @since  1.16.0
 */
final class CoreIntegrityHelper
{
    private const TABLE = '#__fgsystemreport_corecheck';

    /** After this many days the result is only information: the files may have changed since. */
    public const STALE_DAYS = 90;

    /** Paths that a site customises on purpose: a change there is a warning, not an alarm. */
    private const CUSTOMISED = '~^(?:templates|administrator/templates|media/templates|language|administrator/language)/~';

    /** @var bool|null */
    private static $tableReady = null;

    /** @var string */
    private static $tableError = '';

    /** @var string  Why the last save to the database failed ('' = it did not). */
    public static $lastError = '';

    // ------------------------------------------------------------------ pure

    /**
     * Is a change of this file serious? Code of the core (PHP, JS, entry
     * points) outside the folders of templates and languages is.
     */
    public static function isCriticalPath(string $rel): bool
    {
        if (preg_match(self::CUSTOMISED, $rel)) {
            return false;
        }

        return (bool) preg_match('~\.(?:php\d?|phtml|phar|pht|inc|js|mjs)$~i', $rel);
    }

    /**
     * Judge a stored result.
     *
     * @param   array|null  $result      time, version, nModified, nMissing, modified[], missing[]
     * @param   string      $installed   Installed Joomla version
     * @param   int         $now         Current time (UNIX)
     *
     * @return  array{status:string,code:string,critical:int,days:int}
     *          code: never | stale | ok | modified | missing | both
     */
    public static function evaluate(?array $result, string $installed, int $now): array
    {
        if ($result === null || ($result['version'] ?? '') !== $installed) {
            return ['status' => 'info', 'code' => 'never', 'critical' => 0, 'days' => 0];
        }

        $days = (int) floor(max(0, $now - (int) ($result['time'] ?? 0)) / 86400);
        $nMod = (int) ($result['nModified'] ?? 0);
        $nMis = (int) ($result['nMissing'] ?? 0);

        if ($days >= self::STALE_DAYS) {
            return ['status' => 'info', 'code' => 'stale', 'critical' => 0, 'days' => $days];
        }

        if ($nMod === 0 && $nMis === 0) {
            return ['status' => 'ok', 'code' => 'ok', 'critical' => 0, 'days' => $days];
        }

        $critical = 0;

        foreach ((array) ($result['modified'] ?? []) as $rel) {
            if (self::isCriticalPath((string) $rel)) {
                $critical++;
            }
        }

        // The stored list is capped: with more changed files than listed, assume the rest is as serious.
        if ($nMod > \count((array) ($result['modified'] ?? [])) && $critical === 0 && $nMod > CoreIntegrityEngine::LIST_MAX) {
            $critical = 1;
        }

        return [
            'status'   => $critical > 0 ? 'danger' : 'warning',
            'code'     => $nMod > 0 && $nMis > 0 ? 'both' : ($nMod > 0 ? 'modified' : 'missing'),
            'critical' => $critical,
            'days'     => $days,
        ];
    }

    // ------------------------------------------------------------------ engine

    /** Work folder for the downloaded package and the list of fingerprints. */
    public static function workDir(): string
    {
        $tmp = '';

        try {
            $tmp = rtrim((string) Factory::getApplication()->get('tmp_path', ''), '/\\');
        } catch (\Throwable $e) {
            // fall back below
        }

        return ($tmp !== '' ? $tmp : JPATH_ROOT . '/tmp') . '/fgsystemreport-core';
    }

    public static function engine(): CoreIntegrityEngine
    {
        return new CoreIntegrityEngine(self::workDir(), JPATH_ROOT, JVERSION);
    }

    /**
     * One step of the check; the finished result is stored.
     *
     * @return  array{phase:string,progress:int,done:bool,error:?string,result:?array}
     */
    public static function step(): array
    {
        @set_time_limit(300);

        $r = self::engine()->step(8.0);

        if ($r['done'] && $r['result'] !== null) {
            $inDb   = self::save($r['result']);
            $inFile = self::saveFile($r['result']);

            // the result is kept in the database and in a file next to the fingerprints;
            // only when neither worked is the check reported as failed
            if (!$inDb && !$inFile) {
                $r['error'] = 'save';
            }
        }

        return $r;
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
                    . '"id" SERIAL PRIMARY KEY, "checked_at" TIMESTAMP NOT NULL, "version" VARCHAR(32) NOT NULL, '
                    . '"source" VARCHAR(16) NOT NULL DEFAULT \'\', "total" INTEGER NOT NULL DEFAULT 0, "checked" INTEGER NOT NULL DEFAULT 0, '
                    . '"n_modified" INTEGER NOT NULL DEFAULT 0, "n_missing" INTEGER NOT NULL DEFAULT 0, "details" TEXT NULL)';
            } else {
                $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABLE) . ' ('
                    . '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `checked_at` DATETIME NOT NULL, `version` VARCHAR(32) NOT NULL, '
                    . '`source` VARCHAR(16) NOT NULL DEFAULT \'\', `total` INT NOT NULL DEFAULT 0, `checked` INT NOT NULL DEFAULT 0, '
                    . '`n_modified` INT NOT NULL DEFAULT 0, `n_missing` INT NOT NULL DEFAULT 0, `details` MEDIUMTEXT NULL, '
                    . 'PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
            }

            $db->setQuery($sql)->execute();
            self::$tableReady = true;
        } catch (\Throwable $e) {
            self::$tableReady = false;
            self::$tableError = $e->getMessage();
            self::log('table', $e);
        }

        return self::$tableReady;
    }

    /** Store a finished result; only the latest one is kept. */
    public static function save(array $result): bool
    {
        if (!self::ensureTable()) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $db->setQuery('DELETE FROM ' . $db->quoteName(self::TABLE))->execute();
            $db->insertObject(self::TABLE, (object) [
                'checked_at' => Factory::getDate((int) ($result['time'] ?? time()))->toSql(),
                'version'    => mb_substr((string) ($result['version'] ?? ''), 0, 32),
                'source'     => mb_substr((string) ($result['source'] ?? ''), 0, 16),
                'total'      => (int) ($result['total'] ?? 0),
                'checked'    => (int) ($result['checked'] ?? 0),
                'n_modified' => (int) ($result['nModified'] ?? 0),
                'n_missing'  => (int) ($result['nMissing'] ?? 0),
                'details'    => (string) json_encode(['modified' => array_values((array) ($result['modified'] ?? [])), 'missing' => array_values((array) ($result['missing'] ?? []))], JSON_UNESCAPED_SLASHES),
            ]);

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            self::log('save', $e);

            return false;
        }
    }

    // ------------------------------------------------------------------ file copy of the result

    private static function resultFile(): string
    {
        return self::workDir() . '/core-result.json';
    }

    /** A copy of the result next to the fingerprints: the check still shows if the database refuses it. */
    private static function saveFile(array $result): bool
    {
        try {
            return is_dir(self::workDir()) && @file_put_contents(self::resultFile(), json_encode($result, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function latestFile(): ?array
    {
        $f = self::resultFile();
        $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;

        return \is_array($d) && isset($d['version'], $d['time']) ? $d : null;
    }

    private static function log(string $what, \Throwable $e): void
    {
        try {
            Log::add('FG System Report core check (' . $what . '): ' . $e->getMessage(), Log::WARNING, 'com_fgsystemreport');
        } catch (\Throwable $x) {
            // logging is best effort
        }
    }

    /**
     * The latest result: the database row, or the file copy when the database has none
     * (or a more recent file exists).
     *
     * @return  array|null  same layout the engine returns as "result"
     */
    public static function latest(): ?array
    {
        $db   = self::latestDb();
        $file = self::latestFile();

        if ($db === null) {
            return $file;
        }

        return $file !== null && (int) $file['time'] > (int) $db['time'] ? $file : $db;
    }

    /** @return  array|null */
    private static function latestDb(): ?array
    {
        if (!self::ensureTable()) {
            return null;
        }

        try {
            $db  = Factory::getContainer()->get('DatabaseDriver');
            $row = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))->order($db->quoteName('id') . ' DESC'), 0, 1)->loadObject();

            if (!$row) {
                return null;
            }

            $d = json_decode((string) $row->details, true);
            $d = \is_array($d) ? $d : [];

            return [
                'version'   => (string) $row->version,
                'source'    => (string) $row->source,
                'time'      => (new \DateTime((string) $row->checked_at, new \DateTimeZone('UTC')))->getTimestamp(),
                'total'     => (int) $row->total,
                'checked'   => (int) $row->checked,
                'nModified' => (int) $row->n_modified,
                'nMissing'  => (int) $row->n_missing,
                'modified'  => array_values((array) ($d['modified'] ?? [])),
                'missing'   => array_values((array) ($d['missing'] ?? [])),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------ Security Check row

    /**
     * @return  array{key:string,status:string,value:string,note:string}
     */
    public static function securityRow(): array
    {
        $result = self::latest();
        $eval   = self::evaluate($result, JVERSION, time());
        $key    = 'COM_FGSYSTEMREPORT_SEC_CORE_FILES';

        switch ($eval['code']) {
            case 'never':
                return ['key' => $key, 'status' => 'info', 'value' => Text::_('COM_FGSYSTEMREPORT_SEC_CORE_FILES_NEVER'), 'note' => Text::_('COM_FGSYSTEMREPORT_SEC_CORE_FILES_NEVER_NOTE')];

            case 'stale':
                return ['key' => $key, 'status' => 'info', 'value' => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CORE_FILES_DAYS_AGO', $eval['days']), 'note' => Text::_('COM_FGSYSTEMREPORT_SEC_CORE_FILES_STALE_NOTE')];

            case 'ok':
                return ['key' => $key, 'status' => 'ok', 'value' => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CORE_FILES_OK', (int) $result['checked'], (string) $result['version']),
                    'note' => $eval['days'] > 30 ? Text::sprintf('COM_FGSYSTEMREPORT_SEC_CORE_FILES_DAYS_AGO', $eval['days']) : ''];
        }

        $show = static function (array $paths): string {
            $head = \array_slice($paths, 0, 5);

            return implode(', ', $head) . (\count($paths) > 5 ? ' …' : '');
        };
        $parts = [];

        if ((int) $result['nModified'] > 0) {
            $parts[] = Text::sprintf('COM_FGSYSTEMREPORT_SEC_CORE_FILES_MODIFIED', (int) $result['nModified'], $show((array) $result['modified']));
        }

        if ((int) $result['nMissing'] > 0) {
            $parts[] = Text::sprintf('COM_FGSYSTEMREPORT_SEC_CORE_FILES_MISSING', (int) $result['nMissing'], $show((array) $result['missing']));
        }

        $parts[] = Text::_('COM_FGSYSTEMREPORT_SEC_CORE_FILES_HINT');

        return [
            'key'    => $key,
            'status' => $eval['status'],
            'value'  => Text::sprintf('COM_FGSYSTEMREPORT_SEC_CORE_FILES_DIFF', (int) $result['nModified'], (int) $result['nMissing']),
            'note'   => implode(' ', $parts),
        ];
    }
}

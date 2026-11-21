<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Compares the core files of the site with the official package of the
 * installed Joomla version.
 *
 * The Joomla project publishes the fingerprint of each release package, not of
 * each file. So the official update package of the installed version is
 * downloaded once, its SHA-256 is checked against the value published with the
 * release on GitHub, and the MD5 of every file it holds is kept in
 * core-<version>.json in the work folder; the package itself is then deleted.
 * Every file of that list is compared with the file on the site.
 *
 * Works in short steps (step() returns after about $budget seconds) so that it
 * also runs on hosts with a low time limit; the caller keeps calling step()
 * until "done" is true. State lives in the work folder, nothing is kept in
 * memory between calls.
 *
 * Plain PHP, no Joomla classes: the network access is injected (tests, and the
 * default implementations use the proxy settings of Joomla).
 *
 * @since  1.16.0
 */
final class CoreIntegrityEngine
{
    /** Files listed in a result (the counts stay exact beyond this). */
    public const LIST_MAX = 500;

    public const GITHUB_API = 'https://api.github.com/repos/joomla/joomla-cms/releases/tags/';

    private const MAX_BYTES = 104857600;

    /** Files of the package that a site normally renames, edits or deletes: not compared. */
    private const NOT_COMPARED = ['htaccess.txt', 'web.config.txt', 'robots.txt.dist', 'README.txt', 'LICENSE.txt'];

    /** Empty placeholder folders of the package: a site may delete the index.html. */
    private const PLACEHOLDERS = '~^(?:cache|tmp|files|images|cli|includes)/index\.html$~';

    private string $dir;
    private string $root;
    private string $version;

    /** @var callable(string):?array */
    private $fetchJson;

    /** @var callable(string,string):bool */
    private $download;

    /**
     * @param   string         $workDir    Folder for state and the downloaded package (created when missing)
     * @param   string         $root       Site root
     * @param   string         $version    Installed Joomla version, e.g. 5.4.1
     * @param   callable|null  $fetchJson  fn(string $url): ?array  - decoded JSON of a GET request
     * @param   callable|null  $download   fn(string $url, string $file): bool
     */
    public function __construct(string $workDir, string $root, string $version, ?callable $fetchJson = null, ?callable $download = null)
    {
        $this->dir       = rtrim($workDir, '/\\');
        $this->root      = rtrim($root, '/\\');
        $this->version   = $version;
        $this->fetchJson = $fetchJson ?? [self::class, 'httpJson'];
        $this->download  = $download ?? [self::class, 'httpDownload'];
    }

    // ------------------------------------------------------------------ pure helpers

    /** Only stable releases have an official package: 5.4.1, not 5.5.0-rc1 or 6.0.0-dev. */
    public static function isReleaseVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    public static function packageName(string $version): string
    {
        return 'Joomla_' . $version . '-Stable-Update_Package.zip';
    }

    /**
     * Download address and SHA-256 of the update package in the release data
     * returned by the GitHub API (/releases/tags/<version>).
     *
     * The SHA-256 is read from the asset's "digest" field; older releases
     * carry it in the release notes (a table row "[name](url) | `sha256`").
     *
     * @return  array{url:string,algo:string,hash:string,source:string}|null
     */
    public static function parseRelease(?array $json, string $version): ?array
    {
        if (!$json || !self::isReleaseVersion($version)) {
            return null;
        }

        $name = self::packageName($version);
        $hash = '';

        foreach ((array) ($json['assets'] ?? []) as $asset) {
            if (\is_array($asset) && ($asset['name'] ?? '') === $name
                && preg_match('/^sha256:([0-9a-f]{64})$/i', (string) ($asset['digest'] ?? ''), $m)) {
                $hash = strtolower($m[1]);
            }
        }

        if ($hash === '' && preg_match('~' . preg_quote($name, '~') . '\)\s*\|\s*`([0-9a-f]{64})`~i', (string) ($json['body'] ?? ''), $m)) {
            $hash = strtolower($m[1]);
        }

        if ($hash === '') {
            return null;
        }

        return [
            'url'    => 'https://github.com/joomla/joomla-cms/releases/download/' . rawurlencode($version) . '/' . $name,
            'algo'   => 'sha256',
            'hash'   => $hash,
            'source' => 'github',
        ];
    }

    /** May this path of the package be compared at all? */
    public static function isCompared(string $rel): bool
    {
        if ($rel === '' || str_contains('/' . $rel . '/', '/../') || str_starts_with($rel, '/') || str_starts_with($rel, 'installation/')) {
            return false;
        }

        return !\in_array($rel, self::NOT_COMPARED, true);
    }

    /** A package file that is not on the site is only a finding when it is not a placeholder. */
    public static function missingMatters(string $rel): bool
    {
        return !preg_match(self::PLACEHOLDERS, $rel);
    }

    // ------------------------------------------------------------------ files

    private function sumsFile(): string
    {
        return $this->dir . '/core-' . preg_replace('/[^0-9A-Za-z.-]/', '', $this->version) . '.json';
    }

    private function stateFile(): string
    {
        return $this->dir . '/.core-state.json';
    }

    private function zipFile(): string
    {
        return $this->dir . '/.core-package.zip';
    }

    private function partFile(): string
    {
        return $this->dir . '/.core-build.tsv';
    }

    private function loadJson(string $file): ?array
    {
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return \is_array($data) ? $data : null;
    }

    private function saveJson(string $file, array $data): void
    {
        @file_put_contents($file, (string) json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function ensureDir(): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            return false;
        }

        // nothing in here is meant to be served
        if (!is_file($this->dir . '/index.html')) {
            @file_put_contents($this->dir . '/index.html', '<!DOCTYPE html><title></title>');
        }

        if (!is_file($this->dir . '/.htaccess')) {
            @file_put_contents($this->dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }

        return is_writable($this->dir);
    }

    /** Remove the state, the half-built list and the package (the finished list stays). */
    public function reset(): void
    {
        @unlink($this->stateFile());
        @unlink($this->partFile());
        @unlink($this->zipFile());
    }

    /** Is the list of official fingerprints of this version already there? */
    public function hasFingerprints(): bool
    {
        $d = $this->loadJson($this->sumsFile());

        return $d !== null && !empty($d['sums']) && \is_array($d['sums']);
    }

    // ------------------------------------------------------------------ steps

    /**
     * One step.
     *
     * @return  array{phase:string,progress:int,done:bool,error:?string,result:?array}
     *          error is a short code (version, work, source, download, hash, zip) the caller turns into text
     */
    public function step(float $budget = 8.0): array
    {
        if (!self::isReleaseVersion($this->version)) {
            return $this->out('source', 0, false, 'version');
        }

        if (!$this->ensureDir()) {
            return $this->out('source', 0, false, 'work');
        }

        $state = $this->loadJson($this->stateFile());

        // a run left behind long ago is not continued (files may have changed meanwhile)
        if ($state === null || ($state['version'] ?? '') !== $this->version || (int) ($state['started'] ?? 0) < time() - 3600) {
            $this->reset();
            $state = ['version' => $this->version, 'phase' => $this->hasFingerprints() ? 'compare' : 'source', 'i' => 0, 'started' => time()];
            $this->saveJson($this->stateFile(), $state);
        }

        $until = microtime(true) + max(0.001, $budget);

        switch ($state['phase']) {
            case 'source':
                return $this->stepSource($state);
            case 'download':
                return $this->stepDownload($state);
            case 'build':
                return $this->stepBuild($state, $until);
            default:
                return $this->stepCompare($state, $until);
        }
    }

    private function out(string $phase, int $progress, bool $done, ?string $error = null, ?array $result = null): array
    {
        return ['phase' => $phase, 'progress' => $progress, 'done' => $done, 'error' => $error, 'result' => $result];
    }

    private function fail(string $phase, string $code): array
    {
        $this->reset();

        return $this->out($phase, 0, true, $code);
    }

    private function stepSource(array $state): array
    {
        $target = self::parseRelease(($this->fetchJson)(self::GITHUB_API . rawurlencode($this->version)), $this->version);

        if ($target === null) {
            return $this->fail('source', 'source');
        }

        $state['phase']  = 'download';
        $state['target'] = $target;
        $this->saveJson($this->stateFile(), $state);

        return $this->out('source', 5, false);
    }

    private function stepDownload(array $state): array
    {
        $t = (array) ($state['target'] ?? []);

        if (!isset($t['url'], $t['algo'], $t['hash'])) {
            return $this->fail('download', 'source');
        }

        @set_time_limit(300);
        @unlink($this->zipFile());

        if (!($this->download)((string) $t['url'], $this->zipFile()) || !is_file($this->zipFile())) {
            return $this->fail('download', 'download');
        }

        if (!hash_equals(strtolower((string) $t['hash']), (string) hash_file((string) $t['algo'], $this->zipFile()))) {
            return $this->fail('download', 'hash');
        }

        $state['phase'] = 'build';
        $state['i']     = 0;
        $this->saveJson($this->stateFile(), $state);
        @unlink($this->partFile());

        return $this->out('download', 20, false);
    }

    private function stepBuild(array $state, float $until): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return $this->fail('build', 'zip');
        }

        $zip = new \ZipArchive();

        if ($zip->open($this->zipFile()) !== true) {
            return $this->fail('build', 'zip');
        }

        $n    = $zip->numFiles;
        $i    = (int) ($state['i'] ?? 0);
        $part = @fopen($this->partFile(), 'ab');

        if (!$part) {
            $zip->close();

            return $this->fail('build', 'work');
        }

        $from = $i;

        for (; $i < $n; $i++) {
            // always at least one item per step, so a very small budget still makes progress
            if ($i > $from && microtime(true) > $until) {
                break;
            }

            $name = (string) $zip->getNameIndex($i);

            if ($name === '' || str_ends_with($name, '/') || !self::isCompared($name) || preg_match('/[\t\r\n]/', $name)) {
                continue;
            }

            $data = $zip->getFromIndex($i);

            if ($data !== false) {
                fwrite($part, $name . "\t" . md5($data) . "\n");
            }
        }

        fclose($part);
        $zip->close();

        if ($i < $n) {
            $state['i'] = $i;
            $this->saveJson($this->stateFile(), $state);

            return $this->out('build', 20 + (int) floor(30 * $i / max(1, $n)), false);
        }

        $sums = [];

        foreach ((array) file($this->partFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$name, $md5] = array_pad(explode("\t", $line, 2), 2, '');

            if ($name !== '' && preg_match('/^[0-9a-f]{32}$/', $md5) && self::isCompared($name)) {
                $sums[$name] = $md5;
            }
        }

        @unlink($this->partFile());
        @unlink($this->zipFile());

        if (!$sums) {
            return $this->fail('build', 'zip');
        }

        $this->saveJson($this->sumsFile(), [
            'version' => $this->version,
            'source'  => (string) ($state['target']['source'] ?? ''),
            'built'   => time(),
            'sums'    => $sums,
        ]);

        // lists of other versions are no longer needed
        foreach ((array) glob($this->dir . '/core-*.json') as $old) {
            if ($old !== $this->sumsFile()) {
                @unlink($old);
            }
        }

        $state = ['version' => $this->version, 'phase' => 'compare', 'i' => 0, 'started' => (int) ($state['started'] ?? time())];
        $this->saveJson($this->stateFile(), $state);

        return $this->out('build', 50, false);
    }

    private function stepCompare(array $state, float $until): array
    {
        $list = $this->loadJson($this->sumsFile());

        if (!$list || empty($list['sums'])) {
            return $this->fail('compare', 'source');
        }

        $sums  = $list['sums'];
        $paths = array_keys($sums);
        $n     = \count($paths);
        $i     = (int) ($state['i'] ?? 0);

        $state += ['checked' => 0, 'nModified' => 0, 'nMissing' => 0, 'modified' => [], 'missing' => []];

        $from = $i;

        for (; $i < $n; $i++) {
            // always at least one item per step, so a very small budget still makes progress
            if ($i > $from && microtime(true) > $until) {
                break;
            }

            $rel  = (string) $paths[$i];
            $file = $this->root . '/' . $rel;

            if (!self::isCompared($rel)) {
                continue;
            }

            if (!is_file($file)) {
                if (self::missingMatters($rel)) {
                    $state['nMissing']++;

                    if (\count($state['missing']) < self::LIST_MAX) {
                        $state['missing'][] = $rel;
                    }
                }

                continue;
            }

            $state['checked']++;

            if (strtolower((string) @md5_file($file)) !== $sums[$rel]) {
                $state['nModified']++;

                if (\count($state['modified']) < self::LIST_MAX) {
                    $state['modified'][] = $rel;
                }
            }
        }

        if ($i < $n) {
            $state['i'] = $i;
            $this->saveJson($this->stateFile(), $state);

            return $this->out('compare', 50 + (int) floor(50 * $i / max(1, $n)), false);
        }

        @unlink($this->stateFile());

        return $this->out('compare', 100, true, null, [
            'version'   => $this->version,
            'source'    => (string) ($list['source'] ?? ''),
            'time'      => time(),
            'total'     => $n,
            'checked'   => (int) $state['checked'],
            'nModified' => (int) $state['nModified'],
            'nMissing'  => (int) $state['nMissing'],
            'modified'  => $state['modified'],
            'missing'   => $state['missing'],
        ]);
    }

    // ------------------------------------------------------------------ network (defaults)

    /** @return  array<int,mixed> */
    private static function curlBase(): array
    {
        $opts = [
            CURLOPT_USERAGENT      => 'FG-System-Report',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if (\defined('CURLOPT_PROTOCOLS') && \defined('CURLPROTO_HTTPS')) {
            $opts[CURLOPT_PROTOCOLS]       = CURLPROTO_HTTPS;
            $opts[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        return $opts + FgsystemreportHelper::proxyCurlOptions();
    }

    /** GET and decode JSON (default for the GitHub release data). */
    public static function httpJson(string $url): ?array
    {
        if (!\function_exists('curl_init') || !str_starts_with($url, 'https://api.github.com/')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json'],
        ] + self::curlBase());

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $data = $body !== false && $code === 200 ? json_decode((string) $body, true) : null;

        return \is_array($data) ? $data : null;
    }

    /** Download to a file as the data arrives (the package is never held in memory). */
    public static function httpDownload(string $url, string $file): bool
    {
        if (!\function_exists('curl_init') || !preg_match('~^https://github\.com/joomla/joomla-cms/releases/download/~', $url)) {
            return false;
        }

        $out = @fopen($file, 'wb');

        if (!$out) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE             => $out,
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_MAXREDIRS        => 5,
            CURLOPT_TIMEOUT          => 240,
            CURLOPT_FAILONERROR      => true,
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => static fn ($c, $total, $now) => $now > self::MAX_BYTES ? 1 : 0,
        ] + self::curlBase());

        $ok = curl_exec($ch) !== false;
        curl_close($ch);
        fclose($out);

        return $ok && filesize($file) > 0;
    }
}

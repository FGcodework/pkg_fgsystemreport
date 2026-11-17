<?php
/**
 * @package     com_fgsystemreport
 * @copyright   (C) 2026 Fero
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Matches installed extensions against the Joomla Vulnerable Extensions List.
 *
 * The list identifies an extension only by a free-text title such as
 * "Akeeba LoginGuard,3.1.1 and all lower versions,Information Disclosure" or
 * "HikaShop, Versions from 4.4.1 to 4.7.2 are affected,  SQL Injection", and
 * the feed has no structured version fields. So the name is reduced to a
 * compact key and has to be equal, and the version range is read out of the
 * title where that is possible. Whatever cannot be decided is reported as
 * "check" (verify by hand) instead of being guessed, and short generic names
 * only ever give a "possible" match.
 *
 * Pure logic, no Joomla or network access: covered by tests/VelMatcherTest.php.
 *
 * @since  1.14.0
 */
final class VelMatcher
{
    /** Words that say nothing about which product it is. */
    private const NOISE = ['pro', 'free', 'lite', 'component', 'module', 'plugin', 'package', 'extension', 'extensions', 'for', 'joomla', 'the', 'com', 'mod', 'plg', 'pkg', 'lib', 'tpl', 'files', 'by', 'and'];

    /** Names shared by many unrelated products: a match on one of these is only "possible". */
    private const GENERIC = [
        'gallery', 'galleries', 'forms', 'form', 'contact', 'contacts', 'calendar', 'blog', 'shop', 'store', 'slider', 'slideshow', 'news', 'events', 'event',
        'poll', 'polls', 'login', 'search', 'banner', 'banners', 'tags', 'maps', 'map', 'video', 'videos', 'photo', 'photos', 'menu', 'jobs', 'booking',
        'guestbook', 'comments', 'attachments', 'downloads', 'newsletter', 'directory', 'library', 'framework', 'template', 'social', 'seo', 'cache',
        'backup', 'security', 'editor', 'media', 'users', 'appointment', 'publisher', 'football', 'recipe', 'player', 'chat', 'quiz', 'survey', 'coupon',
        'payment', 'paymentform', 'support', 'tickets', 'ticket', 'cart', 'reviews', 'rating', 'ratings', 'faq', 'sitemap', 'redirect', 'stats', 'counter',
    ];

    /** Plugin folders: "plg_system_foo" is the plugin whose element is "foo". */
    private const PLUGIN_GROUPS = [
        'system', 'content', 'user', 'editors', 'editors-xtd', 'search', 'finder', 'authentication', 'captcha', 'extension', 'fields', 'installer',
        'quickicon', 'sampledata', 'task', 'webservices', 'workflow', 'actionlog', 'behaviour', 'multifactorauth', 'privacy', 'schemaorg', 'api-authentication',
    ];

    /** Installed name key => the key(s) the list uses for the same product. */
    private const ALIASES = [
        'nrframework'     => ['tassosframework', 'novarain'],
        'novarain'        => ['tassosframework'],
        'tassos'          => ['tassosframework'],
    ];

    /** @var array<string,array<int,array>> key => listing id => listing */
    private array $index = [];

    /**
     * @param   array<int,array{id:int,title:string,live:bool,created:string}>  $items  the list
     */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $parsed = self::parseTitle((string) $item['title']);

            foreach ($parsed['keys'] as $key) {
                $this->index[$key][(int) $item['id']] = $item + ['parsed' => $parsed];
            }
        }
    }

    /** Reduce a product or element name to a compact comparison key. */
    public static function key(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');

        if (preg_match('/^plg_([a-z0-9-]+)_(.+)$/', $name, $m) && \in_array($m[1], self::PLUGIN_GROUPS, true)) {
            $name = $m[2];
        }

        $name  = preg_replace('/^(com|mod|plg|pkg|lib|tpl|files)_/', '', $name);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, static fn ($w) => !\in_array($w, self::NOISE, true));

        return implode('', $words);
    }

    /**
     * Read a listing title.
     *
     * @return  array{keys:string[],majors:int[],patch:string,patches:string[],from:string,to:string,last:string,versions:string[],all:bool}
     *          keys     name keys the listing can be known by
     *          patch    "pre 8.7.0" / "versions before 4.2": the first fixed version
     *          patches  all of them, when the title names one for each branch
     *          from/to  "from 4.4.1 to 4.7.2": an explicit affected range
     *          last     the single version named: that version and older are affected
     *          versions every dotted version found (more than one without a range = ambiguous)
     *          all      "all versions" / "all"
     *          majors   branches named without a number ("3x", "2.5.x")
     */
    public static function parseTitle(string $title): array
    {
        $text = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        $keys = [];

        // Element names quoted in the title: [com_contushdvideoshare], (mod_easyfileuploader), plg_system_helixultimate
        if (preg_match_all('/\b((?:com|mod|plg|pkg|tpl)_[a-z0-9_-]+)/i', $text, $m)) {
            foreach ($m[1] as $element) {
                $keys[] = self::key($element);
            }
        }

        $name = str_replace(['"', '“', '”'], '', $text);
        $name = preg_replace('/[\[(].*?[\])]/', ' ', $name);
        $name = explode(',', $name)[0];
        $name = preg_replace('/\s+by\s+.*$/i', '', $name);
        $name = preg_replace('/\s+id\s+\d+.*$/i', '', $name);
        $name = preg_replace('/\s+(?:v(?:ersions?)?\.?\s*)?(?:pre\s+|before\s+|up to\s+)?\d+(?:\.\d+)+.*$/i', '', $name);
        $name = preg_replace('/\s+(?:version\s+)?\d+x?\s*$/i', '', $name);
        $name = preg_replace('/\s+(?:abandonware|all versions|file permissions|improper .*)$/i', '', $name);

        foreach (preg_split('~\s*/\s*~', $name) as $part) {
            $keys[] = self::key($part);
        }

        // A name that is only a plain word ("Other", "XSS") is not a product
        $keys = array_values(array_unique(array_filter($keys, static fn ($k) => mb_strlen($k) >= 2)));

        // Versions are looked for outside brackets, where element names and similar live
        $body  = preg_replace('/\[[^\]]*\]|\([^)]*\)/', ' ', $text);
        $lower = mb_strtolower($body, 'UTF-8');
        $dotted = '(\d+(?:\.\d+)+)';

        $patch   = '';
        $patches = [];
        $from    = '';
        $to      = '';

        if (preg_match('/\b(?:from|between)\s+v?' . $dotted . '\s*(?:to|through|and|-|–)\s*v?' . $dotted . '/', $lower, $m)) {
            [$from, $to] = [$m[1], $m[2]];
        } elseif (preg_match_all('/\b(?:pre|before|prior to|earlier than|older than)\s+v?' . $dotted . '/', $lower, $m)) {
            // "pre 2.1.2 and pre 3.1.2": one fixed version for each branch
            $patches = array_values(array_unique($m[1]));
            $patch   = $patches[0];
        }

        preg_match_all('/(?<![\d.])' . $dotted . '(?![\d])/', $lower, $v);
        $versions = array_values(array_unique($v[1]));
        $last     = ($patch === '' && $from === '' && \count($versions) === 1) ? $versions[0] : '';
        $all      = $last === '' && $patch === '' && $from === '' && !$versions
            && (bool) preg_match('/\ball\s+versions?\b|,\s*all\s*,|\ball\b\s*,/', $lower);

        // "Phoca Gallery 3x", "2.4.x - 2.5.x": only the branch is named
        preg_match_all('/(?<![\d.])(\d+)(?:\.\d+)*\.?x\b/', $lower, $x);

        return [
            'keys'     => $keys,
            'majors'   => array_values(array_unique(array_map('intval', $x[1]))),
            'patch'    => $patch,
            'patches'  => $patches,
            'from'     => $from,
            'to'       => $to,
            'last'     => $last,
            'versions' => $versions,
            'all'      => $all,
        ];
    }

    /** First dotted number of a version string; "3.39f" gives 3.39, "v2.0.18-rc1" gives 2.0.18. */
    public static function version(string $text): string
    {
        return preg_match('/\d+(?:\.\d+)*/', $text, $m) ? $m[0] : '';
    }

    /**
     * Find the listings that apply to one installed extension.
     *
     * @param   string[]  $names    names to look up: element first, then the display name
     * @param   string    $version  installed version
     *
     * @return  list<array{id:int,title:string,state:string,confidence:string,live:bool,fix:string,created:string,matched:string}>
     *          state: affected | check
     */
    public function match(array $names, string $version): array
    {
        $found     = [];
        $installed = self::version($version);

        foreach ($names as $name) {
            $key  = self::key($name);
            $keys = array_merge([$key], self::ALIASES[$key] ?? []);

            foreach ($keys as $k) {
                foreach ($this->index[$k] ?? [] as $id => $item) {
                    if (isset($found[$id])) {
                        continue;
                    }

                    $verdict = self::assess($item['parsed'], (bool) $item['live'], $installed);

                    if ($verdict === null) {
                        continue;
                    }

                    $found[$id] = $verdict + [
                        'id'         => (int) $id,
                        'title'      => trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? ''),
                        'live'       => (bool) $item['live'],
                        'created'    => (string) ($item['created'] ?? ''),
                        'matched'    => $name,
                        'confidence' => (\in_array($k, self::GENERIC, true) || $installed === '') ? 'possible' : 'likely',
                    ];
                }
            }
        }

        $rank = ['affected' => 0, 'check' => 1];
        uasort($found, static fn ($a, $b) => $rank[$a['state']] <=> $rank[$b['state']] ?: strcmp($b['created'], $a['created']));

        return array_values($found);
    }

    /**
     * Does the listing apply to this version?
     *
     * @return  array{state:string,fix:string}|null  null = it does not apply (or is of no use)
     */
    public static function assess(array $parsed, bool $live, string $installed): ?array
    {
        $hasRange = $parsed['patch'] !== '' || $parsed['from'] !== '' || $parsed['last'] !== '' || $parsed['all'];

        if (!$hasRange && !$parsed['versions']) {
            // Only a branch ("3x") named: it concerns that major version alone
            if (($parsed['majors'] ?? []) && $installed !== '' && !\in_array((int) $installed, $parsed['majors'], true)) {
                return null;
            }

            // No version information at all: still open -> worth a look, resolved -> nothing to act on
            return $live ? ['state' => 'check', 'fix' => ''] : null;
        }

        if ($installed === '') {
            return ['state' => 'check', 'fix' => $parsed['patch']];
        }

        if ($parsed['patch'] !== '') {
            // One fixed version for each branch: judge the installed version against the fix of its own major version
            $fixes = $parsed['patches'] ?: [$parsed['patch']];
            $major = (int) $installed;
            $own   = array_values(array_filter($fixes, static fn ($f) => (int) $f === $major));
            $fix   = $own ? max($own) : min($fixes);

            return version_compare($installed, $fix, '<') ? ['state' => 'affected', 'fix' => $fix] : null;
        }

        if ($parsed['from'] !== '') {
            return version_compare($installed, $parsed['from'], '>=') && version_compare($installed, $parsed['to'], '<=')
                ? ['state' => 'affected', 'fix' => $live ? '' : '>' . $parsed['to']]
                : null;
        }

        if ($parsed['last'] !== '') {
            return version_compare($installed, $parsed['last'], '<=')
                ? ['state' => 'affected', 'fix' => $live ? '' : '>' . $parsed['last']]
                : null;
        }

        if ($parsed['all']) {
            return $live ? ['state' => 'affected', 'fix' => ''] : null;
        }

        // Several versions without a stated range (for example separate branches): only a newer one is certainly clear
        $max = '0';

        foreach ($parsed['versions'] as $v) {
            if (version_compare($v, $max, '>')) {
                $max = $v;
            }
        }

        return version_compare($installed, $max, '>') ? null : ['state' => 'check', 'fix' => ''];
    }
}

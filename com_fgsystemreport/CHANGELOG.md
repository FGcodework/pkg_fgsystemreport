# Changelog

FG System Report (com_fgsystemreport), maintained by Fero. Originally
forked from Abdulgani's "JC System Report" (com_jc_systemreport) starting
from the upstream 1.0.12 base, extensively rewritten since, and rebranded
to the FG line in 1.2.0 (element, namespace, classes, and language
constants all changed then - see that entry for the full list). Entries
before 1.2.0 refer to the project under its old name, kept as an accurate
historical record rather than rewritten. This file is kept from 1.0.35
onward; earlier versions are summarized below from memory of the work
done.

**Companion package: plg_task_fgsystemreport** (was
`plg_task_jcsystemreport` before 1.2.0 - added alongside 1.0.74, see
below - a separate installable ZIP, not bundled into this component's own
package). A Joomla Task Scheduler (`com_scheduler`) plugin that runs the
Security Check on a schedule and emails a notification only when
something got worse since the last run. Requires this component to
already be installed on the same site (it reuses
`FgsystemreportHelper::getSecurityChecks()`/`calculateSecurityScore()`
directly rather than duplicating any logic). Install it, then create a
Scheduled Task of type "FG System Report: Security Check Notification"
under System > Scheduled Tasks, set a schedule (e.g. daily), and optionally
enter recipient emails (defaults to all active Super Users if left blank).

**Bundled TCPDF version: 6.10.1** (upgraded from 6.6.5 in 1.0.55 - see
below. Check this line when auditing for known TCPDF CVEs; update the
`administrator/assets/library/TCPDF/library/VERSION` file's value here
too whenever it's bumped again, so this note doesn't go stale.)

## [1.13.4]

### Added
- New Security Check "Extension records without files": lists non-core extensions that are registered in Joomla but whose folder is missing on disk (orphaned records).

### Fixed
- "Extensions without an active update site" no longer lists orphaned records (extensions whose files are gone); they are reported by the new check instead.

## [1.13.3]

### Fixed
- "Extensions without an active update site" counted every extension, including Joomla core extensions and the individual members of packages (which never have their own update site), which inflated the number. It now counts only non-core extensions that are not package members, and the note lists the first 12 of them by name.

## [1.13.2]

### Fixed
- Header score pill and history disagreed with the Security Check page after accepting or revoking a risk (the pill shows the latest recorded run). The Security Check page now records a run whenever its score differs from the latest recorded one.

## [1.13.1]

### Changed
- Slovak (sk-SK): the 36 missing language keys are translated, so the interface is now fully Slovak (en-GB, sk-SK and de-DE all have 573 keys).

## [1.13.0]

### Added
- Accepted risk: on the Security Check page a warning or critical finding can be accepted with an optional note (and revoked later). An accepted finding is treated as info, so it no longer lowers the score or counts as a warning on the dashboard, in the history, the report, the CLI command and the scheduled task. Super-user (core.admin) only; stored in the new table `#__fgsystemreport_accepted` (created on first use, dropped on uninstall).
- Every Security Check result now carries a stable `key` (the language constant of the check).

## [1.12.6]

### Fixed
- Change timeline: plugin/extension enable and disable still was not recorded on Joomla 5.4. System plugin 1.0.3 additionally recognises the toggle request itself (Plugins and Extensions managers, publish/unpublish tasks), independent of the state-change event.

## [1.12.5]

### Fixed
- Change timeline: enabling/disabling a plugin from the Plugins manager was not recorded (it fires onContentChangeState, not onExtensionChangeState). System plugin 1.0.2 listens to both.

### Changed
- Timeline badges are colour-coded by type (install blue, update/enable green, uninstall red, disable grey, configuration amber).

## [1.12.4]

### Fixed
- Change timeline recorded nothing: `insertObject()` takes its object by reference and was given a cast expression, which throws on Joomla 5/6. Fixed there and in the same call used by the disk-growth alerts (database table row and Joomla message).

## [1.12.3]

### Added
- History: when the change timeline is empty it now shows diagnostics (system plugin installed/enabled, events table available, last recording error).
- System plugin 1.0.1 writes handler errors to `administrator/logs/fgsystemreport_events.log` instead of swallowing them silently.

## [1.12.2]

### Fixed
- Extensions: the "Official Joomla Project" badge was unreadable (dark text on a solid blue background); it now uses the soft accent badge style.

## [1.12.1]

### Changed
- Table text is larger and heavier (15px, medium weight, bold first column) for better readability.

## [1.12.0]

### Changed
- New admin UI in the visual style of fgcodework.github.io: app header with
  logo, version and live security-score pill, pill-style tab navigation across
  all pages, panel cards, rounded tables, pill badges, light and dark mode
  (follows the Atum colour scheme). All styling lives in one scoped
  stylesheet (`assets/css/fg-ui.css`) and affects only this component.
- Dashboard: quick-link tiles with status accent, restyled statistics boxes
  and PDF section; copy-to-clipboard behaviour unchanged.
- Security Check: score ring with badge counts and actions at the top.
- Printable report keeps its clean print layout (no header or tabs).

## [1.11.1]

### Changed
- The Dashboard's PDF block is now called **Site inventory (PDF)** (it lists counts, server/Joomla information, template overrides and extensions, and can be e-mailed), and links to the new Report page; the Report page links back to it. The two are complementary: the inventory is a real PDF file, the Report is the health/security print view.

### Added
- Report: optional **Installed extensions** section (name, type, state, installed and available version, updates highlighted; third-party only unless "incl. Joomla core extensions" is ticked).

## [1.11.0]

### Added
- **Report** page (new submenu item, `core.admin` only): a one-page printable site health report - security score, findings that need attention (with what to do), passed checks, 30-day score trend, recent changes, errors by source, and the largest folders and database tables. Optional "Prepared for" name for client reports, each section can be switched off (e.g. hide the technical notes), and an optional fresh Security Check. "Open print view" opens a clean page (no Joomla chrome) with a Print / Save as PDF button; print CSS hides everything else. It reads stored data only, so opening it never starts a disk scan.

## [1.10.0]

### Added
- **Errors page** (new submenu item, `core.admin` only): reads the tail (last 1 MB) of the Joomla log folder error logs and PHP `error_log` files, groups identical errors by cause and attributes each group to the extension, template or Joomla core folder whose file raised it ("who causes the errors"). Period 24 h / 7 d / 30 d, severity filter, and a "spike" flag when an error is 3x more frequent in the last day than the daily average of the six days before. Read-only, no per-request cost.
- **Change timeline** (new plugin `plg_system_fgsystemreport` 1.0.0): records extension installs, updates and removals, enable/disable, and changes of key Global Configuration settings (debug, error reporting, caching, offline, mailer, force SSL, SEF, gzip, session handler, ...). Shown on the Security History page as dashed markers in the score chart, a "Changes" count per run (tooltip lists them) and a "Change timeline" table. Does nothing on ordinary page requests.
- Security Check, 7 new checks (now 32): **Mail DNS** (SPF / DMARC / MX of the site domain), **Scheduled tasks** overdue by more than a day (cron not running), **OPcache** off or full, **HSTS** (informational), **PHP files in /images**, **Leftover files in the web root** (installation folder, copies of configuration.php, backups/dumps, adminer/phpinfo-style scripts) and **PHP fatal errors in the last 24 h**.
- New table `#__fgsystemreport_event` (created on first use; removed on uninstall).

### Changed
- The package now contains five extensions; the installer enables the new system plugin on a fresh install and on the first update that brings it.

## [1.9.1]

### Fixed
- Console and quick icon plugins (both 1.0.1): the service provider called `setDatabase()`, which these plugins do not have, so the administrator Home page crashed with "Call to undefined method ... setDatabase()" as soon as the quick icon plugin was enabled.

## [1.9.0]

### Added
- **Console command** `fgsystemreport:check` (new plugin `plg_console_fgsystemreport` 1.0.0): runs the full Security Check from the CLI / cron (`php cli/joomla.php fgsystemreport:check`). Options: `--format=text|json`, `--fail-on=danger|warning|never`, `--url=https://example.com` (site address for the HTTP-based checks when `live_site` is not set), `--no-record`. Exit codes: 0 OK, 1 warnings (with `--fail-on=warning`), 2 critical, 3 could not run. Runs are recorded in the Security History with source "CLI".
- **Quick icon on the administrator Home page** (new plugin `plg_quickicon_fgsystemreport` 1.0.0): shows the latest recorded Security Check score, coloured by severity (and flagged when older than 7 days), linking to the Security page. It only reads the latest history row, never runs a live check, and is shown only to users with `core.manage` on the component.
- Security History source "CLI" (EN/SK/DE).

### Changed
- The package now contains four extensions (component, task, console and quick icon plugins). The installer script enables the new plugins on a fresh install and on the first update that brings them; later updates never re-enable a plugin you disabled.

## [1.8.0]

### Added
- Database growth over time. Every Database diagnostics run stores a snapshot of
  all table sizes (new table `#__fgsystemreport_dbscan`, created lazily, dropped
  on uninstall, pruned by the history retention setting, max 60 snapshots). The
  Database page shows a "Change" column for the largest tables and a
  "Fastest growing tables" box compared with the previous snapshot.
- Growth alerts for database tables, reusing the folder growth alert channels
  (dismissible banner, private message to Super Users, optional e-mail for
  scheduled runs). New Options: growth over MB / percent for tables.
- Log viewer (Logs menu, requires core.admin): read-only tail of the log files
  in the configured log folder - file picker, text filter, 100/300/1000 lines,
  errors in red and warnings in amber. Only plain files with .log/.php/.txt/.json
  extension directly inside the log folder can be opened (no symlinks, no path
  traversal); only the last 256 kB of a file are read.
- Task plugin 1.4.0: new scheduled task "FG System Report: Database Growth
  Check" (fresh snapshot + alert evaluation; recommended daily).

## [1.7.1]

### Changed
- Database page: the MyISAM and collation checks now tell Joomla core tables
  apart from extension tables. A warning is raised only when a core table is
  on MyISAM or not on utf8mb4; the same findings in extension tables (which
  create these tables themselves) are shown as information only, with
  explanatory text. Fixes needless warnings on perfectly healthy sites.

## [1.7.0]

### Added
- New "Database" page: read-only diagnostics for MySQL / MariaDB - server
  version and default character set, key server variables (max_allowed_packet,
  max_connections, wait_timeout, InnoDB settings), presence of the Joomla core
  tables, storage engines (flags MyISAM), collation consistency (flags mixed
  or non-utf8mb4), total size, reclaimable space and the 10 largest tables.
  Cached for 5 minutes; "Run again" bypasses the cache.
- Security Check: "Folder permissions" - flags world-writable folders
  (root, administrator, plugins, cache, tmp, logs, images) as critical and
  missing/read-only folders Joomla must write to as a warning. Plain
  "writable" is not flagged, to avoid false positives on shared hosting.
- Security Check: "Orphaned asset rows" - counts rows in the assets table whose
  parent no longer exists (detection only, nothing is modified).
- Idea source: feature survey of DevArt Utils (GPL-3.0). No code was copied;
  everything here is an independent implementation.

## [1.6.0]

### Added
- Folder growth alerts. After each Disk Usage scan the new snapshot is
  compared with the previous one; folders that grew by more than a
  configurable amount (MB) or percentage (percent rule ignores folders that
  grew by less than 10 MB) raise an alert. Only folders present in both
  snapshots are considered. New Options tab "Folder growth alerts".
- Alert channels, none of which needs PHP mail(): a dismissible warning
  banner on the dashboard and Disk Usage pages, a private message to all
  Super Users (Users - Private Messages), and optionally an e-mail through
  the site mailer (Global Configuration, so SMTP works). E-mail is sent only
  for scheduled scans.
- "Send test e-mail" button on the Disk Usage page; shows the real error
  text when sending fails.
- New table `#__fgsystemreport_alert` (created lazily, dropped on uninstall).
- Task plugin 1.3.0: new scheduled task "FG System Report: Disk Growth
  Check" (fresh Disk Usage scan + alert evaluation; recommended daily).

## [1.5.1]

### Changed
- Long-running actions (Check now on Extensions, Rescan on Disk Usage,
  Re-check on Security) now show a modal "Working..." overlay with a
  spinner and a per-action message until the page reloads. Implemented
  once in the new `BusyHelper`; any link can opt in with class `fg-busy`.

## [1.5.0]

### Added
- Disk Usage: comparison with the previous scan. New "Change" column per
  folder (red = growth, green = shrink, "new in list" badge), total change
  in the footer and a note showing which scan it is compared against.
  Scans are stored in the new table `#__fgsystemreport_diskscan`
  (created lazily; dropped on uninstall; pruned by the history retention
  setting, max 60 snapshots per exclude-set).
- Installed Extensions: "Update all (N)" button when updates are available.
  Opens Joomla's update list with filters cleared (select all, then Update).

## [1.4.10]

### Changed
- Release workflow attaches only the package (`pkg_fgsystemreport_vX.X.X.zip`)
  to GitHub releases; the standalone component/plugin ZIPs no longer appear
  there (the component has no update server of its own, only the package
  does). `build.sh` still builds them into `dist/`.
- README: removed the note about standalone ZIPs, `bash build.sh` in the
  release steps.
- Maintenance release; also used to verify the update path 1.4.9 -> 1.4.10.

## [1.4.9]

### Changed
- The package's update server URL now points to the `master` branch
  (`.../pkg_fgsystemreport/master/updates.xml`) - the repository's default
  branch is `master`, not `main`. 1.4.7 and 1.4.8 pointed to `main`;
  install 1.4.9.

## [1.4.8]

### Changed
- GitHub repository is named `pkg_fgsystemreport` (FGcodework/pkg_fgsystemreport);
  the package's update server URL and all links point there. 1.4.7 pointed
  to a repository name that is not used - install 1.4.8.

## [1.4.7]

### Changed
- Updates are now delivered through the `pkg_fgsystemreport` package (update
  server on GitHub, `https://raw.githubusercontent.com/FGcodework/pkg_fgsystemreport/master/updates.xml`).
  The component no longer declares its own update server (the old
  placeholder URL is gone) and `updates.xml.example` was removed.
- Project moved to a GitHub repository (FGcodework/pkg_fgsystemreport).

## [1.4.6]

### Added
- German localisation (de-DE): the full component UI, PDF report texts and
  the .sys.ini menu labels (328 strings, same keys as en-GB; placeholders
  verified identical). The bundled PDF font already covers ä ö ü ß.
- Companion plugin 1.2.1 gets de-DE too (task title, form, notification
  e-mail).

## [1.4.5]

### Fixed
- Slovak localisation typos: "úspéšná/úspéšné/neúspéšné" -> "úspešná/úspešné/neúspešné"
  (e.g. "Posledná úspešná záloha") and "môže íšť" -> "môže ísť".

## [1.4.4]

### Fixed
- PDF reports: Slovak diacritics (č, ľ, ň, ď, ť and others) are now rendered
  correctly instead of "?". The PDF now uses a small subset of DejaVu Sans
  (Latin + Latin Extended-A, plus dashes, quotes, bullet, ellipsis and the
  euro sign; regular, bold and italic), added as TCPDF font `dejavusanssub`.
  Measured package growth: about 49 KB (the full DejaVu would add ~1.5 MB).
  Helvetica remains bundled (TCPDF default).
- PDF title and file name still said "JC System" (leftover from the rebrand):
  now "FG System Report - ..." and `fg_system_report.pdf`.

## [1.4.3]

### Changed
- Installed Extensions quick filter: restyled as separate pill buttons with
  a "Quick filter:" label and light counts in brackets, with proper
  spacing, instead of a cramped button group with heavy dark badges.

## [1.4.2]

### Fixed
- 1.4.1 shipped with truncated `com_fgsystemreport.ini` language files
  (en-GB and sk-SK contained only the 5 new quick-filter strings), so every
  other text showed as a raw language constant. The full files are restored
  and every constant used in the code was re-checked against both languages.
  Do not use 1.4.1.

## [1.4.1]

### Added
- Installed Extensions: quick filter buttons - All / With update /
  Third-party (not authored by "Joomla! Project") / Disabled - each with
  an item count. The choice is remembered for the session and also applies
  to the CSV export. Combines with the existing core/official/group-by-type
  toggles.

## [1.4.0]

### Added
- **Security History** (new submenu item): every Security Check run is
  recorded (score, counts of critical/warning/ok, per-check status, Joomla
  and PHP version, source). The page shows a score trend chart (plain SVG,
  no library; 30/90/365 days), a list of runs with score change, and a
  comparison of the selected run with the previous one (worse / better /
  new check / check gone). CSV export and "Clear history".
- Runs are recorded by the scheduled task (companion plugin 1.2.0) and by
  an explicit "Re-check" on the Security Check page (plus one baseline
  when the history is empty) - not on every page view. An identical result
  within one hour is not stored twice.
- Option "Security history retention (days)", default 365; old rows are
  purged by the scheduled task.
- Link to the history from the Security Check page.
- The table `#__fgsystemreport_history` is created on first use (so it
  also appears after an in-place upgrade) and dropped on uninstall.

### Notes
- Checks are matched between runs by their translated label. If runs were
  recorded in different languages, the comparison falls back to matching
  by position.

## [1.3.4]

### Fixed
- Installed Extensions: the "Go to update" link searched com_installer by
  the raw language key (e.g. `MEDIABOXCK_NAME`) and found nothing. It now
  filters by `filter[extension_id]`, which is independent of translations.

### Changed
- Installer ZIP naming is now `com_fgsystemreport_vX.X.X.zip` (the `_J6_`
  infix was dropped). `updates.xml.example` updated accordingly.

## [1.3.3] - Clock skew check: recognise a likely timezone-misread pattern
- A site reported "Critical" with a suspiciously round 7200s (exactly 2
  hours) skew and the DB's `time_zone` set to `SYSTEM`. That specific
  combination is much more likely to be MySQL/MariaDB misinterpreting
  `UTC_TIMESTAMP()` because its timezone tables were never loaded
  (`mysql_tzinfo_to_sql` never run - common on shared hosting) than an
  actual desynced clock - the 2-hour figure lines up exactly with a
  CEST/UTC offset, not a random drift.
- Added a heuristic: when the skew lands within 5 seconds of a whole
  number of hours (measuring distance to the *nearest* multiple of
  3600s, so both just-above and just-below count - e.g. 3595s is as
  suspicious as 3605s) *and* the DB's `time_zone` is literally
  `SYSTEM`, the check now reports "Warning" with an explanation and a
  concrete way to verify (`SELECT NOW(), UTC_TIMESTAMP();` - if they
  match, that confirms the cause) instead of a flat "Critical". A
  genuinely small or zero skew is never affected (requires > 60s
  first), and a round-hour skew with a real named DB timezone (not
  `SYSTEM`) still reports normally, since that combination doesn't fit
  this specific misread pattern.
- Verified the distance-to-nearest-hour logic against nine cases
  (exact hour, +5s, -5s tesne pod, +20s outside tolerance, round-hour
  with a non-SYSTEM timezone, a random non-round skew, and a
  near-zero skew that must never trigger this) - all matched
  expectations.

## [1.3.2] - Disk Usage table font size matched to Security Check
- The Disk Usage table looked noticeably smaller than the rest of the
  component - an explicit `font-size:12px` on the path column and
  Bootstrap's `.small` class (~87% size) on the size/total columns,
  neither of which the Security Check table has. Removed both so the
  table now inherits the same default size as Security Check's,
  consistent across pages.

## [1.3.1] - Fixed "View not found" on the Disk Usage page
- `view=diskusage` failed with "View not found [name, type, prefix]:
  diskusage, html, Administrator". Cause: Joomla's MVCFactory resolves
  a view name to a class/folder name via `ucfirst()` only - not full
  StudlyCase conversion of multi-word names - so `diskusage` resolves
  to `Diskusage` (capital D only), not `DiskUsage` (capital D *and* U)
  as the folder was actually named. Every other view in this component
  is a single dictionary word (Extensions, Security, Icons), so this
  was the first place a multi-word view name exposed the assumption.
  Renamed `View/DiskUsage/` -> `View/Diskusage/` and updated its
  namespace declaration to match; `tmpl/diskusage/` was already
  correctly all-lowercase (the template folder isn't resolved through
  this same ucfirst-only convention) and needed no change. Confirmed
  no other view in this component uses a multi-word name, so this
  class of bug has nowhere else to hide here.

## [1.3.0] - New page: Disk Usage (adapted from mod_jm_treesize)
- Adapted Fero's own `mod_jm_treesize` module (Maarten Blokdijk,
  jmodules.com - a module Fero has maintained and improved through many
  iterations) into a proper page of this component (submenu entry,
  `view=diskusage`) rather than keeping it as a separate standalone
  module. Shows the top N largest folders under the site root as a
  ranked table with a running total, the same kind of "what's eating
  disk space" finding this component already surfaces for the database
  (the bloated-tables check from 1.0.75) - forgotten backups, oversized
  logs, and runaway caches are exactly the kind of thing an audit tool
  should flag.
- Ported the scanning algorithm faithfully - single-pass traversal
  (folder structure and file sizes collected together), a bounded
  min-heap (`DiskScan\TopHeap`) that tracks only the current top N by
  size instead of sorting every scanned folder, an exclude filter
  (`DiskScan\ExcludeFilter`) that skips excluded folders (cache, tmp,
  logs, .git, node_modules by default) entirely rather than descending
  into them and filtering after, symlinked folders skipped outright to
  prevent infinite recursion on a symlink loop, and permission errors
  during traversal caught so one unreadable folder doesn't abort the
  whole scan. Verified against a real filesystem: excluded folders
  correctly stay out of both the top-N ranking and the total, sizes
  accumulate correctly into ancestor folders, and `formatFileSize()`
  matches expected output across B/KB/MB/GB/TB.
- Simplified during the port rather than copied 1:1: this component only
  targets Joomla 6, so the module's Joomla-3.x-compatible fallback
  wrappers (legacy `JText`/`JFactory`/`JModuleHelper`/`JSession`
  version-checking) were dropped in favour of calling `Text::`/
  `Factory::` directly, as the rest of this component already does.
- Caching now goes through the same `CacheControllerFactoryInterface`
  pattern already used for Security Check (1.0.62), instead of the
  module's own hand-rolled JSON-file cache - but in its own cache group
  (`com_fgsystemreport_diskusage`) so a "Rescan now" here never also
  discards the (expensive, network-calling) Security Check cache, and
  vice versa. Careful to avoid reintroducing a timezone bug the
  original module spent two versions fixing (1.9.10/1.9.11): the "last
  scanned" timestamp is formatted via `Factory::getDate()` with the
  site's configured Server Time Zone explicitly applied, not PHP's own
  default timezone or a plain `date()` call.
- "How many to show" and "Exclude folders" are on-page controls
  (number/text inputs + Apply), persisted via `getUserStateFromRequest()`
  - the same session-backed filter pattern already used for the
  Extensions page (1.0.76) - rather than a separate Options-page config
  field, so they can be adjusted per-visit without leaving the page.
- No separate standalone module going forward - this page replaces it
  rather than duplicating it alongside.

## [1.2.0] - Rebranded to FG System Report (com_fgsystemreport)
- Full rename to Fero's FG line of extensions, as planned. Every
  layer of the project changed together in one pass:
  - Element: `com_jc_systemreport` -> `com_fgsystemreport`.
  - Namespace: `Jcsystemreport\Component\Jc_systemreport` ->
    `Fg\Component\Fgsystemreport` (a stable `Fg` vendor segment,
    shared ground for future FG components rather than repeating the
    component name twice).
  - Every class renamed to match: `FgsystemreportHelper`,
    `FgsystemreportController`, `FgsystemreportModel`,
    `FgsystemreportComponent`, `FgSystemPDF` (was `JCSystemPDF`).
  - Language constant prefix: `COM_JC_SYSTEMREPORT_*` ->
    `COM_FGSYSTEMREPORT_*`, across both `.ini` and `.sys.ini`, both
    languages - including the bare `COM_JC_SYSTEMREPORT` component-name
    constant (used for the top-level admin menu label) that an
    over-strict first-pass regex missed because it required a
    trailing underscore; caught on the follow-up exhaustive
    case-insensitive sweep of the whole tree.
  - Every file and folder renamed to match (manifest, language files,
    CSS asset, every PHP class file, the `View/Fgsystemreport/` and
    `tmpl/fgsystemreport/` folders for the Dashboard view).
  - Removed rather than renamed: the `JC_SYSTEMREPORT` HTML service
    helper (`Service/Html/JC_SYSTEMREPORT.php`) - its one method
    (`toggle()`) turned out to be registered but never actually
    called anywhere, a leftover from the generic list view removed
    back in 1.0.56 that this rename pass happened to surface.
  - PDF metadata that literally named the original author's business
    ("JC Joomla Consultant" as the PDF Creator/Author,
    "JC Quick Insight Report" as its title) updated to "FG System
    Report" / "FG Quick Insight Report".
  - `LICENSE.txt` and the manifest `<description>` reworded to
    describe this accurately as an FG-branded evolution of the
    original "JC System Report" by Abdulgani, rather than as an
    ongoing "fork" - historical copyright attribution kept intact per
    GPL practice, `<author>` now Fero as current maintainer.
  - The companion `plg_task_fgsystemreport` task-scheduler plugin
    (was `plg_task_jcsystemreport`) renamed the same way, including
    its direct full-namespace reference to the component's helper
    class.
- **Caught mid-rename**: a bulk regex pass missed every place the
  namespace was written as a double-escaped string inside PHP
  single-quoted string literals (`'\\Jcsystemreport\\Component\\...'`
  - two literal backslashes in the raw file, since PHP needs `\\` to
  produce one backslash at runtime) - the regex only matched the
  single-backslash form used in real `namespace`/`use` statements.
  This silently produced a broken namespace
  (`\Fgsystemreport\Component\Fgsystemreport` instead of
  `\Fg\Component\Fgsystemreport`) in `services/provider.php`'s DI
  registration calls, which would have broken `MVCFactory`/
  `RouterFactory`/etc. resolution at runtime. Found by manually
  reviewing every renamed file rather than trusting the regex output,
  fixed directly, and the same double-backslash pattern was checked
  for and fixed in the task plugin's `provider.php` and its
  `$helperClass` string on the first pass this time.
- Verified PSR-4 compliance (every `namespace`/`class` declaration
  checked against its actual file path) and did an exhaustive
  case-insensitive search of the whole tree for any remaining
  `jc_systemreport`/`jcsystemreport` fragment after the rename -
  clean except for `CHANGELOG.md`'s historical entries, which are
  left as an accurate record of what was actually named at the time.
- **Upgrade note**: this is a new element, not an in-place rename Joomla
  can migrate automatically - existing sites need to uninstall
  `com_jc_systemreport` (and `plg_task_jcsystemreport` if installed)
  and install `com_fgsystemreport` (and `plg_task_fgsystemreport`)
  fresh. The task plugin's saved comparison baseline resets on
  reinstall - harmless, it just re-establishes itself silently on the
  next scheduled run instead of sending a notification.

## [1.1.1] - Fixed untranslated submenu label, enlarged icon grid
- The "Icon Classes" submenu entry showed the raw, untranslated
  `COM_JC_SYSTEMREPORT_TITLE_ICONS` string instead of its label. The
  admin sidebar submenu resolves its labels from the component's
  `.sys.ini` file (loaded very early, before the main `.ini`), and
  1.1.0 only added this constant to the regular `.ini` files, not the
  `.sys.ini` ones - added it there too, for both languages. Also
  double-checked every other `TITLE_*` constant is present in both
  files, in each language - all were already consistent (this was the
  only gap).
- Enlarged the icon grid on the Icon Classes page: icon glyphs
  20px -> 32px, cell width 110px -> 140px, more padding, and slightly
  more space between cells - easier to actually see the glyph and read
  its label at a glance.

## [1.1.0] - New page: Icon Classes reference
- Adapted LADkit's "Icon Classes" tool into a proper page of its own
  (submenu entry, `view=icons`), rather than a separate standalone
  extension - a per-request follow-up to the earlier LADkit review.
  Scans the currently active admin template's CSS (FontAwesome, the
  template's own `template.css`, and Joomla's bundled icomoon set) for
  `.icon-xxx` classes and shows each one with its rendered glyph, name,
  and source file - a quick reference for which icon classes actually
  exist in this admin template, useful when building or maintaining a
  view or template override without digging through CSS source by
  hand.
- New `Jc_systemreportHelper::getIconClasses()`: unlike LADkit's
  line-by-line parsing (which needed a separate special-cased code
  path for minified CSS), this uses a single regex with a lookahead
  for the next delimiter (`:`, `,`, `{`, or whitespace), so it handles
  both normal and minified CSS the same way. Verified against sample
  CSS covering both cases, multi-selector comma lists, and the
  `link-hover` false-positive LADkit's own code explicitly filtered
  out.
- Includes a client-side filter box (same `type="search"` +
  `autocomplete="off"` treatment as the Extensions page filter, so it
  doesn't fall into the same browser-autofill trap fixed in 1.0.72)
  and is gated behind the same `core.manage` ACL check as the rest of
  this component's admin pages.
- Bumped to 1.1.0 (not a 1.0.x patch) since this adds a new page/
  feature rather than fixing or refining an existing one.

## [1.0.81] - Fixed narrow Server/Joomla Information tables in PDF
- The two-column tables in "Server Information" and "Joomla
  Information" had column widths of 30% and 25% - only 55% of the
  table's own declared 100% width, leaving the rest blank and making
  both tables look narrow next to the full-width tables elsewhere in
  the report (Summary, Extensions, Security Check). Changed to 35%/65%
  (label/value), which actually sums to 100%, and switched the value
  column from centered to left-aligned text - centering reads poorly
  for longer values like a PHP Locale string or an email address.

## [1.0.80] - Added DATE_FORMAT_CALENDAR_DATE to "Joomla Information" after all
- Explicit request to include it despite the earlier call to leave it
  out - added as its own row in the same "Joomla Information" section
  as the other configuration-awareness items from 1.0.79. Uses the raw
  Joomla core language key as the label (matching how LADkit itself
  displayed it), since it's a core Joomla string, not one of this
  component's own.

## [1.0.79] - "Joomla Information" section: configuration-awareness items
- Following up on the LADkit review: not everything worth surfacing
  is a security *risk* - some of it is just "am I configured the way
  I intend to be", which belongs somewhere separate from Security
  Check rather than mixed into its ok/warning/danger findings. The PDF
  report's existing "Joomla Information" section (already distinct
  from Security Check) is exactly that separate home, so it gained
  five new facts: PHP Locale, PHP Timezone, Session (lifetime +
  handler, e.g. "60 minutes (database)"), Joomla Caching (on/off), and
  Mail From (address + from-name as actually configured, so a wrong
  sender name/address is obvious at a glance).
- Deliberately left out `DATE_FORMAT_CALENDAR_DATE` even under this
  broader "admin awareness" framing - it's a language-pack string for
  a JS date-picker's format, tells an admin nothing about their
  configuration, and its underlying `strftime()`-style formatting is
  itself deprecated PHP.

## [1.0.78] - Two more checks from the LADkit "System Info" tab
- Spotted while comparing our Security Check against LADkit's own
  "Main" info tab: two things worth adding, everything else there was
  either already covered or not worth exposing (JPATH_* paths,
  Uri::root(), mail-from name/address are either duplicated elsewhere
  or not actionable for an audit).
- **MySQL/MariaDB strict mode (`sql_mode`)**: flags when neither
  `STRICT_TRANS_TABLES` nor `STRICT_ALL_TABLES` is active - without
  it, the database silently truncates or coerces invalid data instead
  of raising an error, a subtle and hard-to-trace source of data
  integrity bugs. Verified the detection logic against sample
  `sql_mode` strings, including the exact one from a real site's
  screenshot.
- **"System - Cache" plugin status**: flagged as a warning when
  enabled, not because it's inherently wrong, but because full-page
  caching of this kind has a real history of leaking one user's page
  output to another on pages with per-user content when misconfigured
  - worth a manual check, not a guaranteed problem.

## [1.0.77] - Three checks inspired by reviewing com_ladkit (LADkit)
- Reviewed a third-party Joomla developer toolkit (LADkit by Martin
  Brampton) for ideas worth adapting. Most of it didn't fit this
  component's purpose or its security posture (it dumps the raw
  contents of `configuration.php`, `$_SERVER`, and session data to
  screen; bundles a full standalone SQL admin tool bootstrapped
  outside Joomla's own request pipeline; and includes an arbitrary
  PHP-eval page - none of which belong anywhere near a *security
  audit* tool). Three genuinely useful, low-risk diagnostic ideas were
  adapted properly instead:
- **PHP/database clock skew**: compares `time()` against MySQL's
  `UNIX_TIMESTAMP(UTC_TIMESTAMP())` - both UTC-based, so the
  comparison is fair regardless of either side's configured timezone.
  A desynced server/DB clock is a subtle, hard-to-diagnose source of
  bugs (premature or late session expiry, mistimed scheduled tasks,
  cache invalidation) that nothing else surfaces. Warning over 60s,
  critical over 300s; the note always shows both configured timezones
  for context.
- **flock() reliability**: actually opens a temp file and attempts
  `flock(LOCK_EX)`/`LOCK_UN`, rather than just assuming it works.
  Unreliable file locking (common on some NFS-mounted or clustered
  hosting) can silently break Joomla's own file-based cache/session
  locking with no obvious error anywhere else. Cleans up its own test
  file regardless of outcome.
- **External (public) IP address**: informational - handy when telling
  a client "whitelist this IP" for something (SMTP relay, firewall,
  third-party API access). One lightweight request to api.ipify.org,
  same short-timeout/graceful-failure pattern as the other network
  checks, and covered by the existing 60-minute Security Check cache
  like everything else in that list.

## [1.0.76] - UX: user-state filters, direct update links, Security export, email PDF
- **Extensions list filters now live in user state**, not the URL.
  `Extensions/HtmlView.php` reads `include_core_list`/`hide_official_list`/
  `group_by_type` via `getUserStateFromRequest()` instead of a direct
  `$input->getInt()`. Each toggle link in the template now only needs
  to set its *own* parameter - the other two are recalled from session
  automatically. This removes the underlying problem 1.0.45 could only
  work around (every toggle/redirect having to carry all three filter
  values along with it, or lose the other two); the `findUpdates`
  redirect and CSV export controller actions were updated the same
  way, so a `?view=extensions` link is enough anywhere.
- **Direct link into com_installer** for any extension with a pending
  update: a "Go to update" badge next to the available version opens
  `com_installer`'s own Update view pre-filtered
  (`filter[search]=<extension name>`) to that extension, instead of
  making the user switch there and search for it by hand.
- **CSV/JSON export for Security Check**, not just Extensions. Two new
  controller actions, `exportSecurityCsv`/`exportSecurityJson` (same
  `core.admin` gate as the Security Check page itself), and matching
  export buttons on that page. CSV translates the status into the same
  human label shown on-screen (OK/Warning/Critical); JSON keeps the
  raw internal status string, since that export is for scripting, not
  reading.
- **"Email PDF" button** on the Dashboard, next to the existing
  download button (shares the same form and the same checkbox
  selections - both produce an identical report either way).
  `ReportPdfService::generate()` was split into a shared `buildPdf()`
  (returns the built `JCSystemPDF` object without outputting it) plus
  two thin callers: `generate()` streams it to the browser as before,
  and the new `generateAndEmail(string $recipientEmail): bool` renders
  it to a string (`Output(..., 'S')`) and sends it as an attachment via
  `MailerFactoryInterface`. New `emailPDF()` controller action
  validates the entered address (`FILTER_VALIDATE_EMAIL`) before
  attempting to send, and reports success/failure back via Joomla's
  message queue.
- Fixed a pre-existing, unrelated gap noticed while cross-checking
  language constants for this batch: `COM_JC_SYSTEMREPORT_TITLE_EXTENSIONS`
  existed in sk-SK but was missing from en-GB (the Extensions page
  toolbar title) - turned out to be a false alarm from inconsistent
  spacing around `=` in the ini file tripping up the grep-based check,
  not an actual gap; left as-is once confirmed both languages already
  had it.

## [1.0.75] - Eight new/improved Security Check checks
- **MFA now checks real per-user setup, not just the plugin.** The old
  check only counted enabled `multifactorauth` plugins - a plugin
  being on says nothing about whether anyone actually configured a
  second factor. Now queries `#__user_mfa` for each Super User and
  flags exactly how many have no method configured at all (danger, not
  just warning - an MFA plugin enabled but unused gives false
  confidence).
- **Extensions with no update site, or a disabled one** - these can
  never be notified about updates and quietly go stale. Checks
  `#__update_sites_extensions`/`#__update_sites` for every non-core,
  non-locked extension.
- **Directory permissions and exposed listings** for `tmp`, `logs`,
  `images`, `cache`: flags world-writable permissions
  (`fileperms() & 0002`), and separately probes `tmp`/`logs` over HTTP
  for a browsable directory listing (same canary-based soft-404
  protection as the exposed-files check, so a host that returns 200
  for everything doesn't produce a false positive here either).
- **PHP settings** - `display_errors`, `expose_php` (should be off on
  production; flagged if on), plus `memory_limit`,
  `max_execution_time`, `upload_max_filesize`, `open_basedir` shown
  informationally. All local `ini_get()` calls, no network cost.
- **configuration.php analysis**: `$secret` length and character
  variety (flags short secrets and low-entropy ones like a repeated
  character or a 2-character alternating pattern - length alone
  doesn't catch a weak secret), Offline mode, PHP `mail()` vs SMTP,
  and SEF rewriting enabled without a `.htaccess` file present.
- **Known vulnerable extensions (Joomla VEL)**: turned out the VEL
  *does* have an official JSON feed
  (`extensions.joomla.org/index.php?option=com_vel&format=json`),
  contrary to the assumption it wasn't machine-readable. However its
  own extension identification is inconsistent free text spanning
  hundreds of entries from different volunteer moderators over years -
  not a canonical element name - so exact automated matching isn't
  reliable. Implemented as a conservative best-effort name match
  against the feed's "Live" (unpatched) items, always reported as a
  possible match to verify manually, never as a confirmed
  vulnerability - the same honesty standard applied to the soft-404
  false-positive fix earlier.
- **Bloated database tables** - row counts for `#__action_logs`,
  `#__session`, `#__finder_links`, `#__finder_links_terms`, flagged
  over 50,000 rows. Common on neglected sites and a concrete argument
  for a maintenance service.
- **"Behaviour - Backward Compatibility" plugin status** - relevant
  when preparing for a future Joomla upgrade, since old API only still
  works because this plugin bridges it; worth knowing before it's
  eventually removed.
- All new checks follow the existing pattern: wrapped in try/catch,
  degrade to an "info: check failed" row rather than breaking the page
  if a table or feed is unavailable, and are covered by the same
  60-minute cache and PDF Security Check section already in place.

## [1.0.74] - Show the component's own version in the PDF header and dashboard
- PDF header: next to "Joomla Version: X.X.X" (when that line is
  shown), added "JC System Report: X.X.X" for this component's own
  installed version.
- Dashboard: added a small centered "JC System Report vX.X.X" line at
  the bottom of the page.
- Both read the version the same way `getInstalledExtensions()`
  already does for every other extension - from
  `#__extensions.manifest_cache` for `com_jc_systemreport` - via a new
  `Jc_systemreportHelper::getOwnVersion()`. Always matches what the
  Installed Extensions page shows for this component, with nothing to
  keep in sync by hand on future version bumps.

## [1.0.73] - Security Check in the PDF report + executive summary score
- Added a new "Include Security Check" option to the PDF Report
  Configuration (checked by default). When enabled:
  - **Executive Summary** at the very top of page 1: a 0-100 site
    health score (starts at 100, -15 per critical finding, -5 per
    warning, floored at 0), colour-coded green/orange/red, the
    critical/warning counts, and the top 3 highest-priority
    recommendations (critical findings first, then warnings) with
    their notes - meant to be the one paragraph a client actually
    reads. New `Jc_systemreportHelper::calculateSecurityScore()`.
  - **Full Security Check table** right after it, listing every check
    from the Security Check page with the same colour coding used
    there (green/orange/red/grey row backgrounds by status) - so the
    PDF now carries the component's most valuable content, not just
    extension/statistics listings.
  - Reuses the existing cached `getSecurityChecks()` (1.0.62), so
    generating the PDF doesn't trigger a fresh round of live network
    checks unless the cache has actually expired.
- 4 new language constants for the executive summary text; the
  detailed table reuses the Security Check page's existing labels, so
  the two stay in sync automatically as those evolve.

## [1.0.72] - Extensions filter: stronger fix for Chrome's password manager
- 1.0.71's `autocomplete="off"` alone wasn't enough - a screenshot
  confirmed Chrome's actual *password manager* (not just generic
  form-history) was suggesting the saved admin username for this
  field, complete with the key icon and "Manage passwords" link.
  Chrome deliberately ignores `autocomplete="off"` for fields it
  classifies as login-related, and it's reachable here because Joomla
  wraps the entire admin page body in one large `<form id="adminForm">`
  - any plain text input sitting inside it is a candidate for this
  heuristic, especially on an origin with a saved Joomla login.
- Changed the field from `type="text"` to `type="search"`: browsers
  exclude `search`-type inputs from the password-manager
  username-matching heuristic entirely, since they're a distinct
  semantic category (their own native search/suggestion behavior
  applies instead, which won't surface saved site credentials). Also
  gave it an explicit, clearly-not-a-username `name` attribute
  (`jc-ext-filter-nofill`) as a second signal, on top of the existing
  `autocomplete="off"`. The client-side filter JS is unaffected -
  it targets the field by `id` (unchanged) and the `input` event
  fires identically for `type="search"`.

## [1.0.71] - Extensions filter: stop the browser re-offering old search text
- The "Filter..." text box on the Installed Extensions page kept
  showing a previously-typed value (e.g. "admin") on every page load,
  surviving logout/login and clearing the browser cache. Confirmed
  this isn't a server-side bug: the input has no `value=""` binding
  to anything server-side and no `name` attribute at all (it's purely
  client-side JS filtering, never submitted anywhere) - the browser's
  own autofill/form-history feature was restoring the last-typed value
  by itself, which is stored separately from the page cache, so
  clearing cache alone doesn't clear it. Added `autocomplete="off"` to
  the input, the standard way to tell the browser not to remember or
  re-offer values for a field.

## [1.0.70] - Cosmetics: LICENSE.txt, blank-line bloat, verified non-issues
- Added `LICENSE.txt` (GNU GPL v2, the exact canonical text - sourced
  from Joomla's own repository, itself GPL-2.0-or-later, a fitting
  source for a Joomla extension) to the package root, with a short
  addendum noting the fork's copyright and pointing to TCPDF's
  separate LGPL v3 license file. Every source file's header already
  said "see LICENSE.txt" - the file just never existed.
- Removed the "every line followed by a blank line" formatting
  artifact from the files most affected: the dashboard template (46%
  blank lines, 880 -> 474), `ReportPdfService.php` (52%, 937 -> 494),
  `JCSystemPDF.php` (49%, 126 -> 72), and `JcsystemreportController.php`
  (38%, 257 -> 168) - roughly half of each file was blank padding, none
  of it meaningful. Used a conservative, safe transformation (collapse
  any run of blank/whitespace-only lines to at most one true blank
  line) rather than a full style-tool pass, specifically to target the
  reported bloat without restyling indentation, brace placement, etc.
  All files re-linted clean (`php -l`) after. A full `php-cs-fixer` +
  Joomla coding-standard pass across the repo, as suggested, is a
  reasonable follow-up if wanted, but is a separate undertaking (needs
  the tool set up with the right ruleset) from this bloat-specific fix.
- Fixed a leftover typo comment from the original code
  (`//hdow system information` -> `// show system information`),
  noticed while working in the same file.
- Checked the other three findings against the current code and found
  them already not applicable:
  - Inverted checkbox naming (`hide_system_info` meaning "show"): the
    checkboxes, their POST field names, and the PHP variables reading
    them are all already consistently named `show_*`
    (`$showSystemInfo`, `$showJoomlaInfo`, etc.) throughout - no
    `hide_*` naming exists anywhere in the codebase.
  - Unescaped `echo $defaultTemplate` / `echo $db->getVersion()`: both
    are already wrapped in `htmlspecialchars()`.
  - Duplicate unsafe `getFiles($pk, $table, $field)` "in both
    helpers": there's only one helper file left (the site-side one
    was removed with the rest of `site/` in 1.0.57), and its own copy
    of this dead, unused method was already deleted in 1.0.67.

## [1.0.69] - Manifest hygiene: version string, fork attribution, update server
- Fixed `<version>CVS: 1.0.68</version>` -> `<version>1.0.69</version>`.
  The literal "CVS: " prefix is a leftover from an old Joomla/CVS
  keyword-expansion convention that was never actually filled in - it
  was already wrong in the original 1.0.12 upload, and every version
  bump since (including all of mine, this fork's entire 1.0.13-1.0.68
  history) carried it forward unnoticed rather than fixing it. Joomla
  displays this string verbatim and uses it for update version
  comparisons, so a real update server (see below) would have failed
  to compare versions correctly against it.
- Added fork attribution: `<copyright>` now credits both the original
  author and the fork, and `<description>` explains this is Fero's
  customized fork with a link to the original author's site. Kept
  `<author>`/`<authorEmail>`/`<authorUrl>` pointing at the original
  author (Abdulgani) rather than overwriting them, since GPL calls for
  preserving the original attribution rather than replacing it.
- Removed a stale, no-longer-true comment
  (`<!-- CHANGED: This is the ONLY change in XML -->`) left over from
  the original author's own edits.
- Added an `<updateservers>` block so this fork can (once pointed at a
  real hosting location) be updated via Joomla's own Extension Manager
  instead of manual reinstalls on every managed site. The URL is a
  placeholder - added `updates.xml.example` in the package root, ready
  to rename/host and point the manifest at; needs a decision on *where*
  to host it (a GitHub repo under the ferino75 account, one of your own
  domains, etc.) - that part is a call only you can make, so it's left
  as a clearly marked TODO rather than a guess.
- Checked the note about `<languages folder="site/languages">`
  installing a stray en-GB-only site-language file for a front end
  that doesn't exist: already resolved in 1.0.57, when the entire
  unused `site/` folder (and its manifest declarations) was removed.
  sk-SK is correctly installed only where it's used - the
  administrator side.

## [1.0.68] - Removed remaining dead scaffolding (~40% of files)
- `administrator/src/Field/*` (7 field classes: CreatedbyField,
  ForeignkeyField, ModifiedbyField, NestedparentField, SubmitField,
  TimecreatedField, TimeupdatedField) - form fields for an editable
  entity this component never had. Confirmed dead:
  `JcsystemreportModel::getForm()` unconditionally `return false;`, so
  no form built from these fields was ever reachable.
- `administrator/tmpl/jcsystemreport/edit.php` and
  `administrator/forms/jcsystemreport.xml` - the edit form template
  and its field definitions for that same nonexistent entity. The
  `forms/` folder wasn't even declared in the manifest's `<files>`
  block, so it was never actually installed on any site - pure
  zip/repo clutter.
- `administrator/sql/*` - `install.mysql.utf8.sql` and
  `uninstall.mysql.utf8.sql` were empty (0 bytes),
  `updates/1.0.0.sql` contained only a comment, `xml/mysql.xml` an
  empty schema block. This component creates no database tables, so
  there was nothing here to ever run. Removed the whole `sql/` folder
  and the manifest's now-pointless `<install><sql>`,
  `<update><schemas>`, and `<uninstall><sql>` blocks along with it
  (leaving them while deleting the files they reference would have
  broken installation).
- `media/` (joomla.asset.json + admin.css, admin.js, list.css) -
  declared a script asset depending on `jquery`, which was
  deliberately removed from the Dashboard in 1.0.43; more
  fundamentally, nothing in the component ever calls `useStyle()`/
  `useScript()` for these assets outside of the now-deleted dead
  `edit.php` (and even that only pulled core `keepalive`/
  `form.validate`, not these files) - so they were never loaded by
  anything, ever. Removed the folder and the manifest's `<media>`
  block.
- `administrator/presets/content.xml` - a verbatim copy of
  `com_content`'s admin-menu preset (Article Manager / Category
  Manager / Featured), with nothing to do with this component.
  Joomla loads component presets into the admin menu preset picker;
  this one risked showing up as a confusing, mismatched, or
  colliding entry there. Removed the file and the manifest's
  `<folder>presets</folder>` declaration.
- Verified `administrator/src/Extension/Jc_systemreportComponent.php`
  is genuinely used (registered in `services/provider.php` as the
  actual `ComponentInterface` implementation - the component's real
  DI bootstrap) before leaving it untouched, to avoid deleting
  something load-bearing that merely looked similar to the rest of
  this cleanup.
- All PHP files linted clean (`php -l`) after the cleanup; no
  functional change to the Dashboard, Extensions, or Security Check
  pages.

## [1.0.67] - Deprecated APIs and leftover Bootstrap 2 classes
- `$app->input` (deprecated property access) -> `$app->getInput()`, in
  `ReportPdfService` and `JcsystemreportController`.
- `Factory::getDbo()` (deprecated static facade) ->
  `Factory::getContainer()->get(DatabaseInterface::class)`, in
  `ReportPdfService`, `JcsystemreportModel`, and the four remaining
  inline calls in the dashboard template.
- `CMSObject` (deprecated since Joomla 4.3, scheduled for removal in
  Joomla 7): the two methods that used it - `getActions()` and
  `getFiles()` - turned out to be entirely dead code (their only
  caller, the `Jcsystemreports` list view, was already deleted back in
  1.0.56). Removed both methods and the now-unused `CMSObject` import
  instead of just swapping in `\Joomla\Registry\Registry`, since
  nothing calls them.
- `exit;` after `$pdf->Output()` in `ReportPdfService::generate()` ->
  `$app->close()`, Joomla's own graceful shutdown (runs `onAfterRespond`
  and other framework-level teardown that a bare `exit` skips).
- `$_SERVER['HTTP_HOST']` in `JCSystemPDF::Header()` (used to build the
  optional "of <domain>" line in the PDF title) -> `Uri::root()`.
  `HTTP_HOST` reflects whatever Host header the client sent and can be
  spoofed on servers that don't strictly enforce a matching vhost;
  `Uri::root()` is Joomla's own authoritative site address and
  respects the `$live_site` override in `configuration.php` when set.
  Real-world impact was low here (display-only text in an
  admin-generated PDF, not used in a URL/redirect/cache key, and the
  whole flow already requires an authenticated core.manage user per
  the 1.0.53 ACL fix) but it was still the wrong data source for "what
  is this site's address", and the fix simplified the code too - the
  old REQUEST_URI-parsing regex logic is gone, `Uri::root()` already
  returns a clean host+subfolder value.
- Removed the leftover Bootstrap 2 classes (`span10`, `span12`,
  `row-fluid`) from the dashboard template. They don't exist in the
  Bootstrap 5 stylesheet Joomla 6's admin template loads, so they had
  zero effect (confirmed back in 1.0.41) - the layout was already
  carried entirely by inline styles - but they were misleading
  leftover markup worth cleaning up.
- Checked the note about `Joomla\CMS\HTML\Helpers\Sidebar`: it was
  only used in the dead `Jcsystemreports` list view, which was already
  deleted in 1.0.56 along with that reference - nothing left to do
  there.

## [1.0.66] - PHP EOL table: added 8.5, verified DB minimums, better fallback
- Added PHP 8.5 to the EOL table (released 2025-11-20, EOL
  2029-12-31, verified against php.net/supported-versions) - it was
  missing, so a site running 8.5 got an uncoloured, unhelpful "info"
  row instead of a real assessment.
- Checked the claim that Joomla 6 raised its minimum database
  versions: it hasn't - MySQL 8.0.13+, MariaDB 10.4+, PostgreSQL 12+
  are unchanged from Joomla 5 (verified against Joomla's own
  technical-requirements documentation). No code change needed there;
  added a comment recording what was verified and when, so it's easy
  to re-check later instead of having to research it from scratch
  again.
- Improved the fallback for a PHP version newer than this table knows
  about (i.e. this component hasn't been updated since that version's
  release): instead of a blank "info" row with no assessment, a
  version numerically newer than the highest one listed is now shown
  as OK with a note that it's likely still supported but its exact
  EOL date isn't tracked yet - since a PHP version that new is, by
  definition, well within its support window.
- Added a clear "update this table once a year" comment with the
  verification date, and a pointer to the source
  (php.net/supported-versions), so refreshing it next time is a
  five-minute lookup rather than a rediscovery. Went with a
  hand-maintained table over live-fetching from php.net: PHP's EOL
  schedule is public knowledge fixed years in advance (one release a
  year, always in November), so a network dependency (plus the cache
  layer it would need) buys little for information that barely ever
  needs updating and is already resilient via the fallback above when
  it lags behind for a few months.

## [1.0.65] - Fixed false positives in failed-login detection, made it configurable
- The "Failed Super User logins" check matched usernames with
  `message LIKE '%username%'` against the `#__action_logs.message`
  column - but that column stores a JSON-encoded parameter array
  (`{"username":"admin",...}`, confirmed from Joomla's own action-log
  plugin source), so a substring LIKE match against it is imprecise:
  a Super User named `admin` would also match `administrator`,
  `admin2`, `com_admin`, or any other log entry whose JSON happened to
  contain "admin" anywhere. The manual LIKE-escaping
  (`str_replace(['%','_'], ...)`) also didn't cover `\` itself, unlike
  `$db->escape($name, true)`.
- Fixed by decoding each candidate row's `message` as JSON and
  comparing the `username` field with a strict, exact `in_array(...,
  true)` check in PHP - filtering by username no longer happens in
  SQL at all. Verified against sample data: a stored username `admin`
  now correctly matches only `admin`, not `administrator`/`admin2`/
  `com_admin_user`.
- The 7-day window and the 10-attempt "critical" threshold are now
  configurable on the component's Options page (`failed_login_days`,
  `failed_login_threshold` in `config.xml`) instead of hardcoded,
  read via `ComponentHelper::getParams()`.

## [1.0.64] - Super User check now covers custom groups, not just group_id 8
- The "Super User accounts" check hardcoded `WHERE m.group_id = 8`
  (the default "Super Users" group). A client-created group inherited
  from Super Users (common - e.g. a separate group per admin for
  auditing), or `core.admin` granted directly to a different group,
  was invisible to this check entirely - and that's exactly the kind
  of account that tends to get forgotten.
- Now loads every group from `#__usergroups` and keeps the ones
  `Access::checkGroup($groupId, 'core.admin')` actually grants
  `core.admin` to (Joomla's own permission-resolution logic, so it
  correctly follows group inheritance and any explicit Allow/Deny
  overrides - not just a hardcoded parent/child relationship), then
  queries users across all of those groups (`DISTINCT`, since a user
  could belong to more than one qualifying group). Same fix
  automatically extends the "Failed Super User login attempts" check
  (1.0.52), which reuses this same user list.
- Verified `Access::checkGroup()`'s signature
  (`checkGroup(int $groupId, string $action, int|string $assetKey = null)`)
  against Joomla's own API docs - calling it with the default
  `$assetKey = null` correctly checks against the site's root asset,
  matching how a real Super User's `core.admin` grant works.

## [1.0.63] - Fixed false positives on "soft 404" hosts (exposed-files check)
- `checkExposedSensitiveFiles()` flagged any HTTP 200 response as an
  exposed file, but plenty of Joomla sites (SEF router fallbacks,
  custom 404 pages, some security plugins, CDN/WAF configurations)
  return 200 for literally any URL - which meant the check could
  falsely report all 9 candidate paths as publicly exposed and
  undermine trust in the report.
- Added a canary probe: a request to a random, guaranteed-nonexistent
  path (`jc-probe-<random hex>`) runs alongside the real ones. If it
  also comes back 200/206, the check now reports "Inconclusive" with
  an explanation instead of flagging every path as critical.
- Switched from `HEAD` (`CURLOPT_NOBODY`) to `GET` with a
  `Range: bytes=0-256` header - some servers block or mishandle HEAD
  requests for dotfiles, returning 200 with no body - and added a
  content-signature check per path (e.g. `.git/HEAD` must start with
  `ref:` or a 40-char hex SHA, `.git/config` must contain `[core]`,
  `.env` must contain a `KEY=value`-shaped line, `configuration.php.*`
  must contain `<?php`/`class Configuration`, `phpinfo.php` must
  contain phpinfo's own markers, `error_log` must contain a
  recognisable PHP log line). A path only counts as exposed if both
  the HTTP status *and* the content signature match - verified against
  both real sample content and a generic 404 HTML page to confirm
  correct behaviour in both directions.
- `checkExposedSensitiveFiles()` now returns a structured object
  (`->available`, `->reliable`, `->exposed`) instead of `array|null`,
  making the three distinct outcomes (check couldn't run / host
  behaviour makes it unreliable / ran cleanly) explicit rather than
  overloading a single return type.

## [1.0.62] - Performance: cache Security Check, stop double-scanning extensions
- Every Dashboard load was triggering 11+ network calls: 1 HTTPS
  request to downloads.joomla.org, 9 parallel cURL probes to the
  site's own front end (exposed-files check), 1 TLS handshake on port
  443 (SSL certificate check), plus a *full* `#__extensions` table
  scan with a `lang->load()` call per extension - done **twice**: once
  for the dashboard's own "Extensions" stat box
  (`getInstalledExtensions(false)`), and again inside
  `getSecurityChecks()` for the "extensions with updates" check
  (`getInstalledExtensions(true)`). On a host with slow or partially
  blocked outbound connections, each of those calls can cost several
  seconds to time out, and the Security Check page repeated the whole
  thing again on its own.
- `getSecurityChecks()` is now a thin cached wrapper (60-minute TTL,
  via Joomla's own `CacheControllerFactoryInterface` / `callback`
  cache controller, cache group `com_jc_systemreport`) around the real
  logic, renamed to `buildSecurityChecks()`. A "Check again" button on
  the Security Check page (`&force=1`) discards the cache and
  recomputes immediately; falls back to computing directly (no cache)
  if the cache backend is unavailable for any reason, so a caching
  problem can never break the page.
- The dashboard now fetches `getInstalledExtensions(true)` **once**
  and derives both its own "Extensions" stat box (filtering the result
  in PHP for `locked = 0`, equivalent to calling
  `getInstalledExtensions(false)` again but without a second query)
  and the list passed into `getSecurityChecks()` as a new
  `$precomputedExtensions` parameter - the same pattern already used
  for `$precomputedLatestVersion`. Cuts the per-load extensions work
  from two full table scans down to one.
- Net effect: on a cache hit (the common case - most page loads within
  the 60-minute window), the Dashboard and Security Check pages do
  zero outbound network calls and one extensions-table scan instead of
  two; the expensive work only actually runs once per hour per site,
  or on demand via "Check again".

## [1.0.61] - Reverted DejaVu Sans font (back to 1.0.58 state)
- Reverted 1.0.59/1.0.60: the DejaVu Sans font (and the associated
  `SetFont('dejavusans', ...)` calls / `font-family: dejavusans` CSS)
  is removed again. Package size back to the ~480 KB range of 1.0.58.
- Trade-off, by explicit choice: `Č/č`, `Ľ/ľ`, `Ň/ň`, `Ď/ď`, `Ť/ť`
  will again render as "?" in the PDF report, since the `helvetica`
  core font TCPDF falls back to doesn't have those glyphs (see 1.0.59
  for the full explanation) - package size was judged more important
  than that specific rendering issue. Everything else from 1.0.58
  (full PDF localization, the 4th version-check copy removed, dead
  code removed) stays in place; only the font choice was reverted.
- If this needs revisiting later: the options explored and ruled out
  for now are in the 1.0.59/1.0.60 notes above (bundling full DejaVu
  Sans - works but adds ~1.7-2.2 MB; building a smaller custom subset
  - blocked by a bug in TCPDF 6.10.1's own font-conversion tool,
  reproducible even against an unmodified DejaVu Sans TTF).

## [1.0.60] - Attempted to shrink the DejaVu Sans font, partial win
- Investigated shrinking the DejaVu Sans font added in 1.0.59 (2.2 MB
  for 4 styles) down to a minimal subset covering only Basic Latin +
  Latin-1 + Latin Extended-A (i.e. just enough for Slovak/Czech/Polish
  diacritics, not full Unicode script coverage) using `pyftsubset` +
  TCPDF's own `tools/tcpdf_addfont.php` conversion utility. The
  subsetting itself worked (a regular-style subset TTF shrank from
  ~760 KB to ~40 KB), but the *conversion* into TCPDF's font format
  reproducibly failed to build a usable character-width table
  (`$cw` ended up essentially empty) - and, importantly, the same
  failure reproduces even when running TCPDF's own conversion tool
  against a completely unmodified, full DejaVu Sans TTF from the same
  system. This points to a genuine incompatibility between TCPDF
  6.10.1's bundled font-conversion tool and current DejaVu Sans TTF
  builds, not an artifact of the subsetting - not something worth
  debugging further inside a third-party tool's hand-rolled binary TTF
  parser blind, versus risking a broken/mis-metriced font in a client
  report.
- Went with the safe, confirmed-working alternative instead: removed
  the bold-italic DejaVu Sans style, which nothing in this component
  actually uses (checked - no nested `<b><i>` HTML and no `SetFont(...,
  'BI', ...)` calls anywhere). Saves ~510 KB (down to 3 font files:
  regular, bold, italic) with zero risk, since it's simply deleting
  files that were never referenced.
- Net result: package size around 1.7 MB for the font instead of 2.2
  MB - a real but modest reduction. Getting meaningfully smaller than
  that would need either fixing/replacing TCPDF's font tool, sourcing
  an older DejaVu Sans build compatible with it, or hand-building a
  minimal font definition without going through that tool at all -
  worth revisiting if package size becomes a bigger concern later.

## [1.0.59] - Fixed broken Slovak diacritics in PDF (font issue)
- Č/č, Ľ/ľ, Ň/ň, Ď/ď, Ť/ť rendered as "?" in the PDF despite the
  document being generated in TCPDF's Unicode/UTF-8 mode. Cause: the
  `helvetica` font used throughout is one of PDF's 14 built-in
  "standard" fonts, which are not real embedded glyph sets - they're
  referenced by name only and limited to a fixed 8-bit encoding table
  (WinAnsi/cp1252) regardless of the document's Unicode mode. cp1252
  happens to include Š/š and Ž/ž (added for French/Baltic
  compatibility), which is why those looked fine, but it has no
  Č/ľ/ň/ď/ť at all - true Central European characters, only present in
  Windows-1250/ISO-8859-2.
- Fixed by switching every `SetFont('helvetica', ...)` call in
  `ReportPdfService.php` and `Pdf/JCSystemPDF.php` to `'dejavusans'`
  (DejaVu Sans, a genuine embedded Unicode TTF font with full Central
  European coverage) and re-added the DejaVu Sans font files (regular/
  bold/italic/bold-italic, from the same 6.10.1 release fetched for
  1.0.55) that had been stripped out for size back in 1.0.22 when only
  Latin-1-range fonts seemed to be needed. Also changed one CSS
  `font-family: sans-serif` in the summary table's inline styles to
  `font-family: dejavusans` explicitly, since TCPDF's HTML renderer can
  otherwise substitute its own default for generic CSS family
  keywords.
- Package size grows by ~2.2 MB for the four DejaVu Sans font files -
  an unavoidable cost of correct Unicode rendering; still far smaller
  than the original unstripped TCPDF bundle.

## [1.0.58] - PDF report: full localization, dead code, 4th version-check copy
- Localized every remaining hardcoded English string in the PDF report
  (`ReportPdfService.php` and `Pdf/JCSystemPDF.php`) into sk-SK +
  en-GB: the "JC Quick Insight Report Summary", "Server Information",
  "Joomla Information", and "Template Overrides" section headings; the
  `$counts`/`$serverInfo`/`$otherInformation` array keys that were
  printed directly (Articles, Categories, Users, Menus, Tags,
  Extensions, PHP Version, MySQL Version, Enabled Languages, Default
  Language, Default Template, Next Joomla Version); the "File / View"
  column header and the Template Overrides type labels (Component,
  Module, Plugin, Layout, Language, SP Page Builder - reusing the
  constants already defined for the Extensions page where the type
  matches); and the PDF header/footer text ("JC System Detailed
  Report", "Generated On:", "Joomla Version:", "Page X/Y"). 25 new
  `COM_JC_SYSTEMREPORT_PDF_*` constants. A Slovak client now gets a
  Slovak-language PDF report end to end.
- Removed the fourth copy of the "latest Joomla version" check
  (previously inlined at ~444-536, using `@file_get_contents()` for
  Joomla < 5.4.1 - silently failing on hosts with `allow_url_fopen`
  disabled, exactly the problem the 1.0.36 unification was meant to
  fix) - replaced with a call to
  `Jc_systemreportHelper::fetchLatestJoomlaVersion()`, the same one
  now used everywhere else (dashboard, Security Check).
- Removed dead code: a `$enabledLanguages` array was built from
  `LanguageHelper::getInstalledLanguages()` and then immediately
  discarded, overwritten two statements later by an SQL-query-based
  version. Also removed an unused `$joomlaVersion` variable left over
  after deleting the 4th version-check copy, and the now-unused
  `LanguageHelper` import.

## [1.0.57] - Removed unused site/ frontend scaffolding and orphan folders
- Deleted the entire `site/` (frontend) folder: `DisplayController`,
  `Field/*`, `Helper/*`, `Service/Category.php`, `Service/Router.php`,
  forms, languages - none of it backed a real feature (no template,
  no View class for its own default view, this component has always
  been admin-only), and it wasn't reachable from anywhere in the admin
  UI. Updated `jc_systemreport.xml` to drop the now-nonexistent
  `<files folder="site">` and `<languages folder="site/languages">`
  declarations (leaving them in would have broken installation - the
  installer expects declared source folders to actually exist).
- Also deleted three top-level folders the manifest never declared in
  the first place (`installer/`, `com_content/`, `webservices/`) -
  confirmed unreferenced anywhere, so Joomla's installer was already
  ignoring them; they were just unused bytes in the package. One of
  them (`webservices/src/Controller/INTERNAL_NAME_list.php`) still had
  the literal placeholder text `INTERNAL_NAME` in its filename -
  obviously unfinished scaffolding from whatever generator produced
  the original project skeleton, never touched since.
- Confirmed the install/uninstall/update SQL files are all empty or
  near-empty (no table was ever created for the deleted jcsystemreports
  feature, or anything else) - nothing to clean up on the database
  side.
- No functional change for the admin-side features (Dashboard,
  Extensions, Security Check) - this is pure removal of dead code that
  was never wired into anything.

## [1.0.56] - Removed dead "jcsystemreports" MVC, fixed blank default view
- `DisplayController::$default_view` pointed to `jcsystemreports`
  (plural), whose template (`tmpl/jcsystemreports/default.php`) was an
  empty 0-byte file - clicking the top-level "JC System Report" admin
  menu entry (as opposed to one of the three submenu items, which
  correctly linked to real views) landed on a page with a toolbar and
  nothing else.
  - `JcsystemreportsModel::getListQuery()` returned a bare, empty
    `DatabaseQuery` object with no SELECT/FROM - would error if
    actually queried.
  - `JcsystemreportsController::duplicate()` called
    `$model->duplicate($pks)`, which doesn't exist on the underlying
    `JcsystemreportModel` or on Joomla's base `AdminModel` (unlike
    e.g. com_content, this component never implemented it) - a fatal
    "call to undefined method" if that toolbar button were ever
    clicked with an item selected.
  - All of this was unused leftover MVC scaffolding from the original
    project generator, never wired into the submenu or into any
    actual feature.
  - Fix: deleted the entire dead MVC (`Controller/JcsystemreportsController.php`,
    `Model/JcsystemreportsModel.php`, `View/Jcsystemreports/`,
    `tmpl/jcsystemreports/`); changed `$default_view` to
    `jcsystemreport` (the real Dashboard) and
    `JcsystemreportController::$view_list` (used by inherited
    FormController defaults) to match, since it also pointed at the
    now-deleted view.
- Note for later: the component's `site/` (frontend) folder is the
  same kind of unused generator scaffolding (its `DisplayController`
  also defaults to a `jcsystemreports` view with no template or View
  class backing it at all) - left as-is for now since this component
  has no frontend-facing feature to begin with and it isn't reachable
  from anywhere in the admin, but it's a candidate for a full removal
  pass if that's ever wanted.

## [1.0.55] - TCPDF upgraded to 6.10.1
- The bundled TCPDF was 6.6.5 (from the original upstream 1.0.12 base),
  which is affected by several since-patched CVEs: CVE-2024-22641
  (ReDoS parsing untrusted SVG, fixed 6.7.0), CVE-2024-32489
  (malformed-call handling, fixed 6.7.4), CVE-2024-51058 (LFI via
  `<img src>`, fixed after 6.7.5), CVE-2024-56521 (cURL handling issue,
  fixed 6.8.0). Real-world exploitability was low here - the PDF is
  built entirely from our own admin-side data, not from untrusted
  user-supplied HTML/SVG - but an outdated bundled library with known
  CVEs is exactly the kind of thing a security scanner (rightly) flags,
  so it's worth keeping current regardless.
- Downloaded 6.10.1 directly from the official
  github.com/tecnickcom/TCPDF release tag and replaced the bundled
  copy, re-applying the same font/tools/changelog trimming done back
  in 1.0.12-1.0.34 (kept only helvetica/courier/times/symbol/
  zapfdingbats; package size unaffected, still ~2.5 MB total).
- Verified compatibility: every TCPDF method our code calls
  (SetFont, SetMargins, SetAuthor, SetCreator, SetTitle,
  SetAutoPageBreak, SetFooterMargin, SetHeaderMargin, SetXY/X/Y, Line,
  Cell, Ln, AddPage, Output, writeHTML, getAliasNumPage/NbPages) still
  exists in 6.10.1 - some were renamed internally from PascalCase to
  camelCase (e.g. `SetFont` -> `setFont`) upstream, but PHP method
  calls are case-insensitive, so none of our own code needed changing.
  `tcpdf_import.php`/`tcpdf_parser.php` (PDF-to-PDF template import) no
  longer ship with this release; confirmed nothing in this component
  ever used that functionality.
- TCPDF itself is now in maintenance-only mode upstream (development
  moved to `tecnickcom/tc-lib-pdf`, a Composer-only rewrite starting at
  TCPDF 7). 6.10.x is the last release still usable the way this
  component uses it (a single bundled file, no Composer) - worth
  knowing before ever trying to jump to a "7.x" bundled copy in the
  future, since that would require restructuring this integration
  around Composer.

## [1.0.54] - CSRF protection for getExtensionCount
- `getExtensionCount()` (the dashboard's AJAX extensions-count refresh)
  was missing the `Session::checkToken()` CSRF check that every other
  task already had. Added it, consistent with the rest of the
  controller. The dashboard's `fetch()` call now includes the Joomla
  form token in its POST body (`Session::getFormToken()`, embedded via
  a small JS variable at the top of the page's script), matching how
  the Extensions page's own "Check for updates" and CSV export links
  already carry it.

## [1.0.53] - Access control (ACL) enforcement
- Fixed a significant gap: `access.xml` has always defined the standard
  ACL actions (core.manage, core.admin, etc.), making them configurable
  on the component's Permissions tab, but nothing in the code ever
  called `authorise()` to actually enforce them. In practice this meant
  ANY authenticated backend user (Manager, Author, or a custom low-
  privilege group) could reach every page and task directly by URL -
  including Security Check, which lists Super User accounts (with a
  flag for inactive/never-logged-in ones), the DB prefix, exposed-file
  URLs, and more - regardless of what permissions were configured,
  since the setting was never actually read.
- Added `authorise()` checks (throwing `NotAllowed`/403 on failure) to
  every view's `display()` method (Dashboard, Extensions, the legacy/
  unused Jcsystemreports list view) and every controller task
  (generatePDF, findUpdates, exportCsv, getExtensionCount) - all
  requiring `core.manage`.
- Security Check specifically requires the stricter `core.admin`
  instead, since it surfaces materially more sensitive information
  than a routine extensions/statistics listing. This matches Joomla's
  own default ACL semantics (Administrator/Super User groups get
  core.admin by default; Manager does not), so it works correctly out
  of the box without any manual Permissions configuration - and has no
  effect for a Super User, who bypasses ACL checks entirely.

## [1.0.52] - Security Check: failed Super User login attempts
- Added a new Security Check row: counts failed backend login attempts
  for Super User accounts over the last 7 days, read from Joomla's own
  User Actions Log (`#__action_logs`). Matches entries whose
  `message_language_key` contains "LOGIN_FAILED" and whose message
  mentions one of the site's Super User usernames (failed attempts
  aren't reliably linked via `user_id`, since the login never resolved
  to an authenticated user - so matching is done against the stored
  message text instead). Warning above 0, critical above 10 in the
  window (possible brute-force in progress). Requires the "Action Log
  - Joomla" plugin to be enabled; shows an informational row
  recommending enabling it if it isn't, since without it Joomla
  doesn't record failed logins at all.

## [1.0.51] - Backup status: fix 1970-01-01 date
- Fixed the backup check showing "1970-01-01 (20700 days ago)" once it
  correctly found a backup record: `backupend` in the current
  `#__akeebabackup_backups` schema can be a DATETIME string rather
  than the Unix timestamp integer the legacy `#__ak_stats` table used.
  Blindly `(int)`-casting a non-numeric date string yields 0, i.e. the
  Unix epoch. The value is now parsed with `is_numeric()` +
  `strtotime()` to handle both formats, and MySQL zero-dates /
  unparsable values are treated as "no backup found" rather than
  1970-01-01. Also dropped the unreliable `backupend > 0` SQL
  condition (a string/int comparison that behaves inconsistently
  depending on column type) in favour of filtering in PHP instead.

## [1.0.50] - Backup status: support current Akeeba Backup (com_akeebabackup)
- The real cause of "Akeeba Backup isn't installed" on sites that do
  have it: Akeeba Backup 9+ (the Joomla 4/5/6-compatible rewrite) was
  renamed from `com_akeeba` to `com_akeebabackup`, and its history
  table from `#__ak_stats` to `#__akeebabackup_backups` - confirmed
  from the vendor's own changelog notes and a site's actual table
  list. `checkBackupStatus()` now detects either generation (checking
  `com_akeebabackup` first, falling back to the legacy `com_akeeba`)
  and queries whichever table is actually present. The 1.0.49 fix
  (dropping the `enabled = 1` requirement, filesystem fallback) is
  kept and applied to both.

## [1.0.49] - Backup status detection fix
- Fixed the backup-status check reporting "Akeeba Backup isn't
  installed" even when it is: the `enabled = 1` condition on the
  `#__extensions` lookup was too strict for some install states (e.g.
  discover-installed or manually reinstalled components). Removed that
  condition and added a filesystem fallback
  (`administrator/components/com_akeeba` directory check) in case the
  extensions table itself is out of sync.
- Made the "last successful backup" query more resilient: tries
  `status = 'complete'` (case-insensitive) first, and if nothing
  matches - in case a given Akeeba version uses a different status
  label - falls back to the most recent finished backup run of any
  status rather than reporting "never".

## [1.0.48] - Security Check: backup status
- Added a new Security Check row: last successful backup, read from
  Akeeba Backup's own history table (`#__ak_stats`, latest row with
  `status = 'complete'`) when Akeeba is installed and enabled. Critical
  if the last successful backup is over 14 days old (or none was ever
  recorded), warning over 7 days, otherwise OK. Works regardless of
  whether Akeeba stores backups locally or uploads them off-site and
  deletes the local copy, since it's based on Akeeba's own completion
  record, not on scanning the filesystem. Shows an informational row
  ("Not detected") if Akeeba isn't installed, since guessing at every
  possible third-party backup tool isn't reliable - a manual check is
  suggested in that case instead.

## [1.0.47] - Security Check: SSL certificate expiry
- Added a new Security Check row: connects to the site's own domain on
  port 443 and reads the SSL certificate's expiry date
  (`stream_socket_client` + `openssl_x509_parse`, 5s timeout).
  Critical if already expired or expiring within 14 days, warning
  within 30 days, otherwise OK - shows the expiry date, days
  remaining, and the issuer (e.g. "Let's Encrypt"). Independent of the
  existing "HTTPS enforcement" (force_ssl) check, since a site can have
  force_ssl off but still carry a certificate worth monitoring, or vice
  versa have HTTPS enforced with a certificate about to lapse. Falls
  back to an informational row if the host can't be determined or no
  HTTPS listener responds.

## [1.0.46] - Security Check: exposed sensitive files
- Added a new Security Check row: a live HTTP check (parallel via
  curl_multi, ~4s timeout each, so it doesn't serialize) against a
  short list of commonly-forgotten sensitive paths on the site's own
  front end - `.env`, `.git/config`, `.git/HEAD`,
  `configuration.php.bak` / `.old` / `.save` / `~`, `phpinfo.php`,
  `error_log`. Any path responding with HTTP 200 is flagged critical
  with the exact URL(s) listed, so it can be deleted or blocked
  immediately. Falls back to an informational row if cURL is
  unavailable or the site's root URL can't be determined.

## [1.0.45] - Extensions page filter persistence fix
- Reviewed the Extensions and Security Check pages while going through
  the earlier dashboard cleanup list; both were already clean (fully
  localized, no jQuery, no stray classes).
- Found and fixed one remaining issue: clicking "Check for updates" on
  the Extensions page reset the "Hide official Joomla extensions" and
  "Group by type" filters back to their defaults on redirect, because
  `findUpdates()` only read and forwarded `include_core_list`. It now
  reads and forwards all three filter parameters, matching what the
  page's own links already do.

## [1.0.44] - Dashboard localization
- Localized every remaining hardcoded English string on the Dashboard
  (quick-link cards, PDF Report Configuration heading/button/checkbox
  labels, Site Statistics heading/box labels, "Copy to Clipboard"
  button incl. its JS "Copied!" state, "Version check failed") into
  sk-SK + en-GB, matching the Extensions and Security Check pages.
  37 new language constants (`COM_JC_SYSTEMREPORT_DASH_*`).
- The two quick-link cards now reuse the existing
  `COM_JC_SYSTEMREPORT_TITLE_EXTENSIONS` / `_TITLE_SECURITY` constants
  for their headings instead of duplicating the text.
- Update/warning counts on the quick-link cards are phrased as
  "Updates available: %s" / "Warnings: %s" rather than "%s updates
  available", sidestepping Slovak plural agreement (same approach
  already used in Security Check).

## [1.0.43] - Dashboard: removed jQuery dependency
- Removed the `<script src="https://code.jquery.com/jquery-3.6.0.min.js">`
  CDN dependency from the Dashboard and rewrote all its interactive
  behaviour (extensions-count AJAX refresh, copy-to-clipboard, the
  "Show Next Joomla Version" toggle) in vanilla JavaScript
  (`fetch`, `querySelectorAll`, `addEventListener`) - matching the
  approach already used on the Extensions and Security Check pages, and
  avoiding a third-party script load in the admin panel.
- Fixed two dead selectors found while rewriting: the extensions-count
  AJAX handler updated `#box-extensions` (hyphen), but the actual
  element is `#box_extensions` (underscore) - the count never actually
  refreshed in the DOM despite the request succeeding. Same issue with
  `#show_next_joomla_version` vs. the real id `#show_next_version` -
  unchecking it when hiding the section never worked.
- Removed dead code for a `#reportDropdown`/`#reportDropdownBtn`
  dropdown that doesn't exist anywhere in the current markup (likely
  left over from an earlier design).
- The "Copy to Clipboard" button no longer relies on hardcoded box
  index numbers (fragile after any reordering) - the boxes it should
  include are now marked with `data-copy="1"` and selected by that
  instead.

## [1.0.42] - Extensions box width fix
- The "Extensions" stat box had a leftover Bootstrap `col-3` class
  (from before the grid rework) forcing `width: 25%` on itself, which
  shrank it to a quarter width inside its own grid cell while every
  other box filled its cell normally. Removed the stray class.

## [1.0.41] - Dashboard layout, take 3
- Site Statistics grid now uses a fixed 4 columns (12 boxes -> exactly
  3 even rows) instead of `auto-fill`, which produced an uneven number
  of boxes per row depending on container width. Responsive: 2 columns
  under 992px, 1 column under 560px.
- Found and fixed the real cause of "PDF Report Configuration" only
  using a narrow column instead of the full page width: its outer
  wrapper (`.jc-audit-btn`) had `display: inline-block`, which was
  presumably meant for just the button but ended up wrapping the whole
  form (heading, checkboxes, everything), shrinking it to fit its
  content instead of stretching to the full container width. Changed
  to `display: block`.

## [1.0.40] - Site Statistics grid fix
- The Site Statistics boxes used a flexbox layout, so a short last row
  (e.g. just "Languages" and "Current Template") had its items stretch
  to fill the full row width, making them look oversized/out of place
  compared to the uniform boxes above. Switched to CSS grid
  (`repeat(auto-fill, minmax(180px, 1fr))`), which keeps every box the
  same width and simply leaves empty space in a short last row instead
  of stretching items to fill it.

## [1.0.39] - Dashboard layout fix
- Fixed a broken `.jc-audit-wrapper` closing `</div>` lost during the
  1.0.38 section reorder: the PDF Report Configuration panel was ending
  up nested inside the statistics flex container in the DOM, so it
  rendered squeezed into the same row as the Languages/Current Template
  boxes instead of as its own section below them.

## [1.0.38] - Dashboard layout cleanup
- Reordered the Dashboard: quick-link cards, then Site Statistics, then
  PDF Report Configuration last (was: PDF config, then statistics).
- Restyled the Site Statistics boxes to match the quick-link cards: full
  display width (responsive flex wrap instead of a fixed ~20%/5-per-row
  grid), white background, subtle shadow, uppercase label heading -
  consistent look across the whole dashboard.
- Removed a leftover dead/commented-out CSS rule.

## [1.0.37] - Dashboard quick links
- Added two "quick link" cards at the top of the Dashboard: one summarizing
  pending extension updates (linking to Installed Extensions), one
  summarizing Security Check warnings/critical issues (linking to
  Security Check). Colour-coded (green/orange/red) so problems are visible
  at a glance without opening either sub-page.
- `Jc_systemreportHelper::getSecurityChecks()` now accepts an optional
  pre-fetched Joomla version, so the dashboard can reuse the single live
  version check for both the quick-link card and the existing "Next
  Joomla version" box instead of hitting the Joomla API twice per page
  load. Same for the extensions list used by both the new card and the
  existing "Extensions" stat box.

## [1.0.36] - Dashboard fixes
- Fixed the dashboard "Extensions" count box: it used the old broken
  filter (`type=component AND enabled=1 AND extension_id>245`), which
  under-counted compared to the Installed Extensions page. Now reuses
  the same shared helper, so the numbers always match.
- Fixed a bug in the "Include Joomla Core Extensions" checkbox on the
  dashboard: the AJAX call sent `include_core`, but the controller task
  read `exclude_core` - so re-checking the box never actually changed
  the count. `getExtensionCount()` now reads the correct parameter and
  reuses the shared helper as well.
- Removed the third, duplicate copy of the "Next Joomla version" live
  check from the dashboard template (cURL + old XML fallback inlined in
  the view) and replaced it with a call to the same
  `Jc_systemreportHelper::fetchLatestJoomlaVersion()` used by Security
  Check (made `public` for this). One implementation instead of three.
- Removed the `allow_url_fopen` warning banner at the top of the
  dashboard (besides having a garbled, unfinished message) since it no
  longer applies - the version-check helper uses cURL exclusively now,
  never `file_get_contents()`.

## [1.0.35] - Dashboard rename
- Renamed the first submenu entry from "JC System Report" to "Dashboard"
  (was previously duplicating the top-level component title). Toolbar page
  title updated to match.

## [1.0.12 - 1.0.34] - Summary of prior work
- Fixed TCPDF `startcolumn`/`startx`/`startpage` warnings and the
  "Some data has already been output" PDF failure (empty `<tr></tr>` in the
  summary table, `LanguageHelper::getInstalledLanguages()` called with an
  invalid client id, missing output-buffer cleanup before `Output()`).
- Fixed the "Installed Extensions" list only showing components
  (`extension_id > 245` filter) - replaced with `locked = 0` so all
  extension types are included.
- Added package/child grouping, a Type column highlighting packages,
  a State (Enabled/Disabled) column, and red highlighting for extensions
  with an available update, to both the PDF report and a new admin page.
- Added a new "Installed Extensions" admin page (own submenu entry) with
  a flat/grouped-by-type view, live text filter, CSV export, and a
  "Check for updates" action.
- Added an Author column and a "Hide official Joomla extensions" filter
  (extensions authored by "Joomla! Project"), independent of the
  core/locked filter.
- Added a fallback name "prettifier" for extensions whose language
  constant can't be translated (e.g. `plg_content_sigplus` -> "Content
  Sigplus").
- Added a new "Security Check" admin page: Joomla core version (live
  check against the official Joomla download servers, falling back to
  the local `#__updates` cache), PHP version vs. security-support EOL,
  database type/version vs. Joomla's minimum requirements,
  configuration.php permissions, debug mode, error reporting level,
  forced HTTPS, .htaccess presence, Super User account audit, pending
  extension updates, MFA plugin status, database prefix, and PHP
  extensions required by Fero's own custom extensions (GD, Imagick incl.
  SVG delegate, Exif, OpenSSL, PDO MySQL, cURL, Zip).
- Refactored the controller: PDF generation moved out into
  `Service/ReportPdfService.php` and `Pdf/JCSystemPDF.php`; shared
  extension-listing/security-check logic centralized in
  `Helper/Jc_systemreportHelper.php`.
- Added CSRF token checks to PDF generation, the "Check for updates" and
  CSV export actions (missing in the upstream version).
- Localized the new pages/sections (sk-SK + en-GB).
- Stripped unused TCPDF fonts, examples and tools from the package
  (~33 MB -> ~500 KB).

<p align="center">
  <img src="assets/logo.webp" alt="pkg_fgsystemreport logo" width="128" height="128">
</p>

<h1 align="center">FG System Report</h1>

<p align="center">
  <a href="https://github.com/FGcodework/pkg_fgsystemreport/releases"><img alt="Release" src="https://img.shields.io/github/v/release/FGcodework/pkg_fgsystemreport?color=ff6b4a"></a>
  <a href="https://www.joomla.org"><img alt="Joomla" src="https://img.shields.io/badge/Joomla-5.x%20%7C%206.x-1a3867?logo=joomla&logoColor=white"></a>
  <a href="https://www.php.net"><img alt="PHP" src="https://img.shields.io/badge/PHP-8.1%2B-purple?logo=php&logoColor=white"></a>
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/license-GPL--2.0+-green.svg"></a>
  <a href="/../../releases"><img src="https://img.shields.io/github/downloads/FGcodework/pkg_fgsystemreport/total?cacheSeconds=3600&color=brown" alt="Downloads"></a>
  <a href="https://ko-fi.com/FGcodework"><img alt="Ko-fi" src="https://img.shields.io/badge/Ko--fi-support-ff5e5b?logo=ko-fi&logoColor=white"></a>
</p>

Audit and monitoring toolkit for Joomla, part of the **FG** extension series. One admin component with a consistent UI (header with live security score, tab navigation, light and dark mode) in the style of [fgcodework.github.io](https://fgcodework.github.io/).

## Features

- **Dashboard** – site statistics (PHP and database versions coloured by support status, Joomla version vs. latest release), quick links with status, and a configurable PDF report (download or e-mail).
- **Installed Extensions** – every extension with its available update, quick filters (all / with update / third-party / disabled), package grouping, CSV export and a working "Go to update" link.
- **Security Check** – 35+ checks with a 0-100 site health score: Joomla/PHP/DB versions and end-of-life, `configuration.php` permissions, exposed sensitive files, SSL certificate expiry, HSTS, Super Users and failed logins, MFA, backups, mail DNS (SPF/DMARC/MX), overdue scheduled tasks, OPcache, PHP files in `/images`, leftover files in the web root, recent PHP fatal errors, update sites, orphaned extension records, known vulnerable extensions (VEL) and more. CSV/JSON export.
  - **Accepted risk** – a warning or critical finding you cannot or do not want to fix can be accepted with a note; it then counts as info everywhere (score, history, report, CLI, scheduled task) and can be revoked later (Super Users only).
  - The score is only a rough indicator of site state: it starts at 100, each critical finding subtracts 15 and each warning 5 (limited to 0-100). It is not a certified security methodology and findings differ greatly in real severity - always read the individual findings.
- **Security History** – every run is recorded; trend chart (30/90/365 days), run-to-run comparison and a **change timeline** (extension installs, updates, removals, enable/disable and key Global Configuration changes, recorded by the system plugin) so a drop in the score can be tied to what changed.
- **Report** – a printable health report (score, findings, trend, recent changes, errors, storage, extensions) for *Print / Save as PDF*; sections can be switched on and off.
- **Errors** – PHP fatal errors and warnings found in the server and Joomla logs.
- **Logs** – read-only tail of the Joomla log files (Super Users only).
- **Disk Usage** – the largest folders under the site root with growth tracking (adapted from `mod_jm_treesize`).
- **Database** – table sizes with history and growth alerts.
- **Icon Classes** – reference of the icon classes available in the admin template.
- **Scheduled notifications** – the companion task plugin runs the Security Check on a schedule, records it in the history and e-mails you only when something got worse. Disk and database growth alerts reuse the same channel (e-mail and/or a Joomla message).
- **Command line** – `fgsystemreport:check` for cron/CI, and a quick icon on the administrator Home page showing the latest score.
- Languages: English, Slovak (sk-SK), German (de-DE).

## Installation

Download **`pkg_fgsystemreport_vX.X.X.zip`** from the [latest release](https://github.com/FGcodework/pkg_fgsystemreport/releases/latest) and install it in *System → Install → Extensions*. The package contains:

| Extension | Purpose |
|---|---|
| `com_fgsystemreport` | the component |
| `plg_task_fgsystemreport` | Task Scheduler plugin (security notifications, history, growth alerts) |
| `plg_console_fgsystemreport` | CLI command `fgsystemreport:check` |
| `plg_quickicon_fgsystemreport` | quick icon with the latest score on the administrator Home page |
| `plg_system_fgsystemreport` | records the change timeline (does nothing on ordinary page requests) |

The plugins are enabled automatically on install (and on the first update that brings them); you can disable any of them later. Updates arrive through Joomla's normal *System → Update → Extensions* (update server: `updates.xml` in this repository).

After installing, create a task *"FG System Report: Security Check Notification"* under *System → Scheduled Tasks* (e.g. daily) to get history and notifications. Without a working cron, run the Security Check from the admin or from the command line.

Requires Joomla 5.x or 6.x and PHP 8.1+. Developed and tested on MySQL/MariaDB; PostgreSQL is only partly supported (the Database diagnostics page is MySQL/MariaDB only, the rest has not been tested end to end). Nothing needs to be configured for the checks themselves; notification recipients are set on the scheduled task (default: all active Super Users).

### Command line / cron

```
php cli/joomla.php fgsystemreport:check
php cli/joomla.php fgsystemreport:check --format=json --fail-on=warning
```

Exit codes: `0` OK, `1` warnings (with `--fail-on=warning`), `2` critical findings, `3` check could not run. Use `--url=https://example.com` if `live_site` is not set (needed for the HTTP-based checks), `--no-record` to skip the Security History.

### Uninstalling

Uninstalling the package removes the component's tables (history, disk and database snapshots, alerts, change timeline, accepted risks).

## Repository layout

```
com_fgsystemreport/       component (source of com_fgsystemreport_vX.X.X.zip), incl. CHANGELOG.md
                          (administrator/assets/css/fg-ui.css = the scoped UI stylesheet)
plg_task_fgsystemreport/  task plugin
plg_console_fgsystemreport/  console plugin (fgsystemreport:check)
plg_quickicon_fgsystemreport/  admin Home quick icon plugin
plg_system_fgsystemreport/  system plugin (change timeline)
pkg_fgsystemreport/       package manifest + install script
updates.xml               update server (generated by build.sh)
build.sh                  builds dist/*.zip and regenerates updates.xml
.github/workflows/        release workflow (tag v* → GitHub release with the ZIPs)
```

## Releasing

1. Bump `<version>` in `com_fgsystemreport/fgsystemreport.xml` **and** `pkg_fgsystemreport/pkg_fgsystemreport.xml` (the package version follows the component), update the inner ZIP names in the package manifest, and add an entry to `com_fgsystemreport/CHANGELOG.md`. If a plugin changed, bump its own manifest (`plg_*_fgsystemreport/fgsystemreport.xml`) and its ZIP name in the package manifest.
2. Run `bash build.sh` – builds `dist/` and regenerates `updates.xml`.
3. Commit, then `git tag vX.X.X && git push --tags`. The workflow checks that tag, package version and `updates.xml` agree and publishes the release.

## Credits

Originally forked from *JC System Report* 1.0.12 by Abdulgani ([joomconsultant.com](https://www.joomconsultant.com/)); extensively rewritten and rebranded by Fero. The Disk Usage page is adapted from `mod_jm_treesize` (Maarten Blokdijk, jmodules.com). PDF output uses [TCPDF](https://tcpdf.org) (LGPL) with a subset of DejaVu Sans (Bitstream Vera licence).

## Support

If you find it useful you can support the FG extensions voluntarily on [Ko-fi](https://ko-fi.com/FGcodework).

## License

GNU General Public License v2 or later – see [LICENSE](LICENSE).

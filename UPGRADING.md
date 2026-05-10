# Upgrading SourceBans++

This file documents behaviours that change at major version boundaries
and that an admin upgrading an existing install should know about
**before** they run the panel updater. Routine bug-fix-only releases
land on the [`CHANGELOG.md`](CHANGELOG.md) instead.

Future entries will land here as the project ships major upgrades; this
section currently covers the v2.0.0 upgrade path.

## Replace `web/configs/version.json` (#1305, v2.0.0)

**Overlay your v2.0.0 tarball over an existing v1.x install with a
tool that overwrites every file.** v1.x repos shipped a checked-in,
hand-edited `web/configs/version.json` (`{"version": "1.8.1", "git":
"1434"}`); v2.0.0 ships a fresh `version.json` written by the release
pipeline at build time. The two files live at the same path, so an
overlay tool that PRESERVES existing files (FTP "skip if exists",
`rsync` without `--delete`, a manual directory-by-directory copy that
treats `configs/` as user data alongside `configs/permissions/*.json`)
keeps the stale v1.x file on disk.

If that happens on v2.0.0 the panel footer reads
`SourceBans++ 1.8.1 | Git: 1434` instead of the actual installed
version. v2.0.0 ships a structural guard in `Sbpp\Version::resolve()`
(#1305) that REJECTS a tier-1 `version.json` whose major component is
below v2.0.0, so the failure mode is now "footer reads `dev` or the
live `git describe` string" instead of "footer reads phantom v1.x
copy". You still want the file to be the v2.0.0 release pipeline's
fresh write so the footer reads `2.0.0` exactly.

**The fix on an already-upgraded install**: re-extract the v2.0.0
tarball over your install with a tool that overwrites every file
(`unzip -o` instead of `unzip`, `rsync -av --delete` instead of plain
`rsync -av`, a manual `cp` that overwrites). `web/configs/version.json`
is the file at issue; `web/configs/permissions/web.json` and
`web/configs/permissions/sourcemod.json` are the only files in
`web/configs/` that an operator might have hand-edited and want to
preserve, so back those up first if you've customised them.

This file is owned by the release pipeline, not by the operator;
v2.0.0+ tarballs always carry it and v2.0.0+ dev clones gitignore it
(the runtime falls through to `git describe`). There is no in-panel
toggle for the resolved version — it's read once per request from
`web/configs/version.json` (and the two fallback tiers).

## Telemetry (#1126, v2.0.0)

**SourceBans++ 2.0.0 ships with default-on anonymous telemetry.**
This is new behaviour for installs upgrading from 1.x. Read this
section before the upgrade.

### What's collected

Once per day per install, the panel sends an anonymous JSON payload to
a SourceBans++ Cloudflare Worker. The payload covers four categories:

- **panel** — version, short git SHA, dev flag, theme name (`default`
  or `custom` only — fork theme names are never reported).
- **env** — PHP `major.minor`, DB engine + `major.minor`, web server
  family (`apache`/`nginx`/…/`other`), OS family
  (`linux`/`windows`/`mac`/`bsd`/`other`).
- **scale** — counts of admins, enabled servers, active and total
  bans / comms, and 30-day submissions / protests.
- **features** — every checkbox under Admin → Settings → Features
  (kickit, group banning, friends banning, admin rehashing, public
  exports, public comments, Steam / normal login), plus three
  derived booleans: SMTP-configured, Steam API key set, GeoIP DB
  present.

A random 32-char hex `instance_id` is included so pings can be
deduplicated. **No** hostnames, IPs, install paths, admin names,
SteamIDs, ban reasons, dashboard text, server hostnames, server IPs,
MOTDs, SMTP credentials, or the Steam API key value are ever sent.

The complete field list, the SQL behind each `scale.*` count, and the
schema-evolution policy live in
[`README.md` → `## Privacy & telemetry`](README.md#privacy--telemetry).

### Why default-on

Opt-in telemetry returns ~1% of installs and is statistically useless.
Every roadmap decision (drop PHP 8.5? raise the MariaDB floor?
deprecate `enablefriendsbanning`?) is currently made blind. The
trade-off is to ship default-on with a loud, easy, obvious opt-out
path — this section, the README's Privacy & telemetry section, the
in-panel help-icon copy next to the toggle, and the `## Privacy`
heading in the 2.0.0 [`CHANGELOG.md`](CHANGELOG.md) are the load-bearing
disclosure surfaces. There is no first-login modal — the docs and the
help-icon together are the disclosure contract.

### How to opt out

After the upgrade:

1. Sign in as an admin with `ADMIN_OWNER` or `ADMIN_WEB_SETTINGS`.
2. Navigate to **Admin → Settings → Features**.
3. Uncheck **Anonymous telemetry** under the **Privacy** card.
4. Click **Save changes**.

Opt-out also clears the per-install random ID. Re-enabling later
issues a fresh ID so the Worker can never link the two states.

### How to redirect to a self-hosted collector

The endpoint URL lives in `:prefix_settings.telemetry.endpoint` (default
`https://cf-analytics-telemetry.sbpp.workers.dev/v1/ping`). To point
the panel at your own collector:

```sql
UPDATE sb_settings
SET value = 'https://your-collector.example.com/v1/ping'
WHERE setting = 'telemetry.endpoint';
```

Setting the value to the empty string disables network calls without
flipping the user-facing toggle. There is no UI for this knob; it's a
debug / escape-hatch surface.

The Worker source lives at
[sbpp/cf-analytics](https://github.com/sbpp/cf-analytics) for operators
who want to inspect or fork the collector.

### Performance impact

The telemetry tick runs in a `register_shutdown_function` callback. On
PHP-FPM, `fastcgi_finish_request()` closes the user's TCP socket
**before** the cURL POST runs, so a panel response time of N ms
remains N ms regardless of telemetry. cURL has 3-second connect /
5-second total timeouts and a flapping endpoint is silent — telemetry
can never hard-fail a panel page or a JSON API request.

# Upgrading SourceBans++

This file documents behaviours that change at major version boundaries
and that an admin upgrading an existing install should know about
**before** they run the panel updater. Routine bug-fix-only releases
land on the [`CHANGELOG.md`](CHANGELOG.md) instead.

Future entries will land here as the project ships major upgrades; this
section currently covers the v2.0.0 upgrade path.

## PHP dependencies (#1307, v2.0.0)

**v2.0.0 introduces new PHP dependencies and bumps the major versions
of others.** A v1.x install with a `vendor/` directory left over from a
prior `composer install` will pass `web/init.php`'s autoload check but
fatal at runtime the first time v2.0 code references a class the old
`vendor/` doesn't ship — usually mid-way through a page render, with
the actual `Class "…" not found` line buried in your PHP error log.

Run `composer install` from the panel root **before** you visit
`web/updater/index.php`:

```sh
cd /path/to/sourcebans/web
composer install --no-dev --optimize-autoloader
```

The new / bumped dependencies in v2.0.0:

- `symfony/mailer ^7.2` — replaced the v1.x `PHPMailer` dependency.
- `league/commonmark ^2.5` — added at #1113 to safely render
  admin-authored Markdown for the dashboard intro text.
- `lcobucci/jwt ^5.0` — major-bumped from v1.x's `^4`.
- `smarty/smarty ^v5.4` — major-bumped from v1.x's `^v3`.
- `php >= 8.5` — raised at #1289 from v1.x's `>=7.4`.

### Why the panel doesn't catch this for you

`web/init.php` only checks that `vendor/autoload.php` *exists*; it
can't tell whether the autoloader's contents match what v2.0 expects
without paying an eager class-resolve cost on every request. Release
**tarballs** ship a fresh `web/includes/vendor/` so tarball upgrades
work out of the box. **Git-based upgrades** (`git pull`, `git
checkout`, drop a checkout over an existing tree) inherit the old
`vendor/` and need the explicit `composer install` above.

A panel that fatals after you ran the updater isn't permanently
broken — `composer install` from the panel root will get you back to
a working state. But the panel renders 500s in the meantime, so it's
worth running the install step **first**.

## Theme compatibility (#1307, v2.0.0)

**v2.0.0 ships a complete chrome rewrite (#1123 / #1207 / #1259 /
#1275)** — new typed View DTOs (`Sbpp\View\*`), new admin sidebar
partial (`core/admin_sidebar.tpl`), every template signature changed,
MooTools / `xajax` / `sb-callback.php` removed, the `openTab()` JS
helper deleted. A fork theme inherited from a v1.x install does not
contain the templates v2.0 expects to render — best case the operator
gets `Smarty: Unable to load template …` fatals, worst case Smarty
falls through to a half-rendered page where every template variable
is undefined.

To keep the panel actually loadable after the upgrade, the v2.0
updater (migration `808.php`) **resets `config.theme` to `default`**.
This happens automatically when you run `web/updater/index.php`. No
operator action is required.

If you maintain a fork theme:

1. The panel will be on `default` immediately after the upgrade.
2. Port your fork against the v2.0 default theme as your reference
   (diff `web/themes/default/` between the v1.x and v2.0 trees to see
   the surface area). The new conventions live in
   [`AGENTS.md`](AGENTS.md) — most relevant: typed View DTOs, the
   `?section=…` admin sub-route pattern, the `core/admin_sidebar.tpl`
   partial, and the empty-state shapes in `web/themes/default/css/theme.css`.
3. Once your fork ships v2.0-shaped templates, re-select it from
   **Admin → Settings → Themes**. The DB-side reset is a one-shot at
   upgrade time; nothing in the panel will switch you back to
   `default` again.

The pre-existing fork theme directory under `web/themes/<fork>/` is
left in place — only the `:prefix_settings.config.theme` row is
rewritten. So your work isn't lost; the panel just stops trying to
render against templates that don't exist.

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

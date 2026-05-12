---
title: Panel won't load
description: The panel hangs, shows a blank page, or doesn't respond — checklist for the usual causes.
sidebar:
  order: 1
---

If the panel won't paint — blank tab, spinning forever, "this site
can't be reached", or a half-rendered page that never finishes — the
cause is almost always one of a small handful of things. This page
walks through the checks in order from easiest to most involved.

## 1. Server-level errors (blank white page)

A completely blank page usually means **PHP encountered a fatal error
and stopped emitting output** before any of the panel chrome painted.

The error itself goes to your webserver's PHP error log. Typical
locations:

- Apache (Debian / Ubuntu): `/var/log/apache2/error.log`
- Apache (Fedora / RHEL): `/var/log/httpd/error_log`
- Nginx + PHP-FPM: `/var/log/php<version>-fpm.log` plus the FPM pool
  log
- Docker / our dev stack: `./sbpp.sh logs web`

Open the most recent entry. It'll be specific — typically a missing
PHP extension, a syntax error in `config.php`, or a memory-limit hit
on a heavy page.

If you can't find the log file, set `display_errors = On` in `php.ini`
**temporarily**, reload the page, and read the error in the browser.
Set it back to `Off` immediately after — leaving error display on in
production leaks internal paths and stack traces.

## 2. Database connection problems

If the panel loads partway but throws a database error, see
[Database errors](/troubleshooting/database-errors/).

If PHP loads but reports "could not find driver", see
[Driver not found](/troubleshooting/could-not-find-driver/).

## 3. The page paints but JavaScript doesn't run

If the panel renders content (you see the layout, sidebar, and so
on) but interactive elements don't respond — buttons don't click,
modals don't open, the command palette doesn't appear — JavaScript
is failing to execute somewhere. Common causes:

### A reverse proxy is rewriting the JavaScript

Cloudflare's **Rocket Loader** is the most common offender. It
re-orders JavaScript loads to speed up first paint, but the panel's
inline boot scripts depend on `theme.js` having executed first, so
re-ordering them breaks the chrome.

If your panel sits behind Cloudflare:

1. Sign into the Cloudflare dashboard.
2. Pick the zone the panel is on.
3. Go to **Speed → Optimization → Content optimization**.
4. **Disable Rocket Loader** for the panel's hostname.

If you share a Cloudflare account with other services, you can scope
this to the panel only via a **Page Rule** instead of disabling it
zone-wide.

Other reverse-proxy "auto-optimization" features that have caused
similar issues in the past: Cloudflare Auto Minify (since
discontinued), Cloudflare Mirage, third-party CDN minifiers. If you
recently added any of these, try disabling them first.

### A browser extension is blocking scripts

Aggressive ad/script blockers (uBlock Origin with custom rules,
NoScript, Privacy Badger) can block the panel's own scripts. Open
the page in a private window with extensions disabled — if it works
there, your extension is the cause.

### The browser cache is stale after an upgrade

Bumping a major SourceBans++ version replaces `theme.js`, but your
browser may still be holding the old one. Force-reload the page:

- **Windows / Linux:** `Ctrl + F5` or `Ctrl + Shift + R`
- **macOS:** `Cmd + Shift + R`

## 4. The browser hangs / tab freezes

If opening a panel page locks the entire tab (no scroll, no
interaction, eventually a browser "this page is slow" prompt), the
JavaScript is in an infinite loop or pathological state.

This is rare on a current panel but worth diagnosing:

1. Open the browser's developer tools (`F12`).
2. Switch to the **Performance** tab.
3. Click record.
4. Reload the page.
5. After about 10 seconds, stop the recording.
6. Click **Save profile** (Firefox) or **Save** (Chrome).

Then drop into our [Discord](https://discord.gg/tzqYqmAtF5)
`#help-support` channel and share the trace — it'll tell us
whether the hang is in our JS, an upstream library, or your
browser environment.

## Still stuck?

If none of the above match what you're seeing, post in
`#help-support` with:

- The URL you're trying to load.
- A screenshot of what you actually see (blank page, error, etc.).
- The PHP error log entry from around the time you reproduced it.
- Whether you're behind a reverse proxy (Cloudflare, Nginx,
  Apache reverse-proxying to PHP-FPM, etc.).
- Your PHP version and SourceBans++ version.

We'll triage from there.

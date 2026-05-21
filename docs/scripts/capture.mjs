#!/usr/bin/env node
// @ts-check
//
// docs/scripts/capture.mjs — Playwright-driven screenshot grabber for
// the SourceBans++ install wizard and the post-install panel.
//
// Output lives under docs/src/assets/auto/{install,panel}/ and is
// committed alongside the PR that changed the UI (see
// .github/workflows/docs-screenshots-capture.yml). Stable filenames
// mean page references in the docs don't have to change when a
// screenshot regenerates — the docs site picks up the new bytes
// automatically.
//
// Prerequisites:
//
//   1. The dev stack is running and the panel is reachable at
//      $PANEL_URL (defaults to http://localhost:8080). Locally:
//
//          ./sbpp.sh up
//
//      and wait for the seed (admin/admin) to land. CI runs the
//      same `docker compose up -d --wait` sequence in
//      docs-screenshots-capture.yml.
//
//   2. The install wizard at /install/ is reachable. The wizard's
//      #1335 C2 guard refuses to start over an installed panel —
//      it short-circuits to a static 409 "already installed" page
//      whenever `web/config.php` exists. The dev stack's entrypoint
//      always creates `config.php` on first boot, so the install
//      captures only paint real wizard surfaces when `config.php`
//      is moved aside for the duration of the install captures.
//      CI does this stash/restore dance in
//      `docs-screenshots-capture.yml` around the
//      `CAPTURE_ROUTES=install` invocation; locally the `install`
//      arm of `CAPTURE_ROUTES` will silently render the 409 page
//      until the operator moves `web/config.php` aside manually.
//
//   3. STEAM_API_KEY is set to the all-zero dummy
//      (00000000000000000000000000000000) per #1333 §7. The dev seed
//      never round-trips back to Steam, so the zero key is safe.
//
// Usage:
//
//      cd docs && npm run capture
//      cd docs && PANEL_URL=http://localhost:8189 npm run capture
//      cd docs && CAPTURE_ROUTES=panel npm run capture     # panel only
//      cd docs && CAPTURE_ROUTES=install npm run capture   # wizard only
//
// `CAPTURE_ROUTES` selects which route groups to capture:
//   - `all` (default) — every route group below
//   - `panel`         — panel-01-login (anonymous) + the
//                       authenticated panel surfaces
//   - `install`       — the install wizard surfaces (steps 1, 2, 5).
//                       Requires `web/config.php` to be absent so
//                       the C2 guard doesn't intercept.
//
// Output shape:
//   - Panel routes are captured TWICE — once with the panel's light
//     theme active and once with dark — and emit per-theme PNGs
//     (`panel-02-dashboard-light.png`, `panel-02-dashboard-dark.png`,
//     etc.). The dark variant seeds `localStorage['sbpp-theme']`
//     before the page paints so the inline FOUC bootloader in
//     `core/header.tpl` lands the `dark` class on `<html>` on the
//     very first frame (no white flash). The browser context's
//     `colorScheme` is matched to the panel theme so native UA
//     surfaces (scrollbars, native pickers, autofill highlighting)
//     paint in the matching scheme too — see theme.css's
//     `color-scheme` declarations (#1309).
//   - Install routes are captured ONCE in the :root light default.
//     The wizard's `_chrome.tpl` doesn't load `theme.js` or the
//     FOUC bootloader (the wizard has no theme toggle by design;
//     see AGENTS.md "Install wizard"), so a "dark install" capture
//     would just be the same light render with a different filename.
//
// Default capture geometry is 1920×1080 viewport with `fullPage: true`
// so the entire scrollable surface lands in the PNG (the docs site
// renders these at responsive widths, but the source PNG carries the
// full layout for high-DPI / zoomed-in inspection).
//
// The script is intentionally a runnable SKELETON for the first PR.
// The route list below is the bones; flesh out per-route selectors
// + click sequences as the install / panel chrome iterates. Routes
// with `TODO:` notes are deferred to follow-up PRs that exercise
// the actual flow end-to-end.

import { chromium } from '@playwright/test';
import { mkdir, rm } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_DOCS = dirname(__dirname);
// Default output target is this checkout's docs/src/assets/auto/. CI uses
// CAPTURE_OUT_OVERRIDE to redirect into the PR-head working tree while
// running the trusted capture script from main (security split, see
// .github/workflows/docs-screenshots-capture.yml). When set, the value is
// the directory that REPLACES `<docs>/src/assets/auto/` — install/ and
// panel/ subdirectories are appended.
const OUT_BASE =
  process.env.CAPTURE_OUT_OVERRIDE ?? join(REPO_DOCS, 'src', 'assets', 'auto');
const OUT_INSTALL = join(OUT_BASE, 'install');
const OUT_PANEL = join(OUT_BASE, 'panel');

const PANEL_URL = process.env.PANEL_URL ?? 'http://localhost:8080';
const STEAM_API_KEY =
  process.env.STEAM_API_KEY ?? '00000000000000000000000000000000';
const ADMIN_USER = process.env.PANEL_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.PANEL_ADMIN_PASS ?? 'admin';
// Pin the viewport to Full HD (1920×1080) so screenshots line up
// across runs even when the runner's chromium gets a different
// default size. Combined with `fullPage: true` on the screenshot
// call, the rendered PNG carries the full scrollable surface at
// the largest viewport the panel chrome's responsive breakpoints
// target — wide enough to surface tier-3 desktop-table columns
// (see AGENTS.md "Responsive desktop-table chrome") and the full
// sidebar + content shell on the admin routes.
const VIEWPORT = { width: 1920, height: 1080 };

// Panel themes to capture. Each panel route runs once per theme in
// its own browser context; the dark pass seeds
// `localStorage['sbpp-theme']` via `addInitScript` so the FOUC
// bootloader in `core/header.tpl` resolves to dark BEFORE the
// document body parses (the bootloader is the synchronous read; see
// AGENTS.md "Anti-FOUC theme bootloader" for the contract). The
// install wizard chrome doesn't carry the bootloader, so install
// routes are captured light-only — see `main()` below.
/** @type {readonly ('light' | 'dark')[]} */
const PANEL_THEMES = ['light', 'dark'];

// CAPTURE_ROUTES picks which route groups run. CI invokes us twice
// (once per group) with config.php stashed around the install pass
// so the wizard's #1335 C2 guard doesn't intercept; local dev
// defaults to `all` and the install routes will silently render the
// "already installed" 409 page until the operator stashes
// `web/config.php` themselves.
const CAPTURE_ROUTES_RAW = (process.env.CAPTURE_ROUTES ?? 'all').toLowerCase();
/** @type {'all' | 'panel' | 'install'} */
const CAPTURE_ROUTES =
  CAPTURE_ROUTES_RAW === 'panel' || CAPTURE_ROUTES_RAW === 'install'
    ? CAPTURE_ROUTES_RAW
    : 'all';

/**
 * @typedef {Object} CaptureRoute
 * @property {string} name        Stable filename slug — survives between runs.
 * @property {string} url         Path appended to PANEL_URL (or full URL).
 * @property {string} [waitFor]   Selector that must be visible before snapping.
 * @property {string} [outDir]    Override OUT_PANEL / OUT_INSTALL routing.
 * @property {boolean} [fullPage] Take a full-page shot instead of the viewport.
 * @property {string} [todo]      Note for future work; route still runs.
 */

// Login route is captured from an anonymous context BEFORE the
// authenticated routes are visited. Reason: `page.login.php` emits
// `<script>window.location.href = 'index.php';</script>` whenever a
// session already exists, and that inline navigation aborts the
// Playwright `page.goto()` for `/index.php?p=login` with
// `net::ERR_ABORTED`. Capturing login from a fresh (cookie-less)
// context sidesteps the redirect entirely.
/** @type {CaptureRoute[]} */
const PANEL_PUBLIC_ROUTES = [
  {
    name: 'panel-01-login',
    url: '/index.php?p=login',
    waitFor: 'form',
  },
];

/** @type {CaptureRoute[]} */
const PANEL_AUTH_ROUTES = [
  {
    name: 'panel-02-dashboard',
    url: '/index.php?p=home',
    waitFor: 'main',
  },
  {
    name: 'panel-03-banlist',
    url: '/index.php?p=banlist',
    waitFor: 'main',
  },
  {
    name: 'panel-04-servers',
    url: '/index.php?p=servers',
    waitFor: 'main',
  },
  {
    name: 'panel-05-admin-dashboard',
    url: '/index.php?p=admin',
    waitFor: 'main',
  },
];

// Install routes pruned to the URLs the URL-only approach actually reaches
// cold. Steps 3-6 of the wizard are POST-handoff-gated (each step
// re-validates the prior step's prefix input and bounces back to step 2 if
// the operator deep-links in), so they need a script that drives the form
// chain end-to-end. Tracked as a follow-up to issue #1333; until that
// lands, the install gallery is just the licence + DB-details + admin-
// create paint (the three step-1 reachable surfaces).
/** @type {CaptureRoute[]} */
const INSTALL_ROUTES = [
  {
    name: 'install-01-licence',
    url: '/install/?step=1',
    waitFor: 'form',
  },
  {
    name: 'install-02-database-details',
    url: '/install/?step=2',
    waitFor: 'form',
  },
  {
    name: 'install-05-admin-create',
    url: '/install/?step=5',
    waitFor: 'form',
    todo:
      'Form pre-populates with whatever step 4 wrote to config.php; deep-link works once steps 2-4 have committed.',
  },
];

// Reset the output directories for the route groups we're about to
// capture so a previous run's stale PNGs (e.g. last-run filenames
// that don't match the current route list, or pre-dual-theme legacy
// `panel-02-dashboard.png` from before per-theme suffixes landed)
// don't linger in the committed diff. We only nuke the subdir for
// the group actually being captured so a `CAPTURE_ROUTES=panel`
// invocation doesn't wipe a previous `install` capture (or vice
// versa). `force: true` makes the missing-directory case idempotent.
async function ensureOutDirs() {
  if (CAPTURE_ROUTES === 'all' || CAPTURE_ROUTES === 'install') {
    await rm(OUT_INSTALL, { recursive: true, force: true });
    await mkdir(OUT_INSTALL, { recursive: true });
  }
  if (CAPTURE_ROUTES === 'all' || CAPTURE_ROUTES === 'panel') {
    await rm(OUT_PANEL, { recursive: true, force: true });
    await mkdir(OUT_PANEL, { recursive: true });
  }
}

/**
 * Drive the panel's login form so we have an authenticated session
 * before snapping the admin-only routes. Falls through to anonymous
 * navigation if the form selector isn't present (e.g. the panel
 * is in a logged-in-from-cookie state).
 *
 * @param {import('@playwright/test').Page} page
 */
async function loginAsAdmin(page) {
  await page.goto(`${PANEL_URL}/index.php?p=login`, {
    waitUntil: 'networkidle',
  });
  const userField = page.locator('input[name="user"], input[name="username"]');
  if ((await userField.count()) === 0) {
    return;
  }
  await userField.first().fill(ADMIN_USER);
  await page
    .locator('input[name="password"], input[name="pass"]')
    .first()
    .fill(ADMIN_PASS);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);
}

/**
 * @param {import('@playwright/test').Browser} browser
 * @param {CaptureRoute[]} routes
 * @param {string} outDir
 * @param {{ login?: boolean; theme?: 'light' | 'dark' }} [opts]
 */
async function captureRoutes(browser, routes, outDir, opts = {}) {
  /** @type {import('@playwright/test').BrowserContextOptions} */
  const contextOptions = { viewport: VIEWPORT };
  if (opts.theme) {
    // Pair the OS-level scheme with the panel-side theme so native
    // UA surfaces (scrollbars, native pickers, autofill highlighting)
    // paint in the matching scheme too — mirrors the `color-scheme`
    // declarations on `:root` / `html.dark` in theme.css (#1309).
    // The bootloader is the load-bearing path for the panel chrome
    // itself; this is belt-and-suspenders for the surfaces the
    // browser draws on top of our DOM.
    contextOptions.colorScheme = opts.theme;
  }
  const ctx = await browser.newContext(contextOptions);

  if (opts.theme) {
    // Seed `localStorage['sbpp-theme']` BEFORE any page navigation
    // so the inline FOUC bootloader in `core/header.tpl` (which runs
    // synchronously in `<head>` BEFORE `<body>` parses, ABOVE the
    // stylesheet link) reads the value and sets `<html class="dark">`
    // on the very first paint. Without the seed, the panel would
    // start in :root light defaults and theme.js (loaded from the
    // document tail via `core/footer.tpl`) would flip the class
    // mid-paint, producing the white-flash regression #1367 fixed.
    // Playwright's `addInitScript` runs after the document is
    // created but before any of its own scripts execute, which is
    // exactly the slot we need.
    await ctx.addInitScript((target) => {
      try {
        localStorage.setItem('sbpp-theme', target);
      } catch (_err) {
        // Private-mode SecurityError; the bootloader's own try/catch
        // falls through to the :root light default in that case,
        // matching theme.js's defensiveness.
      }
    }, opts.theme);
  }

  const page = await ctx.newPage();

  if (opts.login) {
    try {
      await loginAsAdmin(page);
    } catch (err) {
      console.warn(
        `[capture] login attempt failed; admin-only routes may screenshot the login wall: ${err}`,
      );
    }
  }

  for (const route of routes) {
    const url = route.url.startsWith('http')
      ? route.url
      : `${PANEL_URL}${route.url}`;
    // Per-theme suffix on the filename so the light + dark variants
    // co-exist in the same output directory. Install routes pass no
    // theme and keep the bare slug.
    const slug = opts.theme ? `${route.name}-${opts.theme}` : route.name;
    const target = join(outDir, `${slug}.png`);

    try {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 15_000 });
      if (route.waitFor) {
        await page.locator(route.waitFor).first().waitFor({ timeout: 10_000 });
      }
      await page.screenshot({
        path: target,
        // Default to `fullPage: true` so the whole scrollable
        // surface lands in the PNG; routes can still opt out
        // explicitly via `fullPage: false` in their config (e.g.
        // a chrome-only shot of the login form).
        fullPage: route.fullPage ?? true,
      });
      const note = route.todo ? `  (TODO: ${route.todo})` : '';
      console.log(`[capture] wrote ${target}${note}`);
    } catch (err) {
      console.warn(`[capture] FAILED ${url}: ${err}`);
    }
  }

  await ctx.close();
}

async function main() {
  await ensureOutDirs();

  console.log(`[capture] PANEL_URL=${PANEL_URL}`);
  console.log(`[capture] STEAM_API_KEY=${STEAM_API_KEY}`);
  console.log(`[capture] CAPTURE_ROUTES=${CAPTURE_ROUTES}`);
  console.log(`[capture] VIEWPORT=${VIEWPORT.width}x${VIEWPORT.height}`);
  console.log(`[capture] PANEL_THEMES=${PANEL_THEMES.join(',')}`);
  console.log(
    `[capture] writing → ${OUT_INSTALL} (install) and ${OUT_PANEL} (panel)`,
  );

  const browser = await chromium.launch();
  try {
    if (CAPTURE_ROUTES === 'all' || CAPTURE_ROUTES === 'install') {
      // Install captures only paint real wizard surfaces when
      // `web/config.php` is absent — see the header comment for
      // the C2-guard rationale. CI stashes the file around this
      // call; local-dev defaults render the 409 page.
      //
      // The install chrome (`_chrome.tpl`) doesn't load `theme.js`
      // or the FOUC bootloader (the wizard has no theme toggle by
      // design — see AGENTS.md "Install wizard"). Capture once in
      // the :root light default with no per-theme suffix; trying
      // to flip the wizard to dark would just produce the same
      // light render under a different filename.
      await captureRoutes(browser, INSTALL_ROUTES, OUT_INSTALL);
    }
    if (CAPTURE_ROUTES === 'all' || CAPTURE_ROUTES === 'panel') {
      // Panel routes capture both light and dark variants. Each
      // theme runs in its own browser context so the localStorage
      // seeding is clean and the cookie/login state is rebuilt
      // per pass (logging in twice costs ~200ms total — cheap).
      for (const theme of PANEL_THEMES) {
        // Anonymous pass — captures the login screen before any
        // session cookie exists. Splitting this from the auth
        // pass avoids the
        // `<script>window.location.href='index.php'</script>`
        // post-login redirect aborting the goto.
        await captureRoutes(browser, PANEL_PUBLIC_ROUTES, OUT_PANEL, {
          theme,
        });
        // Authenticated pass — fresh context, drives the login
        // form, then visits the admin-only routes so they paint
        // actual content instead of the login wall.
        await captureRoutes(browser, PANEL_AUTH_ROUTES, OUT_PANEL, {
          login: true,
          theme,
        });
      }
    }
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error('[capture] fatal:', err);
  process.exit(1);
});

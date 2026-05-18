#!/usr/bin/env node
// @ts-check
//
// docs/scripts/capture.mjs — Playwright-driven screenshot grabber for
// the SourceBans++ install wizard and the post-install panel.
//
// Output lives under docs/src/assets/auto/{install,panel}/ and is
// committed alongside the PR that changed the UI (see
// .github/workflows/docs-screenshots.yml). Stable filenames mean
// page references in the docs don't have to change when a screenshot
// regenerates — the docs site picks up the new bytes automatically.
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
//      docs-screenshots.yml.
//
//   2. The wizard at /install/ is reachable. Locally the install
//      directory is wiped after seed, so the install captures only
//      run when /install/ exists. The dev stack rebuilds it on
//      `./sbpp.sh reset`; CI's `docker compose up` always starts
//      from a fresh DB so /install/ is fresh on first paint.
//
//   3. STEAM_API_KEY is set to the all-zero dummy
//      (00000000000000000000000000000000) per #1333 §7. The dev seed
//      never round-trips back to Steam, so the zero key is safe.
//
// Usage:
//
//      cd docs && npm run capture
//      cd docs && PANEL_URL=http://localhost:8189 npm run capture
//
// The script is intentionally a runnable SKELETON for the first PR.
// The route list below is the bones; flesh out per-route selectors
// + click sequences as the install / panel chrome iterates. Routes
// with `TODO:` notes are deferred to follow-up PRs that exercise
// the actual flow end-to-end.

import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
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
// Crop window chrome to a consistent viewport so screenshots line up
// across runs even when the runner's chromium gets a different
// default size.
const VIEWPORT = { width: 1280, height: 800 };

/**
 * @typedef {Object} CaptureRoute
 * @property {string} name        Stable filename slug — survives between runs.
 * @property {string} url         Path appended to PANEL_URL (or full URL).
 * @property {string} [waitFor]   Selector that must be visible before snapping.
 * @property {string} [outDir]    Override OUT_PANEL / OUT_INSTALL routing.
 * @property {boolean} [fullPage] Take a full-page shot instead of the viewport.
 * @property {string} [todo]      Note for future work; route still runs.
 */

/** @type {CaptureRoute[]} */
const PANEL_ROUTES = [
  {
    name: 'panel-01-login',
    url: '/index.php?p=login',
    waitFor: 'form',
  },
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

async function ensureOutDirs() {
  await mkdir(OUT_INSTALL, { recursive: true });
  await mkdir(OUT_PANEL, { recursive: true });
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
 * @param {{ login?: boolean }} [opts]
 */
async function captureRoutes(browser, routes, outDir, opts = {}) {
  const ctx = await browser.newContext({ viewport: VIEWPORT });
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
    const target = join(outDir, `${route.name}.png`);

    try {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 15_000 });
      if (route.waitFor) {
        await page.locator(route.waitFor).first().waitFor({ timeout: 10_000 });
      }
      await page.screenshot({
        path: target,
        fullPage: route.fullPage ?? false,
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
  console.log(
    `[capture] writing → ${OUT_INSTALL} (install) and ${OUT_PANEL} (panel)`,
  );

  const browser = await chromium.launch();
  try {
    // Install captures first — they only work against a fresh DB
    // where /install/ exists. CI ensures this via `docker compose up`
    // from a clean volume.
    await captureRoutes(browser, INSTALL_ROUTES, OUT_INSTALL);
    // Panel captures need an authenticated session. Run with `login`
    // turned on so the admin-only routes paint actual content.
    await captureRoutes(browser, PANEL_ROUTES, OUT_PANEL, { login: true });
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error('[capture] fatal:', err);
  process.exit(1);
});

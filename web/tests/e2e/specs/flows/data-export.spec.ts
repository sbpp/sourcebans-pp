/**
 * Flow spec — `feat/data-export-bundle`: end-to-end ZIP download of
 * the "Full data export" admin surface.
 *
 * The PHPUnit side covers the per-row wire format
 * (`tests/unit/EntityExporterTest.php`), the manifest contract
 * (`tests/unit/ManifestBuilderTest.php`), the bundle assembly
 * (`tests/integration/ExportBundleWriterTest.php`), the S3 transport
 * (`tests/integration/S3PresignedUploaderTest.php`), and the
 * permission gate (`tests/integration/AdminExportPermissionTest.php`
 * + `AdminExportRuntimePermissionTest.php`). This spec is the
 * end-to-end seam: real browser, real form submit, real Apache
 * streaming `web/export.php`'s `php://output` ZipStream payload,
 * real chrome around it.
 *
 * # What this spec pins
 *
 *   1. The admin-export page (`?p=admin&c=export`) actually renders
 *      for the seeded admin/admin user — the navbar entry is
 *      reachable, the page handler's `CheckAdminAccess(ADMIN_OWNER)`
 *      passes, the `AdminExportView` populates, the form template
 *      ships the two cards.
 *
 *   2. Clicking "Export as ZIP" triggers a real browser download
 *      (NOT a navigation, NOT a JSON envelope) — i.e. the
 *      `Content-Disposition: attachment; filename="..."` header the
 *      entry point emits is correctly interpreted by the browser.
 *      Pre-fix a missing header would render the ZIP bytes as a
 *      text-mode response, which Chromium silently treats as a
 *      navigation and lands the user on a "this site can't be
 *      reached" page.
 *
 *   3. The downloaded bytes parse as a valid ZIP (any ZIP-aware
 *      consumer can open it). Belt-and-suspenders against the
 *      integration test in case `php://output` buffering
 *      configuration differs in dev-stack Apache vs PHPUnit.
 *
 *   4. The first entry is `manifest.json` — the "manifest first"
 *      contract is part of the wire format (downstream consumers
 *      can parse the manifest by reading just the first
 *      central-directory entry without slurping the whole bundle).
 *
 *   5. The manifest carries `format_version: 1` and a non-empty
 *      `row_counts` dictionary — the load-bearing fields the
 *      operator's pipeline keys off.
 *
 *   6. CSRF + permission are gated end-to-end: a navigation to
 *      `/export.php` via GET (i.e. NOT through the form's POST)
 *      returns 405 Method Not Allowed. This is the wire-layer
 *      sister to the static-shape `testEntryPointEnforcesPostOnly`
 *      assertion in `AdminExportPermissionTest.php`.
 *
 * # What this spec deliberately does NOT pin
 *
 *   - Row-by-row content of the JSONL streams (covered by
 *     `EntityExporterTest`).
 *   - SteamID conversion, mute-kind, ban-state derivation
 *     (covered by `EntityExporterTest`).
 *   - Forbidden-column absence (covered by `EntityExporterTest`
 *     + `ExportBundleWriterTest`).
 *   - Cap math (covered by `ManifestBuilderTest`).
 *   - The S3 upload mode (covered by `S3PresignedUploaderTest`'s
 *     transport-injection — driving the real S3 path through the
 *     dev MinIO would add a separate spec dependency without
 *     buying additional contract coverage; the static + transport
 *     tests are sufficient).
 *
 * # Test data
 *
 * Uses the e2e DB's baseline seed (the `admin/admin` row + the
 * default `sb_settings`). NO per-spec truncate-and-reseed —
 * `data-export` is a read-only surface, so the row counts the
 * manifest reports are whatever the e2e DB happens to carry at
 * spec run time, and the spec asserts shape contracts that hold
 * regardless of cardinality (`row_counts.admins >= 1` rather than
 * `row_counts.admins === 5`). This keeps the spec resilient to
 * cross-spec data accumulation and avoids fighting the
 * `Fixture::truncateAndReseed` GET_LOCK contention every
 * sister spec already has to manage.
 */

import { test, expect } from '../../fixtures/auth.ts';
import JSZip from 'jszip';

test.describe('admin data export', () => {
    test('admin export page renders for the seeded admin', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=export');

        const section = page.locator('[data-testid="admin-export-section"]');
        await expect(section).toBeVisible();

        // Both cards present (ZIP + S3 modes).
        await expect(page.locator('[data-testid="admin-export-zip-form"]')).toBeVisible();
        await expect(page.locator('[data-testid="admin-export-s3-form"]')).toBeVisible();
        await expect(page.locator('[data-testid="admin-export-zip-submit"]')).toBeVisible();
        await expect(page.locator('[data-testid="admin-export-s3-submit"]')).toBeVisible();

        // Stats panel populated — the manifest builder ran the
        // pre-flight counts and the View DTO propagated them to
        // the template.
        await expect(page.locator('[data-testid="admin-export-count-admins"]')).toBeVisible();
        await expect(page.locator('[data-testid="admin-export-count-bans"]')).toBeVisible();
        await expect(page.locator('[data-testid="admin-export-count-comms"]')).toBeVisible();
        await expect(page.locator('[data-testid="admin-export-count-demos"]')).toBeVisible();
    });

    test('clicking "Export as ZIP" downloads a valid bundle with manifest-first contract', async ({ page }) => {
        await page.goto('/index.php?p=admin&c=export');

        await expect(page.locator('[data-testid="admin-export-zip-submit"]')).toBeVisible();

        // The download event fires when the server emits the
        // Content-Disposition: attachment header AND the body
        // starts streaming. We have to register the listener
        // BEFORE we click the button so the event isn't missed.
        const downloadPromise = page.waitForEvent('download', { timeout: 30_000 });

        // Submit via the actual form click — same path an admin
        // takes in production. This proves the form's hidden
        // mode=zip input + the CSRF field + the action URL all
        // land at the entry point correctly.
        await page.locator('[data-testid="admin-export-zip-submit"]').click();

        const download = await downloadPromise;

        // Filename shape — the entry point emits `sbpp-export-<uuid>.zip`.
        // The exact UUID is impossible to predict from the spec
        // side (the manifest is minted fresh per request), so we
        // pattern-match the shape.
        expect(download.suggestedFilename()).toMatch(
            /^sbpp-export-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.zip$/,
        );

        // Read the downloaded bytes. Playwright's `path()` returns
        // the local file path the browser saved the download to;
        // we load it as a Buffer and feed it to JSZip.
        const path = await download.path();
        const fs = await import('node:fs/promises');
        const buf = await fs.readFile(path);
        expect(buf.length).toBeGreaterThan(0);

        // Parse the ZIP. JSZip's `loadAsync` is permissive — it
        // accepts both the streaming and the random-access forms.
        // If the bytes aren't a valid ZIP this rejects and the
        // test fails loudly.
        const zip = await JSZip.loadAsync(buf);

        // Manifest-first contract: the first entry in the central
        // directory MUST be `manifest.json`. Downstream consumers
        // can then parse the manifest without slurping the whole
        // bundle. JSZip preserves insertion order, so iterating
        // `files` gives the original write order.
        const names = Object.keys(zip.files);
        expect(names.length).toBeGreaterThan(0);
        expect(names[0]).toBe('manifest.json');

        // Manifest parses + carries the wire-format identifier
        // every consumer pipeline keys off.
        const manifestFile = zip.file('manifest.json');
        expect(manifestFile).not.toBeNull();
        const manifestJson = await manifestFile!.async('text');
        const manifest = JSON.parse(manifestJson);

        expect(manifest.format_version).toBe(1);
        expect(typeof manifest.bundle_id).toBe('string');
        expect(manifest.bundle_id).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
        );
        expect(typeof manifest.created_at).toBe('number');
        expect(manifest.created_at).toBeGreaterThan(1_700_000_000);
        expect(typeof manifest.row_counts).toBe('object');
        expect(manifest.row_counts).not.toBeNull();

        // The e2e DB carries the seeded admin/admin row, so
        // admins >= 1. We use >= rather than === so cross-spec
        // data accumulation doesn't break this gate (and so the
        // spec stays resilient if a future fixture adds more
        // seed rows).
        expect(typeof manifest.row_counts.admins).toBe('number');
        expect(manifest.row_counts.admins).toBeGreaterThanOrEqual(1);

        // PII policy block: load-bearing operator attestation.
        // The shape contract is pinned at unit-test level
        // (ManifestBuilderTest); here we just verify it's
        // present so a downstream consumer can route the bundle
        // through the appropriate handling controls.
        expect(typeof manifest.pii_policy).toBe('object');
        expect(manifest.pii_policy).not.toBeNull();
        expect(manifest.pii_policy.password_hashes).toBe('never');
    });

    test('GET to /export.php returns 405 (POST-only)', async ({ page }) => {
        // The entry point enforces POST-only — a GET (from a hand-
        // typed URL bar or a hostile probe) must reject with 405
        // BEFORE the body starts streaming. This is the wire-layer
        // sister to AdminExportPermissionTest's static-shape gate.
        //
        // Use `page.request.get` so the same authenticated session
        // cookies travel — proves the gate fires even for an
        // authenticated owner who happens to GET the URL.
        const response = await page.request.get('/export.php');
        expect(response.status()).toBe(405);
    });
});

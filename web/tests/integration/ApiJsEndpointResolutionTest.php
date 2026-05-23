<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1433: pin the `web/scripts/api.js` endpoint-resolution contract.
 *
 * Background
 * ----------
 *
 * Pre-#1433 api.js shipped `endpoint: './api.php'` as the load-bearing
 * default. Document-relative resolution is fine on a top-level panel
 * page render (`/index.php?p=…` → resolves against `/` → fetch lands
 * on `/api.php`), but the iframe-routed surfaces under
 * `web/pages/admin.kickit.php` + `web/pages/admin.blockit.php` sit
 * one directory deep — the iframe document URL is
 * `/pages/admin.kickit.php`, the bare `./api.php` resolves against
 * the iframe document, and the fetch goes to `/pages/api.php`. The
 * Apache config (`docker/apache/sbpp-prod.conf`) does not rewrite
 * `/pages/api.php` so the request 404s; the iframe's
 * `KickitLoadServers` call resolves to a `bad_response` envelope and
 * the load handler's silent early-return (`if (!r || !r.ok || !r.data) return`)
 * leaves every row at the initial "Waiting..." text forever. Player
 * is never kicked. Same code path on every iframe-routed surface
 * that loads api.js — kick (Bug 1), post-ban kickit fan-out (Bug 2),
 * blockit, the future Sleuth iframe, etc.
 *
 * Fix
 * ---
 *
 * api.js now captures `document.currentScript.src` at script-load
 * time (it's null inside async handlers / promises, so the value
 * has to be cached at the top of the IIFE) and computes the
 * endpoint as `new URL('../api.php', SCRIPT_SRC).href`. This lands
 * on the panel-root `/api.php` for top-level renders AND for
 * iframe contexts AND for subdir installs (`https://host/sourcebans/`
 * → script at `…/sourcebans/scripts/api.js` → endpoint at
 * `…/sourcebans/api.php`).
 *
 * Static gate
 * -----------
 *
 * This test reads `web/scripts/api.js` as a string and asserts:
 *
 *   - The literal `document.currentScript` is referenced (the
 *     load-bearing capture mechanism).
 *   - The literal `new URL('../api.php'` appears (the resolution
 *     call). The relative-segment count is part of the contract —
 *     api.js lives at `/scripts/api.js`, so `../api.php` is the
 *     only correct shape; `./api.php` from the script URL would
 *     land on `/scripts/api.php` (also 404).
 *   - The bare `endpoint: './api.php'` literal is NOT the
 *     load-bearing default. It can survive as a fallback string
 *     literal (the IIFE keeps it for the no-`currentScript` path,
 *     e.g. ancient browsers without `document.currentScript`
 *     support), but it must NOT be the value `sb.api.endpoint`
 *     binds to at construction time. We approximate "is the
 *     load-bearing default" by asserting `endpoint: './api.php'`
 *     does NOT appear: the post-fix code reads `endpoint: resolveEndpoint(),`
 *     so the literal-with-init shape is gone.
 *
 * This catches a future refactor that "simplifies" the endpoint
 * back to the broken literal (the kind of edit a reviewer might
 * wave through if they didn't know the iframe-routed surfaces
 * exist).
 *
 * Pure file scanning — extends `PHPUnit\Framework\TestCase` directly
 * (no DB / session / Smarty bring-up). Sister of
 * `DeadJsCallSitesTest`; same per-file forbidden-substring shape,
 * inverted into per-required + per-forbidden assertions for the
 * single api.js surface.
 */
final class ApiJsEndpointResolutionTest extends TestCase
{
    private static function apiJsPath(): string
    {
        return ROOT . 'scripts/api.js';
    }

    private static function apiJsContents(): string
    {
        $contents = @file_get_contents(self::apiJsPath());
        if ($contents === false) {
            self::fail('Could not read ' . self::apiJsPath() . ' — file moved or test bootstrap broke?');
        }
        return $contents;
    }

    public function testApiJsExists(): void
    {
        $this->assertFileExists(
            self::apiJsPath(),
            'web/scripts/api.js must live at scripts/api.js for the iframe-routed surfaces (kickit / blockit) to load `../scripts/api.js` correctly. If you moved it, update both this test AND the per-template `<script src="">` tags in `page_kickit.tpl` / `page_blockit.tpl` / the panel chrome footer.',
        );
    }

    public function testApiJsReferencesDocumentCurrentScript(): void
    {
        $contents = self::apiJsContents();
        $this->assertStringContainsString(
            'document.currentScript',
            $contents,
            "api.js must capture `document.currentScript.src` to resolve the endpoint against the script's own absolute URL (#1433). Without this the endpoint falls back to a document-relative path and the iframe-routed surfaces (pages/admin.kickit.php, pages/admin.blockit.php) silently 404 on every API call.",
        );
    }

    public function testApiJsResolvesEndpointAgainstScriptSrc(): void
    {
        $contents = self::apiJsContents();
        $this->assertStringContainsString(
            "new URL('../api.php'",
            $contents,
            "api.js must compute the endpoint as `new URL('../api.php', SCRIPT_SRC).href` (#1433). The `..` segment is load-bearing — api.js lives at `/scripts/api.js`, so `./api.php` resolves against the script URL to `/scripts/api.php` (also 404). If you intentionally changed the resolution shape, update this assertion + verify the iframe-routed surfaces still hit `/api.php`.",
        );
    }

    public function testApiJsDoesNotHardcodeRelativeEndpoint(): void
    {
        // The fallback string `'./api.php'` may legitimately survive
        // as the no-`currentScript` defensive return value inside
        // `resolveEndpoint()`. What CANNOT survive is the
        // pre-#1433 shape that bound the literal directly to
        // `sb.api.endpoint` at construction time:
        //
        //     endpoint: './api.php',
        //
        // Asserting against the exact construction-time bind shape
        // (`endpoint: './api.php',`) catches a refactor that
        // "simplifies" the endpoint back to the broken literal
        // without false-matching the legitimate fallback return.
        $contents = self::apiJsContents();
        $this->assertStringNotContainsString(
            "endpoint: './api.php',",
            $contents,
            "api.js must NOT bind `sb.api.endpoint` to the bare relative literal `'./api.php'` at construction time (#1433). The post-fix shape is `endpoint: resolveEndpoint(),`. If you reverted the resolver, every iframe-routed surface (kick / block / kick-on-ban) silently 404s.",
        );
    }
}

// TypeScript ambient declarations for the docs project.
//
// `astro/client` is auto-included by `astro check` via the
// `.astro/types.d.ts` shim — that gives us `astro:content`,
// `astro:assets`, etc.
//
// Starlight's `virtual:starlight/components/*` modules (used by the
// Footer.astro override to re-render Starlight's stock per-page chrome)
// have type declarations in `virtual-internal.d.ts` but it isn't a
// public export entry — Starlight ships it as an unscoped path-based
// reference. Pull it in here so consumer overrides type-check cleanly
// under `astro check`.
/// <reference path="../node_modules/@astrojs/starlight/virtual-internal.d.ts" />

---
title: Browser freeze
description: Solutions and debugging steps for unresponsive panel pages.
sidebar:
  order: 1
---

If a panel page hangs the browser tab, two things usually explain it:
a Cloudflare optimization that breaks the panel's bundled JS, or a
genuine bug worth a perf trace.

## Cloudflare

If your site sits behind Cloudflare as a reverse-proxy, **disable
Rocket Loader** in your Cloudflare zone settings.

Rocket Loader rewrites and re-orders JavaScript loads, which breaks
the panel's chrome — historically by mis-bundling MooTools (pre-2.0
panels), and on the current panel by reordering the inline boot
scripts that depend on theme.js.

:::caution
Rocket Loader is a per-zone setting. If you're sharing a Cloudflare
account with other services, only the SourceBans++ subdomain needs
it off — use a Page Rule scoped to the panel hostname.
:::

## Debugging

Most browsers ship a performance debugger, accessible via `F12`.

With the performance recorder open:

1. Start recording.
2. Navigate to the unresponsive page.
3. End the recording after ~10 seconds.
4. Export the trace.

Then drop into our [Discord](https://discord.gg/4Bhj6NU) `#help-support`
channel and post the exported file. The trace will tell us whether the
hang is in the panel's JS, an upstream library, or your own browser
extension.

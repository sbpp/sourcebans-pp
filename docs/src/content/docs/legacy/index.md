---
title: Legacy
description: Documentation for older SourceBans++ versions and one-off upgrade quirks.
sidebar:
  order: 1
---

:::caution
The pages in this section cover SourceBans++ versions that are no
longer maintained. Some content may be inaccurate or out of date.
:::

## What lives here

This section is the soft archive — historical material that no
longer fits the supported flow but that someone running an old
version may still need. Content lands here when:

- It documents a setup the current panel no longer supports
  (e.g. PHP < 8.5, SourceMod < 1.11).
- It documents a one-off upgrade between two old releases that
  doesn't apply to anyone landing on the current panel.
- The current docs cover the same material better, but the legacy
  phrasing is still cited externally and we don't want to break
  inbound links.

If you're on a current install, you almost certainly want one of
these instead:

- [Quickstart](/getting-started/quickstart/) — fresh install on a
  current PHP / SourceMod / DB stack.
- [Updating SourceBans++](/updating/) — upgrade path for any panel
  from 1.6.x or later. The intermediate version notes (1.6.x → 1.7.0,
  1.7.x → 1.8.x, 1.8.x → 2.0.x) live there.
- [Troubleshooting](/troubleshooting/panel-not-loading/) — the
  catalog of common errors and fixes.

## Pages in this section

- [Plugin upgrade from <= 1.5.4.7](/legacy/plugin-pre-1.5.4.7/) —
  one-off cleanup steps for the SourceMod plugin half when migrating
  from a pre-1.6 install.

If you're trying to upgrade an install older than 1.6.x and nothing
here or in [Updating](/updating/) covers your starting point, drop
into our [Discord](https://discord.gg/tzqYqmAtF5) `#help-support`
channel — we'll walk you through it and add a page here if your
situation is likely to recur for someone else.

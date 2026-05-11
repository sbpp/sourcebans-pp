---
title: Legacy
description: Soft-archived documentation for legacy SourceBans++ versions.
sidebar:
  order: 1
---

:::caution
Documentation in this section refers to versions that are no longer
maintained. The information may be inaccurate or out of date.
:::

## What lives here

This section is the soft archive — historical material that no longer
fits the supported flow but that someone running an old version may
still need. Content lands here when:

- It documents a setup that the current panel doesn't support
  (e.g. PHP < 8.5, SourceMod < 1.11).
- It documents a one-off upgrade quirk between two specific old
  releases (e.g. "upgrading from 1.5.x to 1.6.x") that doesn't apply
  to anyone landing on the current panel today.
- The current docs cover the same material more comprehensively for
  the current panel, but the legacy phrasing is still cited
  externally and we don't want to break those inbound links.

## What lives elsewhere

For the **current** install / upgrade / troubleshooting flow:

- [Quickstart](/getting-started/quickstart/) — fresh install on a
  current PHP / SourceMod / DB stack.
- [Updating](/updating/) — upgrade path for any panel from 1.6.x or
  later. The 1.6.x → 1.7.0 → 1.8.x sections cover the
  `SB_SECRET_KEY` and Smarty 5 transitions explicitly.
- [Troubleshooting](/troubleshooting/database-errors/) — the
  catalog of common error messages and fixes.

## Pages

- [Plugin upgrade from <= 1.5.4.7](/legacy/plugin-pre-1.5.4.7/) — the
  one-off cleanup pass when migrating the SourceMod plugin half off a
  pre-1.6 install (delete the old `sourcebans.smx` / `sourcecomms.smx`
  / etc., let the new `sbpp_*.smx` consolidated plugins take over).

If you're trying to upgrade an install older than 1.6.x and nothing
in [Updating](/updating/) or the page above covers your starting
point, drop into our [Discord](https://discord.gg/4Bhj6NU)
`#help-support` channel — we'll walk you through it and add a page
here if your situation is likely to recur.

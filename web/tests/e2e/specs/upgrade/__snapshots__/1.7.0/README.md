# `__snapshots__/1.7.0/` — locked drift baseline (#1269)

Snapshot artefacts the upgrade harness asserts byte-equality against.
Each file in this directory is the **expected** state of one parity
dimension; the spec (`upgrade-1.7.0.spec.ts`) computes the actual
state at runtime and fails when it diverges.

## `schema.diff`

The expected diff between:

- The post-upgrade schema (1.7.0 fixture walked through migrations
  `705 → 801 → 802 → 803 → 804 → 805`), and
- A fresh `struc.sql + data.sql` install.

**Empty file = full schema parity.** That's the target state and the
acceptance criterion in [#1269](https://github.com/sbpp/sourcebans-pp/issues/1269).

**Non-empty file = known drift** documented in the harness PR's
deferred-followups list (and indexed in
[`web/tests/e2e/specs/upgrade/README.md`](../../README.md) under
"Known schema drift"). Each line of drift corresponds to a real
upgrade-path bug that needs a paired migration fix; we lock the
current state in the snapshot so the spec stays green while
individual fixes ship in their own PRs (per #1166's "small,
sequential PRs" rule).

When you ship a fix that closes one of the locked drift items,
**delete the matching line from this file in the same PR** — the
spec asserts byte-equality, so any actual-state change requires a
synchronized snapshot update. The reviewer's signal that the fix
landed correctly is "the snapshot lost a line and the spec still
passes".

When the file is finally empty (all known drift fixed), the spec
asserts "no drift, period" and any future regression fails the
build immediately.

## Diff format

The diff is produced by `_helpers/parity.ts::diffSchemas`. It's a
human-readable, line-stable serialization (NOT byte-equal to
`diff -u` output, which carries hunk headers that would force
needless snapshot churn on unrelated changes). Lines:

- `+ <thing>` — only in the fresh install (i.e. the upgrade missed it).
- `- <thing>` — only in the post-upgrade DB (i.e. the upgrade added
  something the fresh install doesn't).
- `~ <thing>` — present in both, but the metadata differs.

`<thing>` is one of:

- `table <name>` — table-level presence drift.
- `<table>.<column>` — column-level presence/metadata drift.
- `<table> index <name>` — index-level presence/metadata drift.

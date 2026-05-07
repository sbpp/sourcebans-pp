# `web/tests/e2e/specs/upgrade/` — upgrade harness (#1269)

Playwright specs that drive the **v1.x → v2.0 upgrade end-to-end** against
the snapshot fixtures committed under
[`fixtures/upgrade/`](../../../../../fixtures/upgrade/) (#1268).

This folder lives separately from the `smoke/`, `flows/`, `a11y/`, and
`responsive/` suites because the upgrade harness has a **different DB
lifecycle** — it creates throwaway `sourcebans_upgrade_*` schemas per
test rather than sharing the suite-wide `sourcebans_e2e` DB. Mixing the
two would silently corrupt the regular suite's truncate-and-reseed
contract.

## Why this exists

#1166 set out to verify the v2.0.0 upgrade path against representative
1.x snapshots. PR1 of that issue ([#1268](https://github.com/sbpp/sourcebans-pp/pull/1268))
landed the fixture inputs (1.7.0 + 1.8.4 `.sql.gz` snapshots + paired
`config.<version>.php` files + `capture/capture.sh`).

This harness is the **scripted, deterministic, agent-controlled
replacement** for "an agent walks the upgrade path manually, twice"
that #1166 originally proposed. The argument for replacing the manual
walk lives in [the issue body](https://github.com/sbpp/sourcebans-pp/issues/1269)
(short version: a manual walk is a one-shot artifact, not a regression
test, and the four post-upgrade smoke flows are exactly the shape of
the Playwright specs the repo already runs in CI).

## What's in scope for the current slice

This is the **scaffold + happy path on one fixture**. Concretely:

- `upgrade-1.7.0.spec.ts` — drives the 1.7.0 → 2.0 upgrade end-to-end
  and asserts on:

  1. `/upgrade.php` appends `SB_SECRET_KEY` to a config that lacks it
     (and short-circuits when the key is already defined).
  2. `/updater/index.php` walks the migration chain
     `705 → 801 → 802 → 803 → 804 → 805` in one pass.
  3. A second `/updater/index.php` pass is a verified no-op.
  4. The post-upgrade schema matches a fresh `struc.sql + data.sql`
     install (snapshotted under `__snapshots__/1.7.0/schema.diff`).
  5. Every key in fresh `data.sql`'s `:prefix_settings` exists on the
     upgraded DB.
  6. Smoke flow: `admin / admin` logs into the upgraded panel.

- `_helpers/` — TypeScript helpers + a PHP CLI driver
  (`scripts/upgrade-db.php`) for the DB lifecycle:

  | File                          | Responsibility                                                  |
  | ----------------------------- | --------------------------------------------------------------- |
  | `upgradeFlow.ts`              | Drive `/upgrade.php` + `/updater/index.php` over HTTP, parse the runner's `<li>` log into a structured result. |
  | `upgradeDb.ts`                | TS bridge to the PHP helper — reset upgrade DB, install fresh ref DB, dump schema/settings, render/restore config. |
  | `parity.ts`                   | Pure-string diff routines (schema, settings) — no Playwright deps. |
  | `copyFixture.ts`              | Stage host-side fixture files into the container's `/tmp/`. |
  | `scripts/upgrade-db.php`      | PHP CLI driver. Refuses any DB whose name doesn't start with `sourcebans_upgrade_` (mirror of the dev synthesizer's safety guard). |

- `__snapshots__/1.7.0/schema.diff` — the locked schema parity snapshot.
  Empty file = full parity. Non-empty = known drift documented in the
  follow-ups list below; new drift fails the spec.

## What's deliberately **not** in this slice

The full issue is multi-PR by design. The PR body for this slice
enumerates the deferred follow-ups; reproduced here as a stable index:

1. **Second fixture (1.8.4).** The harness shape is generic, but
   `upgrade-1.8.4.spec.ts` lands separately so the 1.7.0 baseline is
   reviewed in isolation first.
2. **Three additional smoke flows post-upgrade**: ban add (web), ban
   insert (plugin → web), mail send (Mailpit). Currently we only
   assert on login.
3. **Standalone `.github/workflows/upgrade-e2e.yml` workflow.** The
   spec runs locally for now via `./sbpp.sh upgrade-e2e` (or
   `./sbpp.sh e2e --grep @upgrade`). CI gating waits for the harness
   to stabilize on both fixtures.
4. **1.x release tarball download + hash-check + cache** (the
   `fixtures/upgrade/cache/` tier). Out of scope here; the fixture
   gz is sufficient for this slice.
5. **Operator handoff** ([#1115](https://github.com/sbpp/sourcebans-pp/issues/1115))
   is independent of which method produces the upgrade evidence;
   once the harness covers both fixtures, the operator review can
   proceed against `UPGRADING.md`.

## Known schema drift (locked in the baseline snapshot)

The 1.7.0 walk surfaces real upgrade-path bugs the dev seed never
exercised. Each one is filed as a follow-up; the snapshot at
`__snapshots__/1.7.0/schema.diff` carries the current drift so the
spec stays green while the fixes ship in their own PRs.

| Where | Drift | Cause |
| ----- | ----- | ----- |
| `sb_admins.attempts` | `IS_NULLABLE` differs (upgraded: `YES`, fresh: `NO`) | Migration 801 emits `ALTER TABLE … ADD COLUMN attempts INT DEFAULT 0` (no `NOT NULL` clause); `struc.sql` declares the column `int(11) NOT NULL default '0'`. |

When the locked drift list reaches zero, the snapshot becomes an empty
file and the harness asserts "no drift, period" — which is the target
state per the issue body.

## Running the harness

The wrapper takes care of bringing up the dev stack, copying fixtures
into the container, pinning the panel at the upgrade DB for the run,
and restoring config.php on exit:

```sh
./sbpp.sh upgrade-e2e            # runs every spec under specs/upgrade/
./sbpp.sh upgrade-e2e --grep 1.7 # narrow to the 1.7.0 spec
```

For ad-hoc debugging from the host shell (e.g. attaching a debugger):

```sh
# Bring up the stack, copy fixtures, then drive playwright directly.
./sbpp.sh up
docker compose cp fixtures/upgrade/1.7.0.sql.gz       web:/tmp/sbpp-upgrade-1.7.0.sql.gz
docker compose cp fixtures/upgrade/config.1.7.0.php   web:/tmp/sbpp-upgrade-config-1.7.0.php
cd web/tests/e2e && npx playwright test --grep '@upgrade'
```

The override at `docker-compose.override.yml` keeps the harness's
ports and named volumes scoped to `sbpp-issue-1269` so a parallel
worktree's stack isn't disturbed.

## Determinism + reproducibility contract

- The 1.x fixture gz dump is byte-for-byte deterministic given a fixed
  `SBPP_CAPTURE_RNG` (default `11660`); see
  [`fixtures/upgrade/README.md`](../../../../../fixtures/upgrade/README.md).
- The harness drops + re-creates the upgrade DB in `beforeAll`, so a
  failed previous run doesn't leak state into the next.
- Schema/setting dumps are sorted by table → column → index name (see
  `scripts/upgrade-db.php`'s `dump-schema`), so the JSON output and
  resulting diff hash-match across runs on the same dataset.
- `SB_SECRET_KEY` is rendered fresh by `/upgrade.php` on every run;
  the bytes of the key affect nothing the parity assertions read
  (`:prefix_settings` doesn't carry it; the schema dump doesn't either),
  so this non-determinism is contained.

If you observe a non-deterministic spec failure, the canonical
investigation path is: (1) `./sbpp.sh upgrade-e2e --trace=on`,
(2) inspect the schema diff against the snapshot, (3) check whether a
new sibling spec is racing on the panel's config.php (the `serial`
mode in `test.describe.configure({ mode: 'serial' })` plus the
wrapper's stash/restore should prevent this — file an issue if it
doesn't).

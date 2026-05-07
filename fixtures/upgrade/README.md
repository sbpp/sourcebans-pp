# `fixtures/upgrade/` — pre-2.0 install snapshots for the upgrade dry-run

Foundational test bed for the SourceBans++ 2.0.0 upgrade dry-run
([issue #1166](https://github.com/sbpp/sourcebans-pp/issues/1166)).
2.0.0 is the first major in ~3 years; the
[`web/updater/`](../../web/updater/) migrations have never been walked
against a real production-shaped database. Without these snapshots,
every step in [`UPGRADING.md`](../../UPGRADING.md) (#1115) is an
unverified claim.

## What the snapshots are

Two `.sql.gz` dumps + matching `config.php` files, captured against
the official release tarballs of two representative starting points:

| Snapshot         | Release                                                                          | `config.version` | Schema highlights |
| ---------------- | -------------------------------------------------------------------------------- | ---------------- | ----------------- |
| `1.7.0.sql.gz`   | [SourceBans++ 1.7.0](https://github.com/sbpp/sourcebans-pp/releases/tag/1.7.0)   | `704`            | `sb_admins` lacks `attempts` / `lockout_until` (added in 705 → 801); `adminIp varchar(32)` (changed to `varchar(128)` in 705). |
| `1.8.4.sql.gz`   | [SourceBans++ 1.8.4](https://github.com/sbpp/sourcebans-pp/releases/tag/1.8.4)   | `705`            | Already has the lockout columns and `adminIp varchar(128)`. |

The two snapshots therefore exercise **two different paths through the
migrator**:

- A 1.7.0 install runs migrations `705 → 801 → 802 → 803 → 804 → 805`
  (six steps — exercises the long walk).
- A 1.8.4 install runs `801 → 802 → 803 → 804 → 805` (five steps —
  exercises the short walk).

Both starting points share the same scale-data shape so the dry-run
can compare apples-to-apples: 200 admins / 5 web groups / 30 servers
across 5 mods / 5 SourceMod srvgroups / 5000 bans (mix of permanent /
temporary / unbanned / appealed) / 500 comm blocks / 50 protests / 80
public submissions / 200 ban comments / 1000 banlog rows.

### 4-byte UTF-8 coverage (#1108)

[Issue #1108](https://github.com/sbpp/sourcebans-pp/issues/1108) caught
the `DB_CHARSET = 'utf8'` (3-byte alias) regression: supplementary-plane
characters (emoji + extension B CJK) silently fail to insert under the
older alias. The seeder writes player names that include all of:

- ASCII (`PyroChampion`, `BotKiller`)
- Latin-1 + combining diacritics (`José_Müller`, `Açaí_Maníaco`,
  Ka<U+0308>se with combining U+0308)
- Cyrillic, Greek (`Сергей`, `Ναυτίλος`)
- Basic-multilingual-plane CJK (`玩家_007`, `李雷的弟弟`)
- **4-byte UTF-8** — emoji (`🎮GamerKing🎮`, `💀DeathDealer💀`,
  `🚀RocketJump🚀`) and supplementary-plane CJK (`𠀋玩家` U+2000B,
  `𩸽_寿司` U+29E3D)

After the upgrade dry-run lands the snapshot in the dev DB, those rows
must round-trip through every migration unchanged. If a migration uses
the wrong client charset, the 4-byte rows will mojibake to `?` —
exactly the regression #1108 is the gate against.

The snapshot itself is captured against `utf8mb4` end-to-end (the
dev-stack default). The matching `config.php` files patch the
`DB_CHARSET` constant to `utf8mb4` for the same reason; an operator
who never updated their post-#765 `config.php` would have `utf8` and
the 4-byte rows would have already been corrupted at insert time. We
capture the **fixed** baseline because the alternative — a pre-#1108
snapshot — has nothing useful to upgrade-test against (the rows are
already destroyed at the source).

## What lives in this directory

```
fixtures/upgrade/
├── README.md                 # this file
├── 1.7.0.sql.gz              # 1.7.0 starting-point snapshot
├── 1.8.4.sql.gz              # 1.8.4 starting-point snapshot
├── config.1.7.0.php          # matching config.php with redacted secrets
├── config.1.8.4.php          # matching config.php with redacted secrets
└── capture/
    ├── capture.sh            # orchestrator (re-runnable for any version)
    └── seed.php              # PHP seeder (deterministic, RNG=11660)
```

The `capture/.cache/` subdirectory holds downloaded release archives
+ extracted trees and is `.gitignore`d; it is recreated on demand.

### Why these live OUTSIDE `web/`

The `web/` tree is what ships in the public release tarball
(`.github/workflows/release.yml` — every file under `web/` makes it
into `sourcebans-pp-X.Y.Z.webpanel-only.tar.gz`). These fixtures are
several megabytes of test data; bundling them with every release
would inflate the download by ~500 KB while serving zero runtime
purpose. They exist for **maintainers** running the upgrade dry-run,
not for self-hosters.

The same reasoning applies to the capture scripts. The dev DB seeder
([`web/tests/Synthesizer.php`](../../web/tests/Synthesizer.php)) is
intentionally inside `web/` because it backs `./sbpp.sh db-seed` for
day-to-day dev work; the upgrade fixtures are a separate axis (they
target an old schema, not the current one) and don't share plumbing.

## Reusing a snapshot for the upgrade dry-run

The intended use is: load one of these snapshots into a DB the panel
under test points at, then visit `web/updater/index.php` and observe.

```sh
# In the worktree of 2.0.0 code under test:
./sbpp.sh up                                      # bring up the dev stack
./sbpp.sh db-reset                                # blank slate
./sbpp.sh db-load <(gunzip -c fixtures/upgrade/1.7.0.sql.gz)
cp fixtures/upgrade/config.1.7.0.php web/config.php

# Then in a browser, hit:
#   http://localhost:8166/updater/index.php
# and walk through the migration log.
```

`UPGRADING.md` (#1115) is the canonical step-by-step. This README only
documents the snapshots themselves; everything about *what to do with
them* belongs in that doc. Any "operator hits this and recovers
manually" surprise discovered during the dry-run goes there as a
note; any "the migrator itself is broken" surprise goes as a separate
fix-PR per [`AGENTS.md` → "Updater migrations"](../../AGENTS.md#updater-migrations).

The snapshot is a **read-only** artefact for the dry-run; the
upgrade procedure mutates the loaded DB. Always re-load from the gz
between attempts to start from a known-clean state.

## Regenerating the snapshots

The snapshots are reproducible: `capture.sh` is idempotent given the
same input release artefact + RNG seed.

```sh
# Re-capture both snapshots (overwrites the .sql.gz + config.<version>.php
# pairs in this directory):
fixtures/upgrade/capture/capture.sh --all

# Or one at a time:
fixtures/upgrade/capture/capture.sh 1.7.0
fixtures/upgrade/capture/capture.sh 1.8.4
```

Requirements: `docker`, `gzip`, `sed`, `python3` (for unzip on .zip
archives — 1.8.4's release only ships `webpanel-only.zip`; 1.7.0
ships both `.tar.gz` and `.zip`). No system PHP is required — the
seeder runs inside an ephemeral `php:8.2-cli` container with
`pdo_mysql` installed at runtime.

The script:

1. Downloads the version's `webpanel-only` archive into
   `capture/.cache/` (cached; subsequent runs skip the network pull).
2. Extracts `install/includes/sql/struc.sql`,
   `install/includes/sql/data.sql`, and `config.php.template`.
3. Spins up an ephemeral `mariadb:10.11` container on a one-off
   docker network (named `sbpp-1166-capture-net` by default; override
   with `SBPP_CAPTURE_NET=...`).
4. Renders `{prefix} → sb`, `{charset} → utf8mb4`, then loads
   `struc.sql` + `data.sql` verbatim. **This is what the version's
   `install/index.php` wizard would have written if a real operator
   had walked it** — direct SQL loading is reproducible and the
   wizard's only DB-affecting step beyond loading the two files is
   the admin-row insert, which the seeder replicates.
5. Runs `seed.php` against the DB to write the scale data
   deterministically (`SEED_RNG=11660` by default; override with
   `SBPP_CAPTURE_RNG=...`).
6. `mariadb-dump`s the result and pipes it through `gzip -9n` into
   `<version>.sql.gz`.
7. Patches `config.php.template` with dev-stack defaults and writes
   it to `config.<version>.php` (redacted secrets — see "Why is
   `SB_SECRET_KEY` a placeholder?" below).
8. Tears down the ephemeral container and network.

If you re-run the capture and the resulting `.sql.gz` is byte-for-byte
identical to the committed version, the snapshot is genuinely
reproducible. If it's not, something drifted — either the upstream
release tarball changed (unusual; releases are immutable in practice
but can be re-uploaded) or the seeder picked up a non-deterministic
input. `mt_srand($rng)` is the only RNG entrypoint and every column
the seeder writes (including the `sb_servers.rcon` strings) is
derived from it, so a clean re-capture against the same `SEED_RNG`
must hash-match. Drift candidates to inspect first: a `data.sql`
row with a server-side default like `CURRENT_TIMESTAMP`, or an
ALTER somewhere that shuffled InnoDB clustered-key order.

### Adding a new starting-point version (e.g. 1.8.5 when it ships)

1. Add a `case "$1" in 1.8.5) ... ;;` arm in `capture.sh` to each of
   `release_url`, `release_archive_format`, and `release_top_dir`.
2. Run `./capture.sh 1.8.5` — produces `1.8.5.sql.gz` +
   `config.1.8.5.php`.
3. Update the table at the top of this README with the new row.
4. Add a row to [`AGENTS.md`'s "Where to find what" table](../../AGENTS.md#where-to-find-what)
   only if the new starting point exercises a meaningfully different
   migration walk; otherwise the existing 1.7.0 / 1.8.4 reference is
   enough.

## Why is `SB_SECRET_KEY` a placeholder?

`config.<version>.php` ships with:

```php
define('SB_SECRET_KEY', 'REPLACE_WITH_BASE64_47_BYTES_PLACEHOLDER_DO_NOT_USE_IN_PROD');
```

— not a real key. The string is intentionally noisy to make it
loudly fail any "did the operator forget to swap it?" review. Two
reasons:

1. **No secrets in git.** A committed JWT signing key is a
   dictionary attack waiting to happen against any panel that copy-
   pasted from the fixture without realising.
2. **Both upgrade-path branches stay testable.** With the constant
   defined to *any* value, [`web/upgrade.php`](../../web/upgrade.php)
   short-circuits via:

   ```php
   if (defined('SB_SECRET_KEY')) exit('Upgrade not needed.');
   ```

   That's the **dominant** path an operator hits — they upgraded
   to 1.7.0+ once and have a real key on file. If you specifically
   want to test the *minority* path (operator on a SourceBans
   1.x-or-pre-#458 install with no `SB_SECRET_KEY` line at all), do:

   ```sh
   sed -i "/SB_SECRET_KEY/d" web/config.php
   ```

   …after copying the fixture's config in. That deletes the constant
   so `web/upgrade.php` will hit the append-the-key branch.

Same reasoning for `STEAMAPIKEY` (placeholder unless you've supplied
your own — Steam OpenID login won't work otherwise) and `SB_EMAIL`
(set to `admin@example.test` so the legacy fallback in the Mailer
doesn't no-op silently).

## Determinism

The seeder is `mt_srand($rng)`-deterministic — every column it writes,
including the `sb_servers.rcon` strings (intentionally prefixed
`fixture-rcon-` so a careless `cp` of the captured DB into a
production-shaped install can't pretend to be a real rcon
credential), is derived from the seeded RNG. `mariadb-dump` uses
`INSERT INTO ... VALUES (...),...` extended-insert syntax whose row
order is InnoDB clustered-key order, which is stable for a fixed
seeder run; combined with `--skip-comments --skip-tz-utc`, the
resulting `.sql.gz` is byte-for-byte identical across re-captures
on the same dataset version.

If you observe drift in the committed `.sql.gz` between two clean
captures and want to investigate, `--skip-extended-insert` makes
each row its own `INSERT` (much larger files, but every row is
diffable line-by-line).

## Findings already known

These were noticed during fixture capture but **NOT fixed in PR #1**
(the issue's "small, sequential, easy to revert" rule). Each is a
candidate for a separate PR per [`AGENTS.md` → "Updater migrations"](../../AGENTS.md#updater-migrations):

- **`web/updater/data/801.php` is non-idempotent.** The migration
  runs `ALTER TABLE :prefix_admins ADD COLUMN attempts INT DEFAULT 0`
  (and the same for `lockout_until`) without `IF NOT EXISTS`. A
  1.7.0 install needs this because it lacks both columns; a 1.8.4
  install **already has** them from `struc.sql` (added in 705 →
  shipped with 1.8.0+). The 1.8.4 → 2.0 path will therefore hit
  `ERROR 1060 (42S21): Duplicate column name 'attempts'` and the
  updater will stop at version 705. Fix: guard each `ADD COLUMN`
  with `IF NOT EXISTS` (MariaDB 10.0+ supports this; the dev stack
  is 10.11). This is a finding for PR #2 of the dry-run series.
- **`web/upgrade.php` short-circuits whenever `SB_SECRET_KEY` is
  defined**, even if the value is the empty string an old config.php
  shipped. An empty secret renders JWT signing in 2.0's
  [`includes/auth/`](../../web/includes/auth/) deterministic-key —
  effectively unsigned tokens. Worth a sanity check on the dry-run;
  may need an additional bootstrap pass that *replaces* an empty
  `SB_SECRET_KEY` rather than only appending a missing one. Filed
  for PR #3 of the dry-run series.

These are listed here so the next agent picking up the dry-run knows
where the suspected breakage lies before they walk the path.

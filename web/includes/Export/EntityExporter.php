<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Export;

use BanRemoval;
use BanType;
use Exception;
use LogType;
use Sbpp\Db\Database;
use SteamID\SteamID;

/**
 * Per-entity SELECT + JSONL emission for the data export bundle.
 *
 * One public method per entity, each returning `iterable<string>`
 * of JSONL lines (one JSON object per line, trailing `\n`). The
 * caller ({@see BundleWriter}) wires each iterable into a
 * `ZipStream::addFileFromCallback` so the SELECT streams straight
 * out the wire without materialising the full result set in PHP
 * memory. Every read goes through {@see Database::iterate} for the
 * same reason.
 *
 * Wire contracts (uniform across every entity unless noted):
 *
 *   - **`null` for absent, never `""` and never omitted.** Every
 *     column the exporter touches gets {@see asString} treatment
 *     so an empty-string default at the schema level surfaces as
 *     JSON `null` in the export. Consumers can write to a schema
 *     with `NOT NULL` defaults more comfortably when "no value"
 *     and "empty string" can't be confused.
 *   - **Timestamps as unix-seconds `int`.** Every `int(11) created`
 *     column is `(int)`-cast verbatim; every `datetime` column
 *     (only `:prefix_admins.lockout_until` today, which we never
 *     export anyway) routes through `strtotime`. The plan's
 *     "epoch-only" contract is structural — a downstream consumer
 *     never has to choose between two date formats.
 *   - **Steam IDs as decimal-string Steam64.** Every column that
 *     stores a Steam ID (`authid` on bans / comms / admins,
 *     `SteamId` on submissions, `steam_id` on notes) routes
 *     through {@see steamPair} which returns
 *     `[authid: <decimal string>, authid_steam2: <STEAM_X:Y:Z>]`.
 *     Legacy malformed `authid` rows (pre-#1108 / #765 truncation,
 *     unset rows, "BOT", "STEAM_ID_LAN") yield `null` for both
 *     fields — the exporter never throws on a bad row, because the
 *     export is a recovery / migration surface and the operator
 *     shouldn't have to clean up legacy garbage before they can
 *     run it.
 *   - **Forbidden columns are hard-coded.** {@see FORBIDDEN_ADMIN_COLUMNS},
 *     {@see FORBIDDEN_SERVER_COLUMNS}, and {@see FORBIDDEN_SETTING_KEYS}
 *     enumerate every value the bundle MUST NEVER carry. The
 *     unit tests assert by grep that the values literally don't
 *     appear in the entity output. This list is intentionally
 *     pre-encoded — never reach for "let me read the schema and
 *     deny dynamically", because a future column addition would
 *     silently slip through that gate.
 *   - **Source PK preserved as `id` where possible.** Single-PK
 *     tables emit `id: <int>` matching the source's PK column
 *     (`:prefix_admins.aid` → `id`, `:prefix_bans.bid` → `id`,
 *     etc.). Composite-PK tables (`:prefix_banlog`,
 *     `:prefix_admins_servers_groups`, `:prefix_servers_groups`)
 *     skip `id` and preserve the join keys verbatim; the consumer
 *     reconstructs identity from the composite shape.
 *
 * Per-entity special derivations live next to their `*()` method,
 * documented inline. The headline ones:
 *
 *   - {@see comms} emits `mute_kind` ∈ {`mute`, `gag`, `silence`,
 *     `unknown`} alongside the raw `type` int.
 *   - {@see bans} emits `state` ∈ {`active`, `expired`, `unbanned`,
 *     `permanent`} derived from the same `RemoveType` + `length` +
 *     `ends` classifier `page.banlist.php` uses for the UI pill.
 *   - {@see bans} and {@see submissions} both LEFT JOIN
 *     `:prefix_demos` so a row's `demo_filename` / `demo_size_bytes`
 *     surface when the file exists on disk (and both fields are
 *     `null` otherwise — the row still ships, the demo just
 *     doesn't).
 *   - {@see log} JOINs `:prefix_admins` to surface the actor's
 *     display name + Steam64; emits `level` ∈ {`message`, `warning`,
 *     `error`} derived from `LogType`.
 */
final class EntityExporter
{
    /**
     * Columns we MUST NEVER include in the `admins` JSONL stream.
     *
     *   - `password`       Salted hash. Including it would defeat
     *                      the entire "data export is safe to
     *                      hand off" property.
     *   - `validate`       Single-use password-reset token. Live
     *                      session credential.
     *   - `attempts`       Failed-login counter — implementation
     *                      detail of the lockout flow, not panel
     *                      data the operator cares about exporting.
     *   - `lockout_until`  Same — lockout deadline driven by
     *                      `attempts`.
     *   - `srv_password`   **Plaintext** SourceMod admin server-
     *                      login password (the panel stores it
     *                      cleartext — see `account.php`'s
     *                      `api_account_check_srv_password` stringwise
     *                      compare and `sbpp_main.sp`'s admin-cache
     *                      hand-off). Exporting it is structurally
     *                      worse than exporting the bcrypt
     *                      `password` hash above: a recipient with
     *                      this value can authenticate as the
     *                      admin against every game server they
     *                      manage. The `pii_policy.password_hashes
     *                      = "never"` attestation in the manifest
     *                      makes the omission load-bearing — the
     *                      forbidden-list IS the contract that
     *                      keeps the attestation truthful.
     *
     * The actual enforcement is the per-column SELECT projection in
     * {@see admins} (the forbidden columns are NOT projected, so
     * they can never leak even through a code-path that bypasses
     * the row builder). This constant is the test-visible
     * declaration of the contract — the unit tests pull from
     * {@see forbiddenColumns} to assert by grep that none of the
     * listed names appear in the actual export.
     */
    public const FORBIDDEN_ADMIN_COLUMNS = ['password', 'validate', 'attempts', 'lockout_until', 'srv_password'];

    /**
     * Columns we MUST NEVER include in the `servers` JSONL stream.
     *
     *   - `rcon`  Server's RCON password. Recipients of an export
     *             bundle have no business holding the keys to the
     *             game servers themselves.
     *
     * See {@see FORBIDDEN_ADMIN_COLUMNS} for the enforcement
     * structure (SQL projection at the call site is the
     * load-bearing gate; this constant is the test-visible
     * declaration).
     */
    public const FORBIDDEN_SERVER_COLUMNS = ['rcon'];

    /**
     * `sb_settings.setting` keys we MUST NEVER export.
     *
     *   - `smtp.pass`             SMTP relay password.
     *   - `telemetry.instance_id` Anonymous-by-default telemetry
     *                             identifier — keeping it on the
     *                             panel of origin is the whole
     *                             point of anonymity.
     */
    public const FORBIDDEN_SETTING_KEYS = ['smtp.pass', 'telemetry.instance_id'];

    /**
     * Test-visible accessor for the forbidden-column allowlist.
     * The integration test uses this to greps the produced JSONL
     * by value rather than by hard-coded literal — a future
     * forbidden-column addition gets picked up automatically.
     *
     * @return array{admins: list<string>, servers: list<string>, settings: list<string>}
     */
    public static function forbiddenColumns(): array
    {
        return [
            'admins'   => self::FORBIDDEN_ADMIN_COLUMNS,
            'servers'  => self::FORBIDDEN_SERVER_COLUMNS,
            'settings' => self::FORBIDDEN_SETTING_KEYS,
        ];
    }

    /**
     * JSON encoder flags every entity row uses.
     *
     * `JSON_THROW_ON_ERROR` surfaces malformed payloads loudly
     * instead of silently emitting `false`. `JSON_INVALID_UTF8_SUBSTITUTE`
     * is the load-bearing flag for fault tolerance — player names on
     * `:prefix_bans.name` / `:prefix_comms.name` can carry malformed
     * UTF-8 from the pre-#1108 (#765) Latin-1-on-utf8 truncation
     * shape, and without this flag the export 500s mid-stream on a
     * hostile-historical row. Same precedent as
     * {@see \Sbpp\View\Toast::emit}; non-negotiable per the plan.
     * `JSON_UNESCAPED_*` keeps the on-disk JSONL human-readable.
     */
    private const JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly Database $dbs,
        private readonly string $demosDir,
    ) {
    }

    /**
     * Deterministic-by-name map of entity name → factory closure.
     *
     * {@see BundleWriter} iterates in `ksort` order so the bundle's
     * central-directory ordering is stable across reruns. Keep in
     * lockstep with {@see ManifestBuilder::ENTITIES}.
     *
     * @return array<string, callable(): iterable<string>>
     */
    public function entityStreams(): array
    {
        return [
            'admins'                => fn(): iterable => $this->admins(),
            'admins_servers_groups' => fn(): iterable => $this->adminsServersGroups(),
            'banlog'                => fn(): iterable => $this->banlog(),
            'bans'                  => fn(): iterable => $this->bans(),
            'comments'              => fn(): iterable => $this->comments(),
            'comms'                 => fn(): iterable => $this->comms(),
            'groups'                => fn(): iterable => $this->groups(),
            'log'                   => fn(): iterable => $this->log(),
            'mods'                  => fn(): iterable => $this->mods(),
            'notes'                 => fn(): iterable => $this->notes(),
            'overrides'             => fn(): iterable => $this->overrides(),
            'protests'              => fn(): iterable => $this->protests(),
            'servers'               => fn(): iterable => $this->servers(),
            'servers_groups'        => fn(): iterable => $this->serversGroups(),
            'settings'              => fn(): iterable => $this->settings(),
            'srvgroups'             => fn(): iterable => $this->srvgroups(),
            'srvgroups_overrides'   => fn(): iterable => $this->srvgroupsOverrides(),
            'submissions'           => fn(): iterable => $this->submissions(),
        ];
    }

    /**
     * `:prefix_admins`. Forbidden columns ({@see FORBIDDEN_ADMIN_COLUMNS})
     * are NOT projected in the SELECT, so they can never leak even
     * through a future code-path that bypasses the projection helper.
     *
     * @return iterable<string>
     */
    public function admins(): iterable
    {
        // SECURITY-REVIEW: the SELECT deliberately omits every entry in
        // FORBIDDEN_ADMIN_COLUMNS — `password` (bcrypt hash), `validate`
        // (live reset token), `attempts` / `lockout_until` (lockout state),
        // and `srv_password` (plaintext SM admin server-login password —
        // the panel stores this cleartext; exporting it would let a
        // bundle recipient authenticate as the admin against every game
        // server they manage). Keeping the projection narrow IS the
        // contract that keeps the manifest's `pii_policy.password_hashes
        // = "never"` attestation truthful.
        $this->dbs->query(
            "SELECT `aid`, `user`, `authid`, `gid`, `email`, `extraflags`, `immunity`,
                    `srv_group`, `srv_flags`, `lastvisit`
             FROM `:prefix_admins`
             ORDER BY `aid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            $steam = $this->steamPair($row['authid'] ?? null);
            yield $this->encode([
                'id'             => (int) $row['aid'],
                'user'           => $this->asString($row['user'] ?? null),
                'authid'         => $steam['authid'],
                'authid_steam2'  => $steam['authid_steam2'],
                'gid'            => (int) ($row['gid'] ?? 0),
                'email'          => $this->asString($row['email'] ?? null),
                'extraflags'     => (int) ($row['extraflags'] ?? 0),
                'immunity'       => (int) ($row['immunity'] ?? 0),
                'srv_group'      => $this->asString($row['srv_group'] ?? null),
                'srv_flags'      => $this->asString($row['srv_flags'] ?? null),
                'lastvisit'      => $row['lastvisit'] !== null ? (int) $row['lastvisit'] : null,
            ]);
        }
    }

    /**
     * `:prefix_admins_servers_groups`. Composite-PK table — no `id`
     * field, the four join keys are the identity.
     *
     * @return iterable<string>
     */
    public function adminsServersGroups(): iterable
    {
        $this->dbs->query(
            "SELECT `admin_id`, `group_id`, `srv_group_id`, `server_id`
             FROM `:prefix_admins_servers_groups`
             ORDER BY `admin_id`, `group_id`, `srv_group_id`, `server_id`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'admin_id'     => (int) $row['admin_id'],
                'group_id'     => (int) $row['group_id'],
                'srv_group_id' => (int) $row['srv_group_id'],
                'server_id'    => (int) $row['server_id'],
            ]);
        }
    }

    /**
     * `:prefix_banlog`. Composite-PK table — `(sid, time, bid)` is
     * the identity, no `id` field.
     *
     * @return iterable<string>
     */
    public function banlog(): iterable
    {
        $this->dbs->query(
            "SELECT `sid`, `time`, `name`, `bid`
             FROM `:prefix_banlog`
             ORDER BY `time`, `sid`, `bid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'sid'  => (int) $row['sid'],
                'time' => (int) $row['time'],
                'name' => $this->asString($row['name'] ?? null),
                'bid'  => (int) $row['bid'],
            ]);
        }
    }

    /**
     * `:prefix_bans`. LEFT JOIN against `:prefix_demos` for the
     * per-row demo metadata; LEFT JOIN against `:prefix_admins` so
     * we can emit the actor's display name + Steam64 alongside the
     * raw `aid` foreign key.
     *
     * Each row carries:
     *
     *   - `state` — one of `permanent` / `active` / `expired` /
     *     `unbanned`. Mirrors the same classifier `page.banlist.php`
     *     uses for the UI pill (#1352's pre-2 admin-lift defensive
     *     arm included), so consumers don't have to re-derive the
     *     logic from `RemoveType` + `RemovedBy` + `length` + `ends`.
     *   - `type` (raw int) + `type_label` ("steam" / "ip").
     *   - `demo_filename` + `demo_size_bytes` when the demos row
     *     exists AND the file is on disk (both `null` otherwise).
     *
     * @return iterable<string>
     */
    public function bans(): iterable
    {
        $this->dbs->query(
            "SELECT B.`bid`, B.`ip`, B.`authid`, B.`name`, B.`created`, B.`ends`, B.`length`,
                    B.`reason`, B.`aid`, B.`adminIp`, B.`sid`, B.`country`,
                    B.`RemovedBy`, B.`RemoveType`, B.`RemovedOn`, B.`type`, B.`ureason`,
                    D.`filename` AS `demo_filename_raw`,
                    A.`user` AS `removed_by_user`, A.`authid` AS `removed_by_authid`
             FROM `:prefix_bans` B
             LEFT JOIN `:prefix_demos` D ON D.`demid` = B.`bid` AND D.`demtype` = 'B'
             LEFT JOIN `:prefix_admins` A ON A.`aid` = B.`RemovedBy`
             ORDER BY B.`bid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            $steam        = $this->steamPair($row['authid'] ?? null);
            $banType      = BanType::tryFrom((int) ($row['type'] ?? 0));
            $banRemoval   = BanRemoval::tryFrom((string) ($row['RemoveType'] ?? ''));
            [$demoName, $demoSize] = $this->demoMetadata((string) ($row['demo_filename_raw'] ?? ''));
            $removedBy    = $row['RemovedBy'] !== null ? (int) $row['RemovedBy'] : null;
            $removedBySteam = $this->steamPair($row['removed_by_authid'] ?? null);

            yield $this->encode([
                'id'               => (int) $row['bid'],
                'ip'               => $this->asString($row['ip'] ?? null),
                'authid'           => $steam['authid'],
                'authid_steam2'    => $steam['authid_steam2'],
                'name'             => $this->asString($row['name'] ?? null),
                'created'          => (int) ($row['created'] ?? 0),
                'ends'             => (int) ($row['ends'] ?? 0),
                'length'           => (int) ($row['length'] ?? 0),
                'reason'           => $this->asString($row['reason'] ?? null),
                'aid'              => (int) ($row['aid'] ?? 0),
                'admin_ip'         => $this->asString($row['adminIp'] ?? null),
                'sid'              => (int) ($row['sid'] ?? 0),
                'country'          => $this->asString($row['country'] ?? null),
                'removed_by'       => $removedBy,
                'removed_by_user'  => $this->asString($row['removed_by_user'] ?? null),
                'removed_by_steam' => $removedBySteam['authid'],
                'remove_type'      => $banRemoval?->value,
                'removed_on'       => $row['RemovedOn'] !== null ? (int) $row['RemovedOn'] : null,
                'type'             => (int) ($row['type'] ?? 0),
                'type_label'       => $this->banTypeLabel($banType),
                'ureason'          => $this->asString($row['ureason'] ?? null),
                'state'            => $this->banState($banRemoval, $removedBy, (int) ($row['length'] ?? 0), (int) ($row['ends'] ?? 0)),
                'demo_filename'    => $demoName !== null ? 'demos/' . $demoName : null,
                'demo_size_bytes'  => $demoSize,
            ]);
        }
    }

    /**
     * `:prefix_comments`. Source PK is `cid` (rendered as `id`).
     * No special derivations — comments are flat admin-authored
     * text rows, surfaced verbatim with the same `type` letter the
     * column uses (`B` = ban, `C` = comm, `S` = submission,
     * `P` = protest).
     *
     * @return iterable<string>
     */
    public function comments(): iterable
    {
        $this->dbs->query(
            "SELECT `cid`, `bid`, `type`, `aid`, `commenttxt`, `added`, `editaid`, `edittime`
             FROM `:prefix_comments`
             ORDER BY `cid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'         => (int) $row['cid'],
                'bid'        => (int) $row['bid'],
                'type'       => $this->asString($row['type'] ?? null),
                'aid'        => (int) ($row['aid'] ?? 0),
                'commenttxt' => $this->asString($row['commenttxt'] ?? null),
                'added'      => (int) ($row['added'] ?? 0),
                'editaid'    => $row['editaid'] !== null ? (int) $row['editaid'] : null,
                'edittime'   => $row['edittime'] !== null ? (int) $row['edittime'] : null,
            ]);
        }
    }

    /**
     * `:prefix_comms`. The `mute_kind` field is the headline
     * derivation: `:prefix_comms.type` is a `tinyint` that takes
     * `1`, `2`, or `3` per the SourceMod plugin (`1 → mute`,
     * `2 → gag`, `3 → silence`). Anything else → `unknown`. The raw
     * int is preserved as `type` so a consumer that wants to apply
     * its own classifier still has the source data.
     *
     * @return iterable<string>
     */
    public function comms(): iterable
    {
        $this->dbs->query(
            "SELECT `bid`, `authid`, `name`, `created`, `ends`, `length`, `reason`,
                    `aid`, `adminIp`, `sid`, `RemovedBy`, `RemoveType`, `RemovedOn`,
                    `type`, `ureason`
             FROM `:prefix_comms`
             ORDER BY `bid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            $steam       = $this->steamPair($row['authid'] ?? null);
            $banRemoval  = BanRemoval::tryFrom((string) ($row['RemoveType'] ?? ''));
            $rawType     = (int) ($row['type'] ?? 0);

            yield $this->encode([
                'id'             => (int) $row['bid'],
                'authid'         => $steam['authid'],
                'authid_steam2'  => $steam['authid_steam2'],
                'name'           => $this->asString($row['name'] ?? null),
                'created'        => (int) ($row['created'] ?? 0),
                'ends'           => (int) ($row['ends'] ?? 0),
                'length'         => (int) ($row['length'] ?? 0),
                'reason'         => $this->asString($row['reason'] ?? null),
                'aid'            => (int) ($row['aid'] ?? 0),
                'admin_ip'       => $this->asString($row['adminIp'] ?? null),
                'sid'            => (int) ($row['sid'] ?? 0),
                'removed_by'     => $row['RemovedBy'] !== null ? (int) $row['RemovedBy'] : null,
                'remove_type'    => $banRemoval?->value,
                'removed_on'     => $row['RemovedOn'] !== null ? (int) $row['RemovedOn'] : null,
                'type'           => $rawType,
                'type_raw'       => $rawType,
                'mute_kind'      => $this->commsMuteKind($rawType),
                'ureason'        => $this->asString($row['ureason'] ?? null),
            ]);
        }
    }

    /**
     * `:prefix_groups`. Web-side admin groups (the `gid` foreign key
     * sat in `:prefix_admins.gid`).
     *
     * @return iterable<string>
     */
    public function groups(): iterable
    {
        $this->dbs->query(
            "SELECT `gid`, `type`, `name`, `flags`
             FROM `:prefix_groups`
             ORDER BY `gid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'    => (int) $row['gid'],
                'type'  => (int) ($row['type'] ?? 0),
                'name'  => $this->asString($row['name'] ?? null),
                'flags' => (int) ($row['flags'] ?? 0),
            ]);
        }
    }

    /**
     * `:prefix_log`. The audit log. Joins `:prefix_admins` to
     * surface the actor's display name + Steam64 alongside the raw
     * `aid` foreign key; emits `level` ∈ {`message` / `warning` /
     * `error`} derived from `LogType`.
     *
     * @return iterable<string>
     */
    public function log(): iterable
    {
        $this->dbs->query(
            "SELECT L.`lid`, L.`type`, L.`title`, L.`message`, L.`function`, L.`query`,
                    L.`aid`, L.`host`, L.`created`,
                    A.`user` AS `actor_user`, A.`authid` AS `actor_authid`
             FROM `:prefix_log` L
             LEFT JOIN `:prefix_admins` A ON A.`aid` = L.`aid`
             ORDER BY L.`lid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            $logType   = LogType::tryFrom((string) ($row['type'] ?? ''));
            $actorAid  = (int) ($row['aid'] ?? 0);
            $actorSteam = $this->steamPair($row['actor_authid'] ?? null);
            yield $this->encode([
                'id'           => (int) $row['lid'],
                'type'         => (string) ($row['type'] ?? ''),
                'level'        => $this->logLevel($logType),
                'title'        => $this->asString($row['title'] ?? null),
                'message'      => $this->asString($row['message'] ?? null),
                'function'     => $this->asString($row['function'] ?? null),
                'query'        => $this->asString($row['query'] ?? null),
                'aid'          => $actorAid > 0 ? $actorAid : null,
                'actor_user'   => $actorAid > 0 ? $this->asString($row['actor_user'] ?? null) : null,
                'actor_steam'  => $actorAid > 0 ? $actorSteam['authid'] : null,
                'host'         => $this->asString($row['host'] ?? null),
                'created'      => (int) ($row['created'] ?? 0),
            ]);
        }
    }

    /**
     * `:prefix_mods`. Game-mod registry rows.
     *
     * @return iterable<string>
     */
    public function mods(): iterable
    {
        $this->dbs->query(
            "SELECT `mid`, `name`, `icon`, `modfolder`, `steam_universe`, `enabled`
             FROM `:prefix_mods`
             ORDER BY `mid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'             => (int) $row['mid'],
                'name'           => $this->asString($row['name'] ?? null),
                'icon'           => $this->asString($row['icon'] ?? null),
                'modfolder'      => $this->asString($row['modfolder'] ?? null),
                'steam_universe' => (int) ($row['steam_universe'] ?? 0),
                'enabled'        => (int) ($row['enabled'] ?? 0) === 1,
            ]);
        }
    }

    /**
     * `:prefix_notes`. Source PK is `nid` (not `id` — the column
     * was named back when the convention was still per-table
     * prefixed). Preserved as both `id` (uniform across the
     * bundle) and `nid` (verbatim of the schema column name).
     *
     * The Steam-ID-keyed `steam_id` column is the actual per-row
     * identifier the player-detail drawer uses — surface both
     * forms via {@see steamPair}.
     *
     * @return iterable<string>
     */
    public function notes(): iterable
    {
        $this->dbs->query(
            "SELECT `nid`, `steam_id`, `aid`, `body`, `created`
             FROM `:prefix_notes`
             ORDER BY `nid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            $steam = $this->steamPair($row['steam_id'] ?? null);
            yield $this->encode([
                'id'              => (int) $row['nid'],
                'nid'             => (int) $row['nid'],
                'steam_id'        => $steam['authid'],
                'steam_id_steam2' => $steam['authid_steam2'],
                'aid'             => (int) ($row['aid'] ?? 0),
                'body'            => $this->asString($row['body'] ?? null),
                'created'         => (int) ($row['created'] ?? 0),
            ]);
        }
    }

    /**
     * `:prefix_overrides`. Server-side command/group overrides.
     *
     * @return iterable<string>
     */
    public function overrides(): iterable
    {
        $this->dbs->query(
            "SELECT `id`, `type`, `name`, `flags`
             FROM `:prefix_overrides`
             ORDER BY `id`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'    => (int) $row['id'],
                'type'  => $this->asString($row['type'] ?? null),
                'name'  => $this->asString($row['name'] ?? null),
                'flags' => $this->asString($row['flags'] ?? null),
            ]);
        }
    }

    /**
     * `:prefix_protests`. Ban-appeal rows.
     *
     * @return iterable<string>
     */
    public function protests(): iterable
    {
        $this->dbs->query(
            "SELECT `pid`, `bid`, `datesubmitted`, `reason`, `email`, `archiv`, `archivedby`, `pip`
             FROM `:prefix_protests`
             ORDER BY `pid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'             => (int) $row['pid'],
                'bid'            => (int) $row['bid'],
                'datesubmitted'  => (int) ($row['datesubmitted'] ?? 0),
                'reason'         => $this->asString($row['reason'] ?? null),
                'email'          => $this->asString($row['email'] ?? null),
                'archived'       => (int) ($row['archiv'] ?? 0) === 1,
                'archivedby'     => $row['archivedby'] !== null ? (int) $row['archivedby'] : null,
                'pip'            => $this->asString($row['pip'] ?? null),
            ]);
        }
    }

    /**
     * `:prefix_servers`. {@see FORBIDDEN_SERVER_COLUMNS} guarantees
     * the `rcon` column is dropped at the SQL projection level so
     * a future code-path that bypasses the column-filter helper
     * still can't leak it.
     *
     * @return iterable<string>
     */
    public function servers(): iterable
    {
        $this->dbs->query(
            "SELECT `sid`, `ip`, `port`, `modid`, `enabled`
             FROM `:prefix_servers`
             ORDER BY `sid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'      => (int) $row['sid'],
                'ip'      => $this->asString($row['ip'] ?? null),
                'port'    => (int) ($row['port'] ?? 0),
                'modid'   => (int) ($row['modid'] ?? 0),
                'enabled' => (int) ($row['enabled'] ?? 0) === 1,
            ]);
        }
    }

    /**
     * `:prefix_servers_groups`. Composite-PK table.
     *
     * @return iterable<string>
     */
    public function serversGroups(): iterable
    {
        $this->dbs->query(
            "SELECT `server_id`, `group_id`
             FROM `:prefix_servers_groups`
             ORDER BY `server_id`, `group_id`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'server_id' => (int) $row['server_id'],
                'group_id'  => (int) $row['group_id'],
            ]);
        }
    }

    /**
     * `:prefix_settings`. WHERE clause filters out the
     * {@see FORBIDDEN_SETTING_KEYS} so SMTP credentials and the
     * telemetry instance ID never reach disk.
     *
     * The plan calls out this filter being inside the SQL (not
     * post-fetch) on purpose — it's a defense-in-depth shape that
     * future modifications can't easily bypass.
     *
     * @return iterable<string>
     */
    public function settings(): iterable
    {
        $placeholders = implode(',', array_fill(0, count(self::FORBIDDEN_SETTING_KEYS), '?'));
        $this->dbs->query(
            "SELECT `id`, `setting`, `value`
             FROM `:prefix_settings`
             WHERE `setting` NOT IN ($placeholders)
             ORDER BY `id`"
        );
        $i = 1;
        foreach (self::FORBIDDEN_SETTING_KEYS as $key) {
            $this->dbs->bind($i++, $key);
        }
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'      => (int) $row['id'],
                'setting' => $this->asString($row['setting'] ?? null),
                'value'   => $this->asString($row['value'] ?? null),
            ]);
        }
    }

    /**
     * `:prefix_srvgroups`. Server-side admin groups.
     *
     * @return iterable<string>
     */
    public function srvgroups(): iterable
    {
        $this->dbs->query(
            "SELECT `id`, `flags`, `immunity`, `name`, `groups_immune`
             FROM `:prefix_srvgroups`
             ORDER BY `id`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'            => (int) $row['id'],
                'flags'         => $this->asString($row['flags'] ?? null),
                'immunity'      => (int) ($row['immunity'] ?? 0),
                'name'          => $this->asString($row['name'] ?? null),
                'groups_immune' => $this->asString($row['groups_immune'] ?? null),
            ]);
        }
    }

    /**
     * `:prefix_srvgroups_overrides`.
     *
     * @return iterable<string>
     */
    public function srvgroupsOverrides(): iterable
    {
        $this->dbs->query(
            "SELECT `id`, `group_id`, `type`, `name`, `access`
             FROM `:prefix_srvgroups_overrides`
             ORDER BY `id`"
        );
        foreach ($this->dbs->iterate() as $row) {
            yield $this->encode([
                'id'       => (int) $row['id'],
                'group_id' => (int) $row['group_id'],
                'type'     => $this->asString($row['type'] ?? null),
                'name'     => $this->asString($row['name'] ?? null),
                'access'   => $this->asString($row['access'] ?? null),
            ]);
        }
    }

    /**
     * `:prefix_submissions`. LEFT JOIN against `:prefix_demos`
     * (same shape as {@see bans} but with `demtype='S'`) so the
     * demo metadata surfaces when present.
     *
     * @return iterable<string>
     */
    public function submissions(): iterable
    {
        $this->dbs->query(
            "SELECT S.`subid`, S.`submitted`, S.`ModID`, S.`SteamId`, S.`name`, S.`email`,
                    S.`reason`, S.`ip`, S.`subname`, S.`sip`, S.`archiv`, S.`archivedby`, S.`server`,
                    D.`filename` AS `demo_filename_raw`
             FROM `:prefix_submissions` S
             LEFT JOIN `:prefix_demos` D ON D.`demid` = S.`subid` AND D.`demtype` = 'S'
             ORDER BY S.`subid`"
        );
        foreach ($this->dbs->iterate() as $row) {
            $steam = $this->steamPair($row['SteamId'] ?? null);
            [$demoName, $demoSize] = $this->demoMetadata((string) ($row['demo_filename_raw'] ?? ''));
            yield $this->encode([
                'id'              => (int) $row['subid'],
                'submitted'       => (int) ($row['submitted'] ?? 0),
                'mod_id'          => (int) ($row['ModID'] ?? 0),
                'authid'          => $steam['authid'],
                'authid_steam2'   => $steam['authid_steam2'],
                'name'            => $this->asString($row['name'] ?? null),
                'email'           => $this->asString($row['email'] ?? null),
                'reason'          => $this->asString($row['reason'] ?? null),
                'ip'              => $this->asString($row['ip'] ?? null),
                'submitter_name'  => $this->asString($row['subname'] ?? null),
                'submitter_ip'    => $this->asString($row['sip'] ?? null),
                'archived'        => (int) ($row['archiv'] ?? 0) === 1,
                'archivedby'      => $row['archivedby'] !== null ? (int) $row['archivedby'] : null,
                'server'          => $row['server'] !== null ? (int) $row['server'] : null,
                'demo_filename'   => $demoName !== null ? 'demos/' . $demoName : null,
                'demo_size_bytes' => $demoSize,
            ]);
        }
    }

    // ---- helpers ------------------------------------------------------

    /**
     * Coerce a DB column to JSON `null` for "absent" and a string
     * otherwise. The empty-string DB default counts as absent. The
     * helper is the single source for the
     * "null-for-absent-never-empty-string" contract.
     */
    private function asString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = (string) $v;
        return $s === '' ? null : $s;
    }

    /**
     * Pair a raw `:prefix_*.authid` (or `steam_id`, or `SteamId`)
     * with both wire formats. The validate-then-convert ladder
     * matches the AGENTS.md "SteamID inputs" pattern — if the raw
     * value isn't a recognisable Steam ID, both fields return
     * `null` instead of throwing. The export is a recovery /
     * migration surface; legacy garbage rows shouldn't break it.
     *
     * @return array{authid: ?string, authid_steam2: ?string}
     */
    private function steamPair(mixed $raw): array
    {
        if ($raw === null) {
            return ['authid' => null, 'authid_steam2' => null];
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return ['authid' => null, 'authid_steam2' => null];
        }
        if (!SteamID::isValidID($s)) {
            return ['authid' => null, 'authid_steam2' => null];
        }
        try {
            $steam64 = SteamID::toSteam64($s);
            $steam2  = SteamID::toSteam2($s);
        } catch (Exception) {
            // Defence-in-depth: the validate-then-convert ladder
            // guarantees we don't reach here, but a future library
            // tightening MIGHT throw on a shape isValidID accepted.
            // Fall back to "absent" rather than 500ing the export.
            return ['authid' => null, 'authid_steam2' => null];
        }
        if (!is_string($steam64) || $steam64 === '') {
            return ['authid' => null, 'authid_steam2' => null];
        }
        return [
            'authid'        => $steam64,
            'authid_steam2' => is_string($steam2) && $steam2 !== '' ? $steam2 : null,
        ];
    }

    /**
     * Pair a `:prefix_demos.filename` column value with its on-disk
     * file size, OR return `[null, null]` when the file is missing
     * / the row is empty. Path traversal is suppressed via `basename`
     * — the column is operator / plugin-controlled and a malformed
     * value mustn't walk us out of the demos directory.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function demoMetadata(string $filename): array
    {
        if ($filename === '') {
            return [null, null];
        }
        $safe = basename($filename);
        if ($safe === '' || $safe === '.' || $safe === '..') {
            return [null, null];
        }
        $path = $this->demosDir . DIRECTORY_SEPARATOR . $safe;
        if (!is_file($path)) {
            return [null, null];
        }
        $size = @filesize($path);
        if ($size === false) {
            return [null, null];
        }
        return [$safe, (int) $size];
    }

    /**
     * Map `:prefix_comms.type` (raw int) to the operator-readable
     * `mute_kind` string. See {@see comms} for the contract.
     */
    private function commsMuteKind(int $type): string
    {
        return match ($type) {
            1 => 'mute',
            2 => 'gag',
            3 => 'silence',
            default => 'unknown',
        };
    }

    /**
     * Mirror `page.banlist.php`'s ban-state classifier (including
     * the #1352 pre-2 admin-lift defensive arm). Inputs are
     * already typed at the call site so this stays a pure helper.
     */
    private function banState(?BanRemoval $removal, ?int $removedBy, int $length, int $ends): string
    {
        $isPre2AdminLift = $removal === null && ($removedBy ?? 0) > 0;
        return match (true) {
            $removal === BanRemoval::Deleted, $removal === BanRemoval::Unbanned => 'unbanned',
            $removal === BanRemoval::Expired => 'expired',
            $isPre2AdminLift                 => 'unbanned',
            $length === 0                    => 'permanent',
            $ends < time()                   => 'expired',
            default                          => 'active',
        };
    }

    private function banTypeLabel(?BanType $type): string
    {
        return match ($type) {
            BanType::Steam => 'steam',
            BanType::Ip    => 'ip',
            null           => 'unknown',
        };
    }

    private function logLevel(?LogType $type): string
    {
        return match ($type) {
            LogType::Message => 'message',
            LogType::Warning => 'warning',
            LogType::Error   => 'error',
            null             => 'unknown',
        };
    }

    /**
     * JSON-encode a row and append the JSONL line terminator.
     * Single source for the {@see JSON_FLAGS} contract — call
     * sites never `json_encode` directly.
     *
     * @param array<string, mixed> $row
     */
    private function encode(array $row): string
    {
        return json_encode($row, self::JSON_FLAGS) . "\n";
    }
}

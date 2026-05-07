<?php

namespace Sbpp\Tests;

/**
 * Dev-only synthetic data generator. Populates a freshly-installed `sourcebans`
 * dev DB with a deterministic, realistic dataset across the panel's full
 * surface: bans, comms, servers, admins, groups, submissions, protests,
 * comments, notes, banlog, and the audit log.
 *
 * Lives under `Sbpp\Tests` (next to {@see Fixture}) because it shares the
 * same bootstrap (`web/tests/bootstrap.php`), the same raw-PDO + DB_PREFIX
 * interpolation idiom, and the same "this never ships in production" risk
 * profile. Driven by `web/tests/scripts/seed-dev-db.php`, wired through
 * `./sbpp.sh db-seed`.
 *
 * Idempotent: every call truncates user-data tables first (preserving
 * `sb_settings` and `sb_mods` which `data.sql` owns), re-seeds the
 * CONSOLE + `admin/admin` rows the dev panel logs in with, then inserts
 * synthetic rows.
 *
 * Determinism contract — what same `seed` ⇒ same:
 *   - row counts, ordering, authids, names, reasons, comment / note /
 *     submission / protest / audit bodies, country codes, IPs
 *   - timestamp _spread_ (relative to the run's `$now` anchor — i.e.
 *     "ban X is 2 hours older than ban Y" reproduces; a re-seed three
 *     days later shifts both by the same amount)
 *   - high-activity cohort membership (which player indices get the
 *     populated history shape)
 *   - SteamID-form mix (which players get `[U:1:N]` vs `STEAM_1:…` vs
 *     `STEAM_0:…`)
 *
 * Intentionally non-deterministic across runs:
 *   - **Absolute timestamps**: anchored at `time()` so the dashboard's
 *     "Latest …" cards always show fresh-looking rows. Re-seeding tomorrow
 *     shifts every `created` / `ends` / `RemovedOn` / etc. by ~24h, but
 *     the spread + ordering is identical to today's run with the same seed.
 *   - **RCON tokens** (`sb_servers.rcon`): generated via
 *     `bin2hex(random_bytes(8))` so each run gets a fresh token. The
 *     server-edit form is the only surface that reads it; nothing
 *     user-visible changes.
 *   - **Admin password hashes** (`sb_admins.password`): bcrypt salts are
 *     non-deterministic by construction, but `password_verify('admin', …)`
 *     against the row holds across runs (the literal password 'admin' is
 *     deterministic; the hash is not).
 *
 * `mt_srand($seed)` pins PHP's Mersenne Twister so the deterministic
 * axes above reproduce byte-for-byte across machines.
 *
 * NOT used by the E2E suite — `Fixture::truncateAndReseed()` stays minimal
 * and the e2e DB stays empty by design (specs build the rows they need).
 *
 * Refusal: callers MUST be running against `DB_NAME=sourcebans`. The CLI
 * driver enforces this before bootstrap loads; this class re-checks at
 * `run()` entry as a belt-and-suspenders guard against accidental misuse
 * from PHP code that doesn't go through the CLI.
 */
final class Synthesizer
{
    /** Default RNG seed. Picked to match the issue number for traceability. */
    public const DEFAULT_SEED = 1238;

    /**
     * Per-table row counts per scale tier. medium >= the issue's stated
     * defaults (200 bans / 100 comms / 8 servers / 8 admins / 4 groups);
     * small/large are the fast-iterate / pagination-stress endpoints.
     *
     * @var array<string, array<string, int>>
     */
    public const SCALES = [
        'small' => [
            'players'     => 80,
            'bans'        => 30,
            'comms'       => 10,
            'servers'     => 5,
            'admins'      => 5,
            'groups'      => 3,
            'banlog'      => 50,
            'submissions' => 10,
            'protests'    => 5,
            'comments'    => 30,
            'notes'       => 15,
            'audit'       => 80,
        ],
        'medium' => [
            'players'     => 400,
            'bans'        => 200,
            'comms'       => 100,
            'servers'     => 8,
            'admins'      => 8,
            'groups'      => 4,
            'banlog'      => 400,
            'submissions' => 60,
            'protests'    => 25,
            'comments'    => 200,
            'notes'       => 120,
            'audit'       => 600,
        ],
        'large' => [
            'players'     => 3000,
            'bans'        => 2000,
            'comms'       => 800,
            'servers'     => 12,
            'admins'      => 12,
            'groups'      => 5,
            'banlog'      => 4000,
            'submissions' => 400,
            'protests'    => 150,
            'comments'    => 1500,
            'notes'       => 800,
            'audit'       => 5000,
        ],
    ];

    /**
     * Tables this synthesizer owns. Truncated on every run before the
     * synthetic dataset is inserted. Order is irrelevant under
     * SET FOREIGN_KEY_CHECKS = 0; intentionally excludes `sb_settings`
     * and `sb_mods` (both seeded by `data.sql` on fresh install).
     */
    private const SYNTH_TABLES = [
        'admins',
        'admins_servers_groups',
        'banlog',
        'bans',
        'comments',
        'comms',
        'demos',
        'groups',
        'log',
        'login_tokens',
        'notes',
        'overrides',
        'protests',
        'servers',
        'servers_groups',
        'srvgroups',
        'srvgroups_overrides',
        'submissions',
    ];

    private \PDO $pdo;
    private int $seed;
    /** @var array<string, int> */
    private array $scale;
    private int $now;

    /** @var list<int> */
    private array $adminAids = [];
    /** @var list<int> */
    private array $groupGids = [];
    /** @var list<int> */
    private array $srvGroupIds = [];
    /** @var list<int> */
    private array $serverSids = [];
    /** @var list<int> */
    private array $modIds = [];
    /** @var list<int> */
    private array $banBids = [];

    /**
     * Pool of synthetic players. Keyed by index; each entry carries a
     * pre-generated authid + display name + country so the same player
     * surfaces across bans/comms/comments/notes/audit log (history-tab
     * realism).
     *
     * @var list<array{steam: string, name: string, country: string, ip: string}>
     */
    private array $players = [];

    /**
     * Indices into {@see $players} for the "high-activity" cohort:
     * roughly the first 5% of the pool, biased to receive 3-4 bans + 2
     * comms + 2 notes apiece. Lets the drawer's per-player history /
     * notes panes always render the populated shape on a deterministic
     * subset of the pool, instead of being mostly-empty for the long
     * tail (#1243 review).
     *
     * @var list<int>
     */
    private array $highActivityIdx = [];

    /**
     * Public entrypoint. Idempotent.
     *
     * @return array<string, int> per-table insert counts (for the CLI to print)
     */
    public static function run(string $scale, int $seed): array
    {
        if (!isset(self::SCALES[$scale])) {
            throw new \InvalidArgumentException(
                "unknown scale '$scale'; expected one of: " . implode(', ', array_keys(self::SCALES))
            );
        }
        if (DB_NAME !== 'sourcebans') {
            throw new \RuntimeException(
                "Synthesizer::run refused: DB_NAME=" . DB_NAME
                . " (only 'sourcebans' is allowed; the dev DB is the only intended target)."
            );
        }
        $self = new self($scale, $seed);
        return $self->execute();
    }

    private function __construct(string $scale, int $seed)
    {
        $this->scale = self::SCALES[$scale];
        $this->seed  = $seed;
        $this->now   = time();
        $this->pdo   = $this->connect();
    }

    private function connect(): \PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        try {
            return new \PDO($dsn, DB_USER, DB_PASS, [
                \PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Synthesizer cannot connect to {$dsn}: " . $e->getMessage());
        }
    }

    /** @return array<string, int> */
    private function execute(): array
    {
        // Pin the global RNG state once; every helper below pulls from
        // the same Mersenne Twister stream so a fixed seed reproduces
        // byte-for-byte.
        mt_srand($this->seed);

        $this->truncateAndReseedBaseline();
        $this->loadModIds();
        $this->generatePlayerPool();

        $counts = [];
        $counts['groups']                = $this->insertGroups();
        $counts['srvgroups']             = $this->insertSrvGroups();
        $counts['admins']                = $this->insertAdmins();
        $counts['servers']               = $this->insertServers();
        $counts['servers_groups']        = $this->insertServersGroups();
        $counts['admins_servers_groups'] = $this->insertAdminsServersGroups();
        $counts['srvgroups_overrides']   = $this->insertSrvGroupsOverrides();
        $counts['overrides']             = $this->insertOverrides();
        $counts['bans']                  = $this->insertBans();
        $counts['banlog']                = $this->insertBanlog();
        $counts['comms']                 = $this->insertComms();
        $counts['comments']              = $this->insertComments();
        $counts['submissions']           = $this->insertSubmissions();
        $counts['protests']              = $this->insertProtests();
        $counts['notes']                 = $this->insertNotes();
        $counts['audit']                 = $this->insertAuditLog();

        return $counts;
    }

    /**
     * Drop all rows the synthesizer owns and re-seed the canonical
     * `CONSOLE` (aid=0) + `admin/admin` rows the dev panel logs in with.
     * Preserves `sb_settings` and `sb_mods` (data.sql's responsibility).
     */
    private function truncateAndReseedBaseline(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::SYNTH_TABLES as $t) {
                $this->pdo->exec(sprintf('TRUNCATE TABLE `%s_%s`', DB_PREFIX, $t));
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        // CONSOLE row (aid=0) — every Log::add(aid=0) call needs this
        // FK target. data.sql inserts it with NO_AUTO_VALUE_ON_ZERO; we
        // mirror the trick so the row keeps aid=0.
        $this->pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        $this->pdo->exec(sprintf(
            "INSERT INTO `%s_admins` (`aid`, `user`, `authid`, `password`, `gid`, `email`, `validate`, `extraflags`, `immunity`)
             VALUES (0, 'CONSOLE', 'STEAM_ID_SERVER', '', 0, '', NULL, 0, 0)",
            DB_PREFIX
        ));

        // admin / admin login — same shape Fixture::seedAdmin() and
        // docker/db-init/00-render-schema.sh use. extraflags=ADMIN_OWNER
        // (16777216) so this account survives in the same-shape state
        // the dev panel expects.
        $hash = password_hash('admin', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (`user`, `authid`, `password`, `gid`, `email`, `validate`, `extraflags`, `immunity`)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 100)',
            DB_PREFIX
        ));
        $stmt->execute(['admin', 'STEAM_0:0:0', $hash, 'admin@example.test', 16777216]);
    }

    private function loadModIds(): void
    {
        $rows = $this->pdo->query(
            sprintf('SELECT `mid` FROM `%s_mods` WHERE `mid` > 0 ORDER BY `mid`', DB_PREFIX)
        )->fetchAll(\PDO::FETCH_COLUMN);
        $this->modIds = array_map('intval', $rows);
        if ($this->modIds === []) {
            throw new \RuntimeException(
                'Synthesizer expected sb_mods seed rows from data.sql; none found. Has the dev DB been initialised?'
            );
        }
    }

    private function generatePlayerPool(): void
    {
        $names = $this->buildNamePool();
        $countries = ['US', 'GB', 'DE', 'FR', 'JP', 'KR', 'CN', 'RU', 'BR', 'AU', 'CA', 'NL', 'SE', 'PL', 'MX'];

        $count = $this->scale['players'];
        for ($i = 0; $i < $count; $i++) {
            $authNum = 1_000_000 + $i * 17;
            $authIdY = mt_rand(0, 1);
            // Three-form authid mix so the drawer's copy-button paths and
            // the format-detection regression surface (#1207 DET-1, the
            // mobile-Safari/Android tap-to-dial guard) all exercise across
            // every form bans/comms in the wild actually carry. $authNum
            // stays shared across the arms so re-seeding with a different
            // seed flips the form for the same player position rather than
            // reshuffling identities.
            //   ~10% [U:1:N]      Steam3 (drawer: copy button + tel: guard)
            //   ~10% STEAM_1:Y:N  universe-1 ("new" Source engine)
            //   ~80% STEAM_0:Y:N  universe-0 (legacy default)
            $formRoll = mt_rand(0, 99);
            if ($formRoll < 10) {
                $steam = sprintf('[U:1:%d]', $authNum);
            } elseif ($formRoll < 20) {
                $steam = sprintf('STEAM_1:%d:%d', $authIdY, $authNum);
            } else {
                $steam = sprintf('STEAM_0:%d:%d', $authIdY, $authNum);
            }
            $this->players[] = [
                'steam'   => $steam,
                'name'    => $names[$i % count($names)],
                'country' => $countries[mt_rand(0, count($countries) - 1)],
                'ip'      => sprintf(
                    '%d.%d.%d.%d',
                    mt_rand(1, 223),
                    mt_rand(0, 255),
                    mt_rand(0, 255),
                    mt_rand(1, 254)
                ),
            ];
        }

        // High-activity cohort: first ceil(5%) of the pool. Front of the
        // pool is fine because the player-pool order is itself shuffled
        // by the name-modulo and the seeded RNG; nothing downstream
        // assumes "low index = special". Bans/comms/notes pull from this
        // cohort first so each cohort player ends up with the populated
        // history shape (3-4 bans, 2 comms, 2 notes apiece on average),
        // making the drawer's per-player history / notes panes reliably
        // non-empty on a deterministic subset (#1243 review).
        $cohortSize = max(1, (int) ceil($count * 0.05));
        $this->highActivityIdx = range(0, min($cohortSize, $count) - 1);
    }

    /**
     * Build the list of player-pool indices to use for each row of a
     * given table (bans / comms / notes). Front-loads the high-activity
     * cohort so each cohort player ends up with $perCohort (+/- 1)
     * rows, then fills the remainder uniformly at random across the
     * full pool. Caps cohort allocation at $total so the returned list
     * never exceeds the scale ceiling.
     *
     * Order is intentional: cohort first, random tail second. Per-row
     * `created` timestamps are still fully randomised inside each
     * insert loop, so the dashboard's "Latest …" cards (sorted by
     * `created DESC`) still mix cohort and tail; only the bid order
     * lands cohort rows first, which is harmless.
     *
     * @return list<int>
     */
    private function scheduleTargets(int $perCohort, int $total): array
    {
        if ($total <= 0 || $this->players === []) {
            return [];
        }
        $list = [];
        foreach ($this->highActivityIdx as $idx) {
            $copies = $perCohort + mt_rand(0, 1);
            for ($k = 0; $k < $copies; $k++) {
                if (count($list) >= $total) {
                    return $list;
                }
                $list[] = $idx;
            }
        }
        $rest = $total - count($list);
        for ($k = 0; $k < $rest; $k++) {
            $list[] = mt_rand(0, count($this->players) - 1);
        }
        return $list;
    }

    /**
     * Realistic utf8mb4 name pool spanning emoji, CJK, Cyrillic, accented
     * Latin, and gamer-handle conventions. Order is intentional — the
     * head of the list shows up first in pagination, and we want
     * variety on page 1.
     *
     * @return list<string>
     */
    private function buildNamePool(): array
    {
        return [
            '🦊 Foxy McFoxface',
            '村田 太郎',
            'Иван Петров',
            'François Dubois',
            '진수 Kim',
            'xX_Sn1per_Xx',
            '小米 Wong',
            'Müller_99',
            '🎮 GamerKid',
            'pwnage_42',
            'Алексей Иванов',
            'Renée Lambert',
            '[CLAN] Pro_Player',
            'えりか',
            'Sergej Nowak',
            'Ahmed العربي',
            'maddog_2007',
            '☠️ DeathSquad',
            'Olga Sokolova',
            'sn4ke_byte',
            '李雷',
            'Søren Nielsen',
            '[ADMIN] Watcher',
            'GhostlyShade',
            'Кристина',
            'Yuki Tanaka',
            'Carlos González',
            'NoScope_King',
            'rage_quit_99',
            '🐍 Viper',
            'Hans Zimmermann',
            'スズキ',
            'flick_master',
            'fragstorm',
            'Иванка',
            'Petra Nováková',
            '匿名ユーザー',
            'lone.wolf',
            'Karim Habibi',
            'pixel_pusher',
            '🌟 Stardust',
            'Wei Liu',
            'Łukasz Kowalski',
            'Marko Petrović',
            'sniper_elite_88',
            '黑客 Hu',
            'Aurélie Bernard',
            '☢️ Toxic',
            'Mehmet Yıldız',
            'cs2_legend',
            'tf_medic_main',
            'Klaus Schmidt',
            '🍕 Pizza_Lord',
            'Valeria Rossi',
            'shadowstrike',
            '王芳',
            'Jakub Wiśniewski',
            'Élise Moreau',
            'aimbot.haver',
            '👑 KingSlayer',
            'Henrique Souza',
            'voidwalker',
            'Дмитрий',
            'silent_assassin',
            'Mateus Silva',
            'Petr Sychrov',
            '하늘',
            'cheeseburger',
            'Jürgen Bauer',
            'tactical_nuke',
            'Aleksandra Polak',
            '⚡ Lightning',
            'OGGamer1996',
            'Yusuf Aydın',
            'shaolinmaster',
            'BrandyMcCree',
            'Tomáš Dvořák',
            'snipergod999',
            'Алёна',
            '🎯 Bullseye',
            'Ahmet Çelik',
        ];
    }

    private function insertGroups(): int
    {
        // Web groups (sb_groups.type = 1). Owner gets all-access, the
        // rest carry tapered masks so the admin list shows the
        // "permission gradient" the audit + admin pages render.
        $defs = [
            ['name' => 'Owner',     'flags' => 16777216 | 4294966783],
            ['name' => 'Senior',    'flags' => 1 | 2 | 4 | 8 | 16 | 256 | 1024 | 2048 | 4096 | 32768 | 65536 | 131072],
            ['name' => 'Moderator', 'flags' => 1 | 16 | 256 | 1024 | 8192 | 16384],
            ['name' => 'Submitter', 'flags' => 1 | 16],
            ['name' => 'Trial',     'flags' => 1 | 16 | 256],
        ];

        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_groups` (`type`, `name`, `flags`) VALUES (1, ?, ?)',
            DB_PREFIX
        ));
        $count = min($this->scale['groups'], count($defs));
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([$defs[$i]['name'], $defs[$i]['flags']]);
            $this->groupGids[] = (int) $this->pdo->lastInsertId();
        }
        return $count;
    }

    private function insertSrvGroups(): int
    {
        $defs = [
            ['name' => 'sm_root',   'flags' => 'z',  'immunity' => 100],
            ['name' => 'sm_admin',  'flags' => 'bcdefijklmpq', 'immunity' => 50],
            ['name' => 'sm_mod',    'flags' => 'bdjkm', 'immunity' => 25],
        ];
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_srvgroups` (`immunity`, `flags`, `name`, `groups_immune`) VALUES (?, ?, ?, ?)',
            DB_PREFIX
        ));
        foreach ($defs as $g) {
            $stmt->execute([$g['immunity'], $g['flags'], $g['name'], ' ']);
            $this->srvGroupIds[] = (int) $this->pdo->lastInsertId();
        }
        return count($defs);
    }

    private function insertAdmins(): int
    {
        // Synth admins beyond the seeded admin/admin row. Each one gets a
        // tapered group + a small extraflags bonus so the admin list
        // shows mixed perm masks. All passwords are bcrypt of "admin"
        // so a dev can log in as any of them with the same password.
        $names = [
            'sentinel',
            'fragmaster',
            'banhammer',
            'minerva',
            'orpheus',
            'penny',
            'qwilfish',
            'rover',
            'silas',
            'tempest',
            'umbra',
            'velvet',
            'wraith',
        ];
        $count = min($this->scale['admins'], count($names));
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_admins`
                (`user`, `authid`, `password`, `gid`, `email`, `validate`, `extraflags`, `immunity`, `lastvisit`)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?)',
            DB_PREFIX
        ));
        $hash = password_hash('admin', PASSWORD_BCRYPT);

        for ($i = 0; $i < $count; $i++) {
            $name      = $names[$i];
            $gid       = $this->groupGids[$i % count($this->groupGids)];
            $extra     = mt_rand(0, 3) === 0 ? 16777216 : 0; // ~25% Owner-flagged
            $immunity  = mt_rand(0, 99);
            $lastvisit = $this->now - mt_rand(60, 60 * 60 * 24 * 30);
            $stmt->execute([
                $name,
                sprintf('STEAM_0:%d:%d', mt_rand(0, 1), 100_000 + $i * 31),
                $hash,
                $gid,
                "$name@example.test",
                $extra,
                $immunity,
                $lastvisit,
            ]);
            $this->adminAids[] = (int) $this->pdo->lastInsertId();
        }
        return $count;
    }

    private function insertServers(): int
    {
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (`ip`, `port`, `rcon`, `modid`, `enabled`) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $count = $this->scale['servers'];
        for ($i = 0; $i < $count; $i++) {
            $ip      = sprintf('10.%d.%d.%d', mt_rand(0, 255), mt_rand(0, 255), mt_rand(2, 254));
            $port    = 27015 + ($i * 2);
            $rcon    = bin2hex(random_bytes(8));
            $mod     = $this->modIds[mt_rand(0, count($this->modIds) - 1)];
            $enabled = mt_rand(0, 9) === 0 ? 0 : 1; // ~10% disabled
            $stmt->execute([$ip, $port, $rcon, $mod, $enabled]);
            $this->serverSids[] = (int) $this->pdo->lastInsertId();
        }
        return $count;
    }

    private function insertServersGroups(): int
    {
        if ($this->serverSids === [] || $this->srvGroupIds === []) {
            return 0;
        }
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_servers_groups` (`server_id`, `group_id`) VALUES (?, ?)',
            DB_PREFIX
        ));
        $n = 0;
        foreach ($this->serverSids as $sid) {
            // Each server lives in exactly one srvgroup so the admin
            // server-group page renders multiple memberships.
            $gid = $this->srvGroupIds[mt_rand(0, count($this->srvGroupIds) - 1)];
            $stmt->execute([$sid, $gid]);
            $n++;
        }
        return $n;
    }

    private function insertAdminsServersGroups(): int
    {
        if ($this->adminAids === [] || $this->serverSids === []) {
            return 0;
        }
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (`admin_id`, `group_id`, `srv_group_id`, `server_id`) VALUES (?, ?, ?, ?)',
            DB_PREFIX
        ));
        $n = 0;
        foreach ($this->adminAids as $aid) {
            // Bind each admin to ~half the servers across a mix of srvgroups
            // so the admin-server-group matrix isn't trivial.
            $bindings = max(1, intdiv(count($this->serverSids), 2));
            for ($j = 0; $j < $bindings; $j++) {
                $sid    = $this->serverSids[mt_rand(0, count($this->serverSids) - 1)];
                $sgid   = $this->srvGroupIds === [] ? 0 : $this->srvGroupIds[mt_rand(0, count($this->srvGroupIds) - 1)];
                $stmt->execute([$aid, 0, $sgid, $sid]);
                $n++;
            }
        }
        return $n;
    }

    private function insertSrvGroupsOverrides(): int
    {
        if ($this->srvGroupIds === []) {
            return 0;
        }
        $defs = [
            ['type' => 'command', 'name' => 'sm_kick',  'access' => 'allow'],
            ['type' => 'command', 'name' => 'sm_slay',  'access' => 'allow'],
            ['type' => 'command', 'name' => 'sm_ban',   'access' => 'allow'],
            ['type' => 'command', 'name' => 'sm_admin', 'access' => 'deny'],
            ['type' => 'group',   'name' => 'sm_root',  'access' => 'deny'],
        ];
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_srvgroups_overrides` (`group_id`, `type`, `name`, `access`) VALUES (?, ?, ?, ?)',
            DB_PREFIX
        ));
        $n = 0;
        foreach ($this->srvGroupIds as $sgid) {
            // Pick 1-2 overrides per srvgroup; UNIQUE KEY (group_id, type, name)
            // makes duplicates the synthesizer's responsibility to avoid.
            $picked = [];
            $want   = mt_rand(1, 2);
            while (count($picked) < $want) {
                $idx = mt_rand(0, count($defs) - 1);
                if (in_array($idx, $picked, true)) continue;
                $picked[] = $idx;
                $d = $defs[$idx];
                $stmt->execute([$sgid, $d['type'], $d['name'], $d['access']]);
                $n++;
            }
        }
        return $n;
    }

    private function insertOverrides(): int
    {
        $defs = [
            ['type' => 'command', 'name' => 'sm_kick',     'flags' => 'd'],
            ['type' => 'command', 'name' => 'sm_slay',     'flags' => 'e'],
            ['type' => 'command', 'name' => 'sm_ban',      'flags' => 'd'],
            ['type' => 'command', 'name' => 'sm_admin',    'flags' => 'a'],
            ['type' => 'group',   'name' => 'sm_root',     'flags' => 'z'],
        ];
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_overrides` (`type`, `name`, `flags`) VALUES (?, ?, ?)',
            DB_PREFIX
        ));
        foreach ($defs as $d) {
            $stmt->execute([$d['type'], $d['name'], $d['flags']]);
        }
        return count($defs);
    }

    private function insertBans(): int
    {
        $reasons = $this->buildReasonPool();
        $stmt    = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_bans`
                (`ip`, `authid`, `name`, `created`, `ends`, `length`, `reason`,
                 `aid`, `adminIp`, `sid`, `country`, `RemovedBy`, `RemoveType`,
                 `RemovedOn`, `type`, `ureason`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));

        $count       = $this->scale['bans'];
        $unbanReason = [
            'Appeal accepted, behaviour clarified.',
            'Mistaken identity confirmed via demo review.',
            'Reduced to a warning after community feedback.',
            'Probationary unban — see notes.',
            'Ban length reduced after appeal.',
        ];

        // High-activity cohort gets ~3-4 bans apiece up front; the
        // remaining slots fall back to uniform random selection.
        $targets = $this->scheduleTargets(3, $count);

        foreach ($targets as $playerIdx) {
            $player  = $this->players[$playerIdx];
            $isIpOnly = mt_rand(0, 9) === 0; // ~10% IP-only bans

            $created = $this->now - mt_rand(0, 60 * 60 * 24 * 90);

            // Length distribution: 25% permanent, 75% timed (1d-180d).
            $length = mt_rand(0, 3) === 0 ? 0 : mt_rand(60 * 60 * 24, 60 * 60 * 24 * 180);
            $ends   = $length === 0 ? 0 : $created + $length;

            // State distribution: 60% active (incl. permanent), 25% expired,
            // 15% admin-removed (RemoveType U/D + RemovedBy + ureason).
            $roll       = mt_rand(0, 99);
            $removeType = null;
            $removedBy  = null;
            $removedOn  = null;
            $ureason    = '';
            if ($roll < 60) {
                // Active. Force ends > now if timed.
                if ($length !== 0 && $ends < $this->now) {
                    $created = $this->now - mt_rand(0, $length - 60 * 60 * 24);
                    $ends    = $created + $length;
                }
            } elseif ($roll < 85) {
                // Expired (timed, ends < now). Skip permanent rows here.
                if ($length === 0) {
                    $length = 60 * 60 * 24 * mt_rand(1, 30);
                    $ends   = $created + $length;
                }
                if ($ends > $this->now) {
                    $created = $this->now - $length - mt_rand(60, 60 * 60 * 24 * 7);
                    $ends    = $created + $length;
                }
            } else {
                // Admin-removed (~15%).
                $removeType = mt_rand(0, 1) === 0 ? 'U' : 'D';
                $removedBy  = $this->randomAdminAid();
                $removedOn  = $created + mt_rand(60 * 60, 60 * 60 * 24 * 7);
                if ($removedOn > $this->now) {
                    $removedOn = $this->now - mt_rand(60, 60 * 60 * 24);
                }
                $ureason    = $unbanReason[mt_rand(0, count($unbanReason) - 1)];
            }

            $stmt->execute([
                $isIpOnly ? $player['ip'] : (mt_rand(0, 4) === 0 ? $player['ip'] : null),
                $isIpOnly ? '' : $player['steam'],
                $player['name'],
                $created,
                $ends,
                $length,
                $reasons[mt_rand(0, count($reasons) - 1)],
                $this->randomAdminAid(),
                $this->randomAdminIp(),
                $this->randomServerSid(),
                $player['country'],
                $removedBy,
                $removeType,
                $removedOn,
                $isIpOnly ? 1 : 0,
                $ureason !== '' ? $ureason : null,
            ]);
            $this->banBids[] = (int) $this->pdo->lastInsertId();
        }
        return count($targets);
    }

    private function insertBanlog(): int
    {
        if ($this->banBids === [] || $this->serverSids === []) {
            return 0;
        }
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT IGNORE INTO `%s_banlog` (`sid`, `time`, `name`, `bid`) VALUES (?, ?, ?, ?)',
            DB_PREFIX
        ));
        $count = $this->scale['banlog'];
        $n     = 0;
        for ($i = 0; $i < $count; $i++) {
            $bid    = $this->banBids[mt_rand(0, count($this->banBids) - 1)];
            $sid    = $this->randomServerSid();
            $time   = $this->now - mt_rand(0, 60 * 60 * 24 * 30);
            $player = $this->players[mt_rand(0, count($this->players) - 1)];
            // PK is (sid, time, bid); INSERT IGNORE absorbs the rare collision.
            $ok = $stmt->execute([$sid, $time, $player['name'], $bid]);
            if ($ok && $stmt->rowCount() > 0) {
                $n++;
            }
        }
        return $n;
    }

    private function insertComms(): int
    {
        $reasons = $this->buildCommReasonPool();
        $stmt    = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_comms`
                (`authid`, `name`, `created`, `ends`, `length`, `reason`, `aid`,
                 `adminIp`, `sid`, `RemovedBy`, `RemoveType`, `RemovedOn`,
                 `type`, `ureason`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));

        $count = $this->scale['comms'];
        // Cohort gets ~2 comms apiece up front; remainder is uniform.
        $targets = $this->scheduleTargets(2, $count);

        foreach ($targets as $playerIdx) {
            $player  = $this->players[$playerIdx];
            $created = $this->now - mt_rand(0, 60 * 60 * 24 * 60);
            $length  = mt_rand(0, 3) === 0 ? 0 : mt_rand(60 * 30, 60 * 60 * 24 * 14);
            $ends    = $length === 0 ? 0 : $created + $length;

            $roll       = mt_rand(0, 99);
            $removeType = null;
            $removedBy  = null;
            $removedOn  = null;
            $ureason    = null;
            if ($roll < 60) {
                if ($length !== 0 && $ends < $this->now) {
                    $created = $this->now - mt_rand(0, max(60, $length - 60 * 60));
                    $ends    = $created + $length;
                }
            } elseif ($roll < 85) {
                if ($length === 0) {
                    $length = 60 * 60 * mt_rand(1, 24);
                    $ends   = $created + $length;
                }
                if ($ends > $this->now) {
                    $created = $this->now - $length - mt_rand(60, 60 * 60 * 6);
                    $ends    = $created + $length;
                }
            } else {
                $removeType = 'U';
                $removedBy  = $this->randomAdminAid();
                $removedOn  = $created + mt_rand(60, 60 * 60 * 24);
                if ($removedOn > $this->now) {
                    $removedOn = $this->now - mt_rand(60, 60 * 60 * 24);
                }
                $ureason    = 'Apologised; comm restored.';
            }

            $stmt->execute([
                $player['steam'],
                $player['name'],
                $created,
                $ends,
                $length,
                $reasons[mt_rand(0, count($reasons) - 1)],
                $this->randomAdminAid(),
                $this->randomAdminIp(),
                $this->randomServerSid(),
                $removedBy,
                $removeType,
                $removedOn,
                mt_rand(1, 2), // 1 = mute, 2 = gag
                $ureason,
            ]);
        }
        return count($targets);
    }

    private function insertComments(): int
    {
        if ($this->banBids === []) {
            return 0;
        }
        $count = $this->scale['comments'];
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_comments` (`bid`, `type`, `aid`, `commenttxt`, `added`) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        // Long-form Markdown bodies live alongside the short pool so the
        // drawer's Comments pane exercises its truncation/wrap chrome on
        // a deterministic subset of comments, not just the one-liners.
        // Shape mirrors the long-reason entry in buildReasonPool().
        $longInvestigation = "**Investigation summary** — three sessions of demo review:\n\n"
            . "1. Round 1 (`de_inferno`): clean prefires through smoke at A-site, but only on enemies. "
            . "Suspicious; not conclusive.\n"
            . "2. Round 2 (`de_mirage`): tracking through walls during the pre-round freeze. "
            . "_This_ is the smoking gun — no in-game info available.\n"
            . "3. Round 3 (`de_dust2`): aim snap to head from B-tunnels through the doorway, target "
            . "fully off-screen. Frame 17:42-17:44.\n\n"
            . "Recommendation: keep the perma. Player has appealed twice already on alts (#812, #944) "
            . "with the same denial pattern.";
        $longContext = "Long-time community member; checked context before the ban:\n\n"
            . "- 6 years on the server, 0 prior bans\n"
            . "- Active in our Discord, helps new players\n"
            . "- Reportedly going through some personal stuff in voice channels last month\n\n"
            . "**Recommendation**: reduce from perma to 14d cooldown + DM check-in when it lifts. "
            . "If the toxic streak continues post-cooldown we revisit. Note added to player profile.";
        $longCrossLink = "Cross-server intel from EU staff (`@modteam-eu`):\n\n"
            . "Player is currently serving a 30d ban on the EU comp server for the same wallhack "
            . "pattern (`STEAM_0:1:88421`). Confirmed via Discord DM with their head admin.\n\n"
            . "**Action**: matching our ban length to theirs (30d), updating ureason to reflect the "
            . "cross-server enforcement. Adding linked-accounts note for future moderators.";
        $bodies = [
            'Demo confirms aimbot — see frame 14:32.',
            'Player apologised in DMs, recommend leaving ban.',
            "Multi-account abuse. Linked to ban #1042 by IP overlap.\nNo prior history but pattern is clear.",
            'Reduced from perm to 7d after appeal.',
            'Repeat offender — third ban this month.',
            'Nothing in chat logs supports the report. Closing.',
            "Community vote: keep ban.\nUnanimous in #mod-channel.",
            'Steam profile private; player ignored DM follow-up.',
            'Friend list overlaps with banned alt accounts.',
            'Wall-banging through textures — see attached demo.',
            "Mass team kill x4 in two consecutive rounds.\nClassic griefing pattern.",
            'Player is banned on the EU server too — checking with that staff.',
            "Discord screenshot shows them admitting to cheats.\nLeaving ban as is.",
            'Reduced to 24h — first offence.',
            "Long-time community member, out-of-character behaviour.\n7d cooldown then revisit.",
            $longInvestigation,
            $longContext,
            $longCrossLink,
        ];
        for ($i = 0; $i < $count; $i++) {
            $bid     = $this->banBids[mt_rand(0, count($this->banBids) - 1)];
            $aid     = $this->randomAdminAid();
            $body    = $bodies[mt_rand(0, count($bodies) - 1)];
            // type='B' is the ban-comment type the public ban page renders.
            $stmt->execute([$bid, 'B', $aid, $body, $this->now - mt_rand(60, 60 * 60 * 24 * 60)]);
        }
        return $count;
    }

    private function insertSubmissions(): int
    {
        $count = $this->scale['submissions'];
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
                (`submitted`, `ModID`, `SteamId`, `name`, `email`, `reason`,
                 `ip`, `subname`, `sip`, `archiv`, `archivedby`, `server`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $reasons = [
            'Wallhacking on de_dust2, see linked demo.',
            'Voice abuse and racist slurs in comms.',
            "Repeated team-killing.\nIgnored multiple warnings.",
            'Aimbot — clearly snapping to heads through smoke.',
            'Sprayed offensive content in spawn.',
            'Trolling by blocking doorways for an entire round.',
            'Threatening behaviour in DMs after the round.',
            'Cheating with fly + speed hacks.',
        ];
        for ($i = 0; $i < $count; $i++) {
            $player = $this->players[mt_rand(0, count($this->players) - 1)];
            $sub    = $this->players[mt_rand(0, count($this->players) - 1)];
            // archiv distribution: 40% pending (0), 30% archived/handled (1),
            // 20% restored (2), 10% banned-from-submission (3).
            $roll = mt_rand(0, 99);
            $archiv = $roll < 40 ? 0 : ($roll < 70 ? 1 : ($roll < 90 ? 2 : 3));
            $archivedBy = $archiv === 0 ? null : $this->randomAdminAid();
            $stmt->execute([
                $this->now - mt_rand(0, 60 * 60 * 24 * 60),
                $this->modIds[mt_rand(0, count($this->modIds) - 1)],
                $player['steam'],
                $player['name'],
                'reporter+' . mt_rand(1, 999) . '@example.test',
                $reasons[mt_rand(0, count($reasons) - 1)],
                $player['ip'],
                $sub['name'],
                $sub['ip'],
                $archiv,
                $archivedBy,
                mt_rand(0, max(1, count($this->serverSids))),
            ]);
        }
        return $count;
    }

    private function insertProtests(): int
    {
        if ($this->banBids === []) {
            return 0;
        }
        $count = $this->scale['protests'];
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_protests`
                (`bid`, `datesubmitted`, `reason`, `email`, `archiv`, `archivedby`, `pip`)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $bodies = [
            "I was banned by mistake. I'd just joined the server and didn't even fire a shot.",
            "My little brother was using my account. Banning me directly is unfair.\nHe won't play again.",
            'Banned for "wallhack" but I was using the radar info my teammate called out.',
            "I admit the chat was over the line, but the perma ban is excessive.\nI've been on this server for 4 years.",
            "The demo will show this is a false positive. I've never used cheats.",
            'Ban length seems disproportionate to the offence — please review.',
        ];
        $n = 0;
        for ($i = 0; $i < $count; $i++) {
            $bid    = $this->banBids[mt_rand(0, count($this->banBids) - 1)];
            $archiv = mt_rand(0, 99) < 60 ? 0 : 1;
            $archivedBy = $archiv === 0 ? null : $this->randomAdminAid();
            $stmt->execute([
                $bid,
                $this->now - mt_rand(0, 60 * 60 * 24 * 45),
                $bodies[mt_rand(0, count($bodies) - 1)],
                'appeal+' . mt_rand(1, 999) . '@example.test',
                $archiv,
                $archivedBy,
                sprintf('192.0.2.%d', mt_rand(1, 250)),
            ]);
            $n++;
        }
        return $n;
    }

    private function insertNotes(): int
    {
        if ($this->adminAids === []) {
            return 0;
        }
        $count = $this->scale['notes'];
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_notes` (`steam_id`, `aid`, `body`, `created`) VALUES (?, ?, ?, ?)',
            DB_PREFIX
        ));
        $bodies = [
            'Repeat ban evader. Watch for new alts.',
            'Long-time donor. Be lenient on first offence.',
            "Asperger's-spectrum, may misread sarcasm.\nSee #mod for context.",
            'Speaks limited English; consider language ban over kick.',
            'Has had three appeals declined this year.',
            'Friendly with admin team — usually self-corrects after a poke.',
            "Linked accounts:\n- STEAM_0:0:9988\n- STEAM_0:0:1122",
            'Toxic in voice but never in text — mute-only ban worked last time.',
            'Reported by multiple sources but I cannot find evidence in demos.',
            "Notable for clutch plays.\nHistory of frustration tilts; not malicious.",
        ];
        // Cohort gets ~2 notes apiece up front; remainder is uniform.
        $targets = $this->scheduleTargets(2, $count);

        foreach ($targets as $playerIdx) {
            $player = $this->players[$playerIdx];
            $aid    = $this->randomAdminAid();
            $body   = $bodies[mt_rand(0, count($bodies) - 1)];
            $stmt->execute([$player['steam'], $aid, $body, $this->now - mt_rand(60, 60 * 60 * 24 * 90)]);
        }
        return count($targets);
    }

    private function insertAuditLog(): int
    {
        $count = $this->scale['audit'];
        $stmt  = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s_log` (`type`, `title`, `message`, `function`, `query`, `aid`, `host`, `created`)
             VALUES (?, ?, ?, "synthetic-data", "", ?, ?, ?)',
            DB_PREFIX
        ));
        // Mix of action types so the audit log filter dropdown
        // (admin / message / type / date) has something to match.
        $events = [
            ['m', 'Ban Added',       'admin added a ban for player'],
            ['m', 'Ban Edited',      'admin edited a ban'],
            ['m', 'Ban Unbanned',    'admin unbanned a player'],
            ['m', 'Comment Added',   'admin added a comment for ban'],
            ['m', 'Comment Edited',  'admin edited comment'],
            ['m', 'Server Added',    'admin added server'],
            ['m', 'Server Edited',   'admin edited server'],
            ['m', 'Mod Added',       'admin added mod'],
            ['m', 'Group Added',     'admin added group'],
            ['m', 'Group Edited',    'admin edited group'],
            ['m', 'Admin Added',     'admin added admin'],
            ['m', 'Admin Edited',    'admin edited admin'],
            ['m', 'Setting Updated', 'admin updated setting'],
            ['m', 'Submission Archived', 'admin archived submission'],
            ['m', 'Protest Archived',    'admin archived protest'],
            ['w', 'Ban Failed',      'failed to add ban: server unreachable'],
            ['w', 'RCON Failed',     'rcon command timed out'],
            ['w', 'Permission Denied', 'admin attempted action without flag'],
            ['e', 'Database Error',  'unexpected SQL state'],
            ['e', 'Auth Failure',    'invalid login attempt'],
        ];
        for ($i = 0; $i < $count; $i++) {
            $ev      = $events[mt_rand(0, count($events) - 1)];
            $aid     = $this->randomAdminAid();
            $created = $this->now - mt_rand(0, 60 * 60 * 24 * 60);
            $host    = sprintf('192.0.2.%d', mt_rand(1, 250));
            $stmt->execute([$ev[0], $ev[1], $ev[2] . ' #' . mt_rand(1, 9999), $aid, $host, $created]);
        }
        return $count;
    }

    /**
     * @return list<string>
     */
    private function buildReasonPool(): array
    {
        $long = "Player exhibited persistent griefing behaviour over multiple maps:\n"
            . "- Repeated team kills at round start\n"
            . "- Voice spam (`siren.wav` looped) for ~30 seconds\n"
            . "- Refused to acknowledge admin warnings\n\n"
            . "After the third warning the ban was issued. Demo attached. Severity bumped to 30d due to prior history (#1042, #918).";
        return [
            'cheating',
            'wallhack',
            'aimbot',
            'racism',
            'griefing',
            "Voice abuse + racial slurs in comms.\nMultiple reports.",
            "**Aimbot** confirmed via demo at frame 14:32.\n_Locked on through smoke._",
            'Spinbot — see uploaded demo `attack_round3.dem`.',
            "Mass team-kill x6 in 2 minutes\nignored repeated warnings.",
            'Spawn-camping with C4 — denied multiple rounds in a row.',
            'Threats made against admin team in DMs after kick.',
            "Repeat offender — fourth ban for the same SteamID alt.\nGroup-banning related accounts in follow-up.",
            'Inappropriate sprays (NSFW).',
            'Exploiting map geometry to wallbang through unintended surfaces.',
            $long,
            'Bug exploit (rocket-jumping out of map bounds repeatedly).',
            "Toxic chat in two consecutive matches.\nFirst: lifetime stats abuse. Second: targeted slurs.",
            "Macro-bound bunnyhop + air-strafe automation.\nDetected via input timing.",
            'Discord harassment of clan member; cross-server enforcement.',
        ];
    }

    /**
     * @return list<string>
     */
    private function buildCommReasonPool(): array
    {
        return [
            'voice spam',
            'racial slurs',
            'mic spam (music)',
            "Repeated targeted harassment in voice chat.\nEscalated after first warning.",
            'Inappropriate language in family hours.',
            "**Politics** in chat after warning. 24h gag.",
            'Sexual harassment of another player.',
            'Voice changer used to impersonate an admin.',
            'Hate speech in chat — zero tolerance policy.',
            "Ad spam linking to a competing server.\nFollow-up perma if repeated.",
        ];
    }

    private function randomAdminAid(): int
    {
        // Mix in aid=0 (CONSOLE) occasionally so the audit log has a
        // realistic "system" actor in addition to human admins.
        if ($this->adminAids === [] || mt_rand(0, 19) === 0) {
            return 0;
        }
        return $this->adminAids[mt_rand(0, count($this->adminAids) - 1)];
    }

    private function randomAdminIp(): string
    {
        // Stable pool of admin source IPs — a handful of admins logging
        // in from a small set of locations keeps the audit log readable.
        return sprintf('203.0.113.%d', mt_rand(1, 20));
    }

    private function randomServerSid(): int
    {
        if ($this->serverSids === []) {
            return 0;
        }
        return $this->serverSids[mt_rand(0, count($this->serverSids) - 1)];
    }
}

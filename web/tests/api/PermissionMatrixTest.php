<?php

namespace Sbpp\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Per-action permission-matrix lock (#1112).
 *
 * Every registered action's `(perm, requireAdmin, public)` triple is
 * pinned in `expectedMatrix()` below so a silent change in
 * `web/api/handlers/_register.php` (forgetting to gate a new action,
 * accidentally widening an existing one, dropping the `public` flag from
 * `auth.login`) fails the build loudly. This file is the wire-format
 * contract for what the dispatcher will let through unauthenticated.
 *
 * Companion `testEveryRegisteredActionIsCoveredByTheMatrix` cross-checks
 * that every action reachable via `Api::actions()` also has a row here,
 * so a NEW action without an entry below also fails — intentional. If
 * you add an action to `_register.php`, add its row to `expectedMatrix()`
 * with the gate you want locked in.
 *
 * This class extends `\PHPUnit\Framework\TestCase` directly (not
 * `ApiTestCase`) so the per-row data provider doesn't pay the cost of
 * `Fixture::reset()` on every dataset — it doesn't touch the DB.
 */
final class PermissionMatrixTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Bootstrap once. Api::bootstrap() is idempotent and only requires
        // the handler files; no DB connection is opened.
        \Api::bootstrap();
    }

    /**
     * Source of truth for every public/baseline/permissioned action.
     *
     * @return array<string, array{perm: int|string, requireAdmin: bool, public: bool}>
     */
    public static function expectedMatrix(): array
    {
        // Constants resolved at bootstrap (see web/configs/permissions/*.json).
        return [
            // -- account: dispatcher login baseline; handler enforces aid match.
            'account.check_password'      => ['perm' => 0, 'requireAdmin' => false, 'public' => false],
            'account.change_password'     => ['perm' => 0, 'requireAdmin' => false, 'public' => false],
            'account.check_srv_password'  => ['perm' => 0, 'requireAdmin' => false, 'public' => false],
            'account.change_srv_password' => ['perm' => 0, 'requireAdmin' => false, 'public' => false],
            'account.change_email'        => ['perm' => 0, 'requireAdmin' => false, 'public' => false],

            // -- admins.
            'admins.add'                  => ['perm' => ADMIN_OWNER | ADMIN_ADD_ADMINS,    'requireAdmin' => false, 'public' => false],
            'admins.remove'               => ['perm' => ADMIN_OWNER | ADMIN_DELETE_ADMINS, 'requireAdmin' => false, 'public' => false],
            'admins.edit_perms'           => ['perm' => ADMIN_OWNER | ADMIN_EDIT_ADMINS,   'requireAdmin' => false, 'public' => false],
            'admins.update_perms'         => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'admins.generate_password'    => ['perm' => 0, 'requireAdmin' => true,  'public' => false],

            // -- auth (only public surface).
            'auth.login'                  => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'auth.lost_password'          => ['perm' => 0, 'requireAdmin' => false, 'public' => true],

            // -- bans.
            'bans.add'                    => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            // bans.detail is intentionally public: same reach as the public
            // ban-list page. The handler hides admin-only fields itself
            // (player IP, admin name, removed-by, comments) so the wire
            // format stays consistent with what the HTML page would have
            // shown the same caller. See api_bans_detail() docblock.
            'bans.detail'                 => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            // bans.player_history is public for the same reason
            // bans.detail is — the drawer's History tab matches the public
            // ban-list reach. Admin-only fields (admin name, removed-by)
            // are gated inside the handler.
            'bans.player_history'         => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'bans.setup_ban'              => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'bans.prepare_reban'          => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'bans.paste'                  => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'bans.add_comment'            => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'bans.edit_comment'           => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'bans.remove_comment'         => ['perm' => ADMIN_OWNER, 'requireAdmin' => false, 'public' => false],
            'bans.group_ban'              => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'bans.ban_member_of_group'    => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'bans.ban_friends'            => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'bans.get_groups'             => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'bans.kick_player'            => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'bans.send_message'           => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'bans.view_community'         => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'bans.search'                 => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            // bans.unban drives the visible row action on the public ban
            // list (#1301). Dispatcher gate is "any unban-ish flag"; the
            // handler then enforces the per-row own/group precision check
            // that the legacy `?p=banlist&a=unban` GET path uses (and
            // requires a non-empty `ureason` so the audit log carries it).
            'bans.unban'                  => [
                'perm' => ADMIN_OWNER | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS,
                'requireAdmin' => false, 'public' => false,
            ],

            // -- blockit.
            'blockit.load_servers'        => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'blockit.block_player'        => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],

            // -- comms.
            'comms.add'                       => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'comms.prepare_reblock'           => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'comms.paste'                     => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'comms.prepare_block_from_ban'    => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            // comms.detail is intentionally public for the same reason
            // bans.detail is — same reach as the public commslist page,
            // hide-* gating enforced inside the handler. Powers the
            // player drawer when opened from a comms-list row.
            'comms.detail'                    => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            // comms.player_history follows bans.player_history (#1165).
            'comms.player_history'            => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            // comms.unblock + comms.delete drive the visible row actions
            // on the comms list (#1207 ADM-5/ADM-6). Dispatcher gate for
            // unblock is "any unban-ish flag"; the handler then enforces
            // the per-row own/group precision check that the legacy
            // `?p=commslist&a=ungag|unmute` GET path uses.
            'comms.unblock'                   => [
                'perm' => ADMIN_OWNER | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS,
                'requireAdmin' => false, 'public' => false,
            ],
            'comms.delete'                    => ['perm' => ADMIN_OWNER | ADMIN_DELETE_BAN, 'requireAdmin' => false, 'public' => false],

            // -- groups.
            'groups.add'                      => ['perm' => ADMIN_OWNER | ADMIN_ADD_GROUP,    'requireAdmin' => false, 'public' => false],
            'groups.remove'                   => ['perm' => ADMIN_OWNER | ADMIN_DELETE_GROUPS,'requireAdmin' => false, 'public' => false],
            'groups.edit'                     => ['perm' => ADMIN_OWNER | ADMIN_EDIT_GROUPS,  'requireAdmin' => false, 'public' => false],
            'groups.update_perms'             => ['perm' => 0, 'requireAdmin' => true, 'public' => false],
            'groups.add_server_group_name'    => ['perm' => ADMIN_OWNER | ADMIN_EDIT_GROUPS,  'requireAdmin' => false, 'public' => false],

            // -- kickit.
            'kickit.load_servers'             => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],
            'kickit.kick_player'              => ['perm' => ADMIN_OWNER | ADMIN_ADD_BAN, 'requireAdmin' => false, 'public' => false],

            // -- mods.
            'mods.add'                        => ['perm' => ADMIN_OWNER | ADMIN_ADD_MODS,    'requireAdmin' => false, 'public' => false],
            'mods.remove'                     => ['perm' => ADMIN_OWNER | ADMIN_DELETE_MODS, 'requireAdmin' => false, 'public' => false],

            // -- notes (player-detail drawer's Notes tab, #1165). Admin-only
            // surface — the drawer hides the tab for non-admins via the
            // `notes_visible` flag in `bans.detail`.
            'notes.list'                      => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'notes.add'                       => ['perm' => 0, 'requireAdmin' => true,  'public' => false],
            'notes.delete'                    => ['perm' => 0, 'requireAdmin' => true,  'public' => false],

            // -- protests.
            'protests.remove'                 => ['perm' => ADMIN_OWNER | ADMIN_BAN_PROTESTS, 'requireAdmin' => false, 'public' => false],

            // -- servers.
            'servers.add'                     => ['perm' => ADMIN_OWNER | ADMIN_ADD_SERVER,    'requireAdmin' => false, 'public' => false],
            'servers.remove'                  => ['perm' => ADMIN_OWNER | ADMIN_DELETE_SERVERS,'requireAdmin' => false, 'public' => false],
            'servers.setup_edit'              => ['perm' => ADMIN_OWNER | ADMIN_EDIT_SERVERS, 'requireAdmin' => false, 'public' => false],
            'servers.refresh'                 => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'servers.host_players'            => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'servers.host_property'           => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'servers.host_players_list'       => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'servers.players'                 => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            // SM_RCON . SM_ROOT — concatenation matters; the dispatcher
            // forwards strings to HasAccess() for SourceMod char matching.
            'servers.send_rcon'               => ['perm' => SM_RCON . SM_ROOT, 'requireAdmin' => false, 'public' => false],

            // -- submissions.
            'submissions.remove'              => ['perm' => ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS, 'requireAdmin' => false, 'public' => false],

            // -- system.
            'system.rehash_admins'            => [
                'perm' => ADMIN_OWNER | ADMIN_EDIT_ADMINS | ADMIN_EDIT_GROUPS | ADMIN_ADD_ADMINS,
                'requireAdmin' => false, 'public' => false,
            ],
            'system.send_mail'                => [
                'perm' => ADMIN_OWNER | ADMIN_BAN_PROTESTS | ADMIN_BAN_SUBMISSIONS,
                'requireAdmin' => false, 'public' => false,
            ],
            'system.check_version'            => ['perm' => 0, 'requireAdmin' => false, 'public' => true],
            'system.sel_theme'                => ['perm' => ADMIN_OWNER | ADMIN_WEB_SETTINGS, 'requireAdmin' => false, 'public' => false],
            'system.apply_theme'              => ['perm' => ADMIN_OWNER | ADMIN_WEB_SETTINGS, 'requireAdmin' => false, 'public' => false],
            'system.clear_cache'              => ['perm' => ADMIN_OWNER | ADMIN_WEB_SETTINGS, 'requireAdmin' => false, 'public' => false],
            'system.preview_intro_text'       => ['perm' => ADMIN_OWNER | ADMIN_WEB_SETTINGS, 'requireAdmin' => false, 'public' => false],
        ];
    }

    public function testEveryRegisteredActionIsCoveredByTheMatrix(): void
    {
        $expected = self::expectedMatrix();

        $registered = \Api::actions();
        sort($registered);
        $expectedKeys = array_keys($expected);
        sort($expectedKeys);

        $extraInRegistry = array_values(array_diff($registered,    $expectedKeys));
        $missingFromCode = array_values(array_diff($expectedKeys,  $registered));

        $this->assertSame([], $extraInRegistry,
            'New actions registered without an entry in PermissionMatrixTest::expectedMatrix(): '
            . implode(', ', $extraInRegistry)
            . '. Add them with the gate you want locked in.');
        $this->assertSame([], $missingFromCode,
            'expectedMatrix() lists actions that are no longer registered: '
            . implode(', ', $missingFromCode));
    }

    /**
     * Yields one dataset per registered action so a single failing row only
     * marks that one action as broken in the test report.
     *
     * @return array<string, array{0: string, 1: int|string, 2: bool, 3: bool}>
     */
    public static function matrixDataProvider(): array
    {
        $out = [];
        foreach (self::expectedMatrix() as $action => $row) {
            $out[$action] = [$action, $row['perm'], $row['requireAdmin'], $row['public']];
        }
        return $out;
    }

    #[DataProvider('matrixDataProvider')]
    public function testRegisteredPermissionMaskMatches(string $action, int|string $perm, bool $requireAdmin, bool $public): void
    {
        $entry = \Api::lookup($action);
        $this->assertNotNull($entry, "action $action not registered (matrix mismatch?)");
        $this->assertSame($perm,         $entry['perm'],         "$action perm mask drift");
        $this->assertSame($requireAdmin, $entry['requireAdmin'], "$action requireAdmin drift");
        $this->assertSame($public,       $entry['public'],       "$action public drift");
    }
}

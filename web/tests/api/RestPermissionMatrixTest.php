<?php

namespace Sbpp\Tests\Api;

use PHPUnit\Framework\TestCase;
use Sbpp\Rest\Routes;

/**
 * Every REST v1 route is locked here. Adding a route without a row fails.
 */
final class RestPermissionMatrixTest extends TestCase
{
    /**
     * @return list<array{method: string, path: string, auth: bool, perm: int|string}>
     */
    public static function expectedRoutes(): array
    {
        $readAdmins = ADMIN_OWNER | ADMIN_LIST_ADMINS | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS;
        $writeAdmins = ADMIN_OWNER | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS;
        $deleteAdmins = ADMIN_OWNER | ADMIN_DELETE_ADMINS;
        $readGroups = ADMIN_OWNER | ADMIN_LIST_GROUPS | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS;
        $rehash = ADMIN_OWNER | ADMIN_EDIT_ADMINS | ADMIN_EDIT_GROUPS | ADMIN_ADD_ADMINS;
        $addBan = ADMIN_OWNER | ADMIN_ADD_BAN;
        $unban = ADMIN_OWNER | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS;
        $deleteBan = ADMIN_OWNER | ADMIN_DELETE_BAN;
        $addServer = ADMIN_OWNER | ADMIN_ADD_SERVER;
        $editServer = ADMIN_OWNER | ADMIN_EDIT_SERVERS;
        $deleteServer = ADMIN_OWNER | ADMIN_DELETE_SERVERS;
        $anyAdmin = ALL_WEB;
        $readMods = ADMIN_OWNER | ADMIN_LIST_MODS | ADMIN_ADD_MODS | ADMIN_EDIT_MODS;
        $addMod = ADMIN_OWNER | ADMIN_ADD_MODS;
        $deleteMod = ADMIN_OWNER | ADMIN_DELETE_MODS;

        return [
            ['method' => 'GET', 'path' => '/openapi.yaml', 'auth' => false, 'perm' => 0],
            ['method' => 'GET', 'path' => '/me', 'auth' => true, 'perm' => 0],
            ['method' => 'GET', 'path' => '/admins', 'auth' => true, 'perm' => $readAdmins],
            ['method' => 'GET', 'path' => '/admins/{id}', 'auth' => true, 'perm' => $readAdmins],
            ['method' => 'PUT', 'path' => '/admins/{id}', 'auth' => true, 'perm' => $writeAdmins],
            ['method' => 'PATCH', 'path' => '/admins/{id}', 'auth' => true, 'perm' => ADMIN_OWNER | ADMIN_EDIT_ADMINS],
            ['method' => 'POST', 'path' => '/admins/{id}/deactivate', 'auth' => true, 'perm' => $deleteAdmins],
            ['method' => 'POST', 'path' => '/admins/{id}/reactivate', 'auth' => true, 'perm' => $deleteAdmins],
            ['method' => 'DELETE', 'path' => '/admins/{id}', 'auth' => true, 'perm' => $deleteAdmins],
            ['method' => 'GET', 'path' => '/groups', 'auth' => true, 'perm' => $readGroups],
            ['method' => 'POST', 'path' => '/system/rehash', 'auth' => true, 'perm' => $rehash],
            ['method' => 'GET', 'path' => '/bans', 'auth' => false, 'perm' => 0],
            ['method' => 'POST', 'path' => '/bans', 'auth' => true, 'perm' => $addBan],
            ['method' => 'GET', 'path' => '/bans/{bid}', 'auth' => false, 'perm' => 0],
            ['method' => 'POST', 'path' => '/bans/{bid}/unban', 'auth' => true, 'perm' => $unban],
            ['method' => 'GET', 'path' => '/comms', 'auth' => false, 'perm' => 0],
            ['method' => 'POST', 'path' => '/comms', 'auth' => true, 'perm' => $addBan],
            ['method' => 'GET', 'path' => '/comms/{cid}', 'auth' => false, 'perm' => 0],
            ['method' => 'POST', 'path' => '/comms/{cid}/unblock', 'auth' => true, 'perm' => $unban],
            ['method' => 'DELETE', 'path' => '/comms/{cid}', 'auth' => true, 'perm' => $deleteBan],
            ['method' => 'GET', 'path' => '/servers', 'auth' => false, 'perm' => 0],
            ['method' => 'POST', 'path' => '/servers', 'auth' => true, 'perm' => $addServer],
            ['method' => 'GET', 'path' => '/servers/{sid}', 'auth' => false, 'perm' => 0],
            ['method' => 'PATCH', 'path' => '/servers/{sid}', 'auth' => true, 'perm' => $editServer],
            ['method' => 'DELETE', 'path' => '/servers/{sid}', 'auth' => true, 'perm' => $deleteServer],
            ['method' => 'POST', 'path' => '/servers/{sid}/rcon', 'auth' => true, 'perm' => SM_RCON . SM_ROOT],
            ['method' => 'GET', 'path' => '/notes', 'auth' => true, 'perm' => $anyAdmin],
            ['method' => 'POST', 'path' => '/notes', 'auth' => true, 'perm' => $anyAdmin],
            ['method' => 'DELETE', 'path' => '/notes/{nid}', 'auth' => true, 'perm' => $anyAdmin],
            ['method' => 'GET', 'path' => '/mods', 'auth' => true, 'perm' => $readMods],
            ['method' => 'POST', 'path' => '/mods', 'auth' => true, 'perm' => $addMod],
            ['method' => 'GET', 'path' => '/mods/{mid}', 'auth' => true, 'perm' => $readMods],
            ['method' => 'DELETE', 'path' => '/mods/{mid}', 'auth' => true, 'perm' => $deleteMod],
        ];
    }

    public function testRegisteredRoutesMatchExpectedMatrix(): void
    {
        $actual = [];
        foreach (Routes::all() as $route) {
            $actual[] = [
                'method' => $route['method'],
                'path' => $route['path'],
                'auth' => $route['auth'],
                'perm' => $route['perm'],
            ];
        }
        $this->assertSame(self::expectedRoutes(), $actual);
    }

    public function testEveryWriteRouteRequiresAuthAndAPermission(): void
    {
        foreach (Routes::all() as $route) {
            if (!in_array($route['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                continue;
            }
            $label = $route['method'] . ' ' . $route['path'];
            $this->assertTrue($route['auth'], $label . ' must require a PAT');
            $this->assertNotSame(0, $route['perm'], $label . ' must declare a permission mask');
        }
    }
}

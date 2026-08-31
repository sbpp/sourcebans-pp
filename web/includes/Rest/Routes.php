<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\ApiError;
use Sbpp\Auth\UserManager;
use WebPermission;

/**
 * Slice 0 REST route table. Reviewable like `api/handlers/_register.php`.
 *
 * @phpstan-import-type Route from Router
 */
final class Routes
{
    /**
     * @return list<Route>
     */
    public static function all(): array
    {
        $readAdmins = ADMIN_OWNER | ADMIN_LIST_ADMINS | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS;
        $writeAdmins = ADMIN_OWNER | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS;
        $deleteAdmins = ADMIN_OWNER | ADMIN_DELETE_ADMINS;
        $readGroups = ADMIN_OWNER | ADMIN_LIST_GROUPS | ADMIN_ADD_ADMINS | ADMIN_EDIT_ADMINS;
        $rehash = ADMIN_OWNER | ADMIN_EDIT_ADMINS | ADMIN_EDIT_GROUPS | ADMIN_ADD_ADMINS;

        return [
            [
                'method' => 'GET',
                'path' => '/openapi.yaml',
                'auth' => false,
                'perm' => 0,
                'handler' => self::openapi(...),
            ],
            [
                'method' => 'GET',
                'path' => '/me',
                'auth' => true,
                'perm' => 0,
                'handler' => self::me(...),
            ],
            [
                'method' => 'GET',
                'path' => '/admins',
                'auth' => true,
                'perm' => $readAdmins,
                'handler' => self::adminsList(...),
            ],
            [
                'method' => 'GET',
                'path' => '/admins/{id}',
                'auth' => true,
                'perm' => $readAdmins,
                'handler' => self::adminsGet(...),
            ],
            [
                'method' => 'PUT',
                'path' => '/admins/{id}',
                'auth' => true,
                'perm' => $writeAdmins,
                'handler' => self::adminsPut(...),
            ],
            [
                'method' => 'PATCH',
                'path' => '/admins/{id}',
                'auth' => true,
                'perm' => ADMIN_OWNER | ADMIN_EDIT_ADMINS,
                'handler' => self::adminsPatch(...),
            ],
            [
                'method' => 'POST',
                'path' => '/admins/{id}/deactivate',
                'auth' => true,
                'perm' => $deleteAdmins,
                'handler' => self::adminsDeactivate(...),
            ],
            [
                'method' => 'POST',
                'path' => '/admins/{id}/reactivate',
                'auth' => true,
                'perm' => $deleteAdmins,
                'handler' => self::adminsReactivate(...),
            ],
            [
                'method' => 'DELETE',
                'path' => '/admins/{id}',
                'auth' => true,
                'perm' => $deleteAdmins,
                'handler' => self::adminsDelete(...),
            ],
            [
                'method' => 'GET',
                'path' => '/groups',
                'auth' => true,
                'perm' => $readGroups,
                'handler' => self::groupsList(...),
            ],
            [
                'method' => 'POST',
                'path' => '/system/rehash',
                'auth' => true,
                'perm' => $rehash,
                'handler' => self::systemRehash(...),
            ],
        ];
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function openapi(array $params, array $body, array $query): Response
    {
        $path = ROOT . 'api/openapi-v1.yaml';
        if (!is_file($path)) {
            throw new ApiError('not_found', 'OpenAPI spec is not available.', null, 404);
        }
        $yaml = file_get_contents($path);
        if ($yaml === false) {
            throw new ApiError('server_error', 'OpenAPI spec could not be read.', null, 500);
        }
        return Envelope::yaml($yaml);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function me(array $params, array $body, array $query): Response
    {
        /** @var UserManager $userbank */
        $userbank = $GLOBALS['userbank'];
        $admin = (new AdminsService())->get(new AdminId($userbank->GetAid(), null));
        return Envelope::ok($admin);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsList(array $params, array $body, array $query): Response
    {
        $result = (new AdminsService())->list($query);
        return Envelope::ok($result['data'], $result['meta']);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsGet(array $params, array $body, array $query): Response
    {
        $admin = (new AdminsService())->get(AdminId::parse($params['id'] ?? ''));
        return Envelope::ok($admin);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsPut(array $params, array $body, array $query): Response
    {
        $id = AdminId::parse($params['id'] ?? '');
        self::assertWritePerm($id);
        return (new AdminsService())->upsert($id, $body, 'PUT');
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsPatch(array $params, array $body, array $query): Response
    {
        $id = AdminId::parse($params['id'] ?? '');
        return (new AdminsService())->upsert($id, $body, 'PATCH');
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsDeactivate(array $params, array $body, array $query): Response
    {
        $reason = trim((string) ($body['reason'] ?? ''));
        $result = (new AdminsService())->deactivate(AdminId::parse($params['id'] ?? ''), $reason);
        return Envelope::ok($result['admin'], ['rehash' => $result['rehash']]);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsReactivate(array $params, array $body, array $query): Response
    {
        $reason = trim((string) ($body['reason'] ?? ''));
        $result = (new AdminsService())->reactivate(AdminId::parse($params['id'] ?? ''), $reason);
        return Envelope::ok($result['admin'], ['rehash' => $result['rehash']]);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function adminsDelete(array $params, array $body, array $query): Response
    {
        $reason = trim((string) ($body['reason'] ?? ''));
        $result = (new AdminsService())->remove(AdminId::parse($params['id'] ?? ''), $reason);
        return Envelope::ok(['id' => $result['deleted']], ['rehash' => $result['rehash']]);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function groupsList(array $params, array $body, array $query): Response
    {
        return Envelope::ok((new GroupsService())->list());
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function systemRehash(array $params, array $body, array $query): Response
    {
        $sids = [];
        if (isset($body['sids']) && is_array($body['sids'])) {
            foreach ($body['sids'] as $sid) {
                if (is_numeric($sid)) {
                    $sids[] = (int) $sid;
                }
            }
        }
        if ($sids === []) {
            $sids = Rehasher::allEnabledSids();
        }
        return Envelope::ok(['rehash' => Rehasher::run($sids)]);
    }

    /**
     * PUT create requires ADD_ADMINS. PUT update requires EDIT_ADMINS.
     */
    private static function assertWritePerm(AdminId $id): void
    {
        /** @var UserManager $userbank */
        $userbank = $GLOBALS['userbank'];
        if ($userbank->HasAccess(WebPermission::Owner)) {
            return;
        }
        $exists = true;
        try {
            (new AdminsService())->get($id);
        } catch (ApiError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
            $exists = false;
        }
        if (!$exists) {
            if (!$userbank->HasAccess(WebPermission::AddAdmins)) {
                throw new ApiError('forbidden', 'No access', null, 403);
            }
            return;
        }
        if (!$userbank->HasAccess(WebPermission::EditAdmins)) {
            throw new ApiError('forbidden', 'No access', null, 403);
        }
    }
}

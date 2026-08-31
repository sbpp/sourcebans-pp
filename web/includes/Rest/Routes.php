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
 * REST v1 route table. Reviewable like `api/handlers/_register.php`.
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
        $addBan = ADMIN_OWNER | ADMIN_ADD_BAN;
        $unban = ADMIN_OWNER | ADMIN_UNBAN | ADMIN_UNBAN_OWN_BANS | ADMIN_UNBAN_GROUP_BANS;
        $deleteBan = ADMIN_OWNER | ADMIN_DELETE_BAN;

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
            [
                'method' => 'GET',
                'path' => '/bans',
                'auth' => false,
                'perm' => 0,
                'handler' => self::bansList(...),
            ],
            [
                'method' => 'POST',
                'path' => '/bans',
                'auth' => true,
                'perm' => $addBan,
                'handler' => self::bansCreate(...),
            ],
            [
                'method' => 'GET',
                'path' => '/bans/{bid}',
                'auth' => false,
                'perm' => 0,
                'handler' => self::bansGet(...),
            ],
            [
                'method' => 'POST',
                'path' => '/bans/{bid}/unban',
                'auth' => true,
                'perm' => $unban,
                'handler' => self::bansUnban(...),
            ],
            [
                'method' => 'GET',
                'path' => '/comms',
                'auth' => false,
                'perm' => 0,
                'handler' => self::commsList(...),
            ],
            [
                'method' => 'POST',
                'path' => '/comms',
                'auth' => true,
                'perm' => $addBan,
                'handler' => self::commsCreate(...),
            ],
            [
                'method' => 'GET',
                'path' => '/comms/{cid}',
                'auth' => false,
                'perm' => 0,
                'handler' => self::commsGet(...),
            ],
            [
                'method' => 'POST',
                'path' => '/comms/{cid}/unblock',
                'auth' => true,
                'perm' => $unban,
                'handler' => self::commsUnblock(...),
            ],
            [
                'method' => 'DELETE',
                'path' => '/comms/{cid}',
                'auth' => true,
                'perm' => $deleteBan,
                'handler' => self::commsDelete(...),
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
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function bansList(array $params, array $body, array $query): Response
    {
        $result = (new BansService())->list($query);
        return Envelope::ok($result['data'], $result['meta']);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function bansGet(array $params, array $body, array $query): Response
    {
        return Envelope::ok((new BansService())->get(self::positiveId($params['bid'] ?? '', 'bid')));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function bansCreate(array $params, array $body, array $query): Response
    {
        $result = (new BansService())->create($body);
        $meta = [];
        if ($result['kick'] !== null) {
            $meta['kick'] = $result['kick'];
        }
        return Envelope::ok($result['ban'], $meta, 201);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function bansUnban(array $params, array $body, array $query): Response
    {
        $bid = self::positiveId($params['bid'] ?? '', 'bid');
        $ureason = trim((string) ($body['ureason'] ?? ''));
        return Envelope::ok((new BansService())->unban($bid, $ureason));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function commsList(array $params, array $body, array $query): Response
    {
        $result = (new CommsService())->list($query);
        return Envelope::ok($result['data'], $result['meta']);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function commsGet(array $params, array $body, array $query): Response
    {
        return Envelope::ok((new CommsService())->get(self::positiveId($params['cid'] ?? '', 'cid')));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function commsCreate(array $params, array $body, array $query): Response
    {
        return Envelope::ok((new CommsService())->create($body), [], 201);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function commsUnblock(array $params, array $body, array $query): Response
    {
        $cid = self::positiveId($params['cid'] ?? '', 'cid');
        $ureason = trim((string) ($body['ureason'] ?? ''));
        return Envelope::ok((new CommsService())->unblock($cid, $ureason));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private static function commsDelete(array $params, array $body, array $query): Response
    {
        return Envelope::ok((new CommsService())->delete(self::positiveId($params['cid'] ?? '', 'cid')));
    }

    private static function positiveId(string $raw, string $field): int
    {
        if (preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
            throw new ApiError('validation', $field . ' must be a positive integer.', $field, 400);
        }
        return (int) $raw;
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

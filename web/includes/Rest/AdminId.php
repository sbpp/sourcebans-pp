<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Api\ApiError;
use SteamID\SteamID;

/**
 * Path `{id}` for `/admins/{id}`: numeric aid, or a 17-digit Steam64
 * that starts with 7 and round-trips through Steam2. Steam2 / Steam3
 * in the path is 400.
 */
final class AdminId
{
    public function __construct(
        public readonly ?int $aid,
        public readonly ?string $steam64,
    ) {
    }

    public static function parse(string $segment): self
    {
        if (preg_match('/^7\d{16}$/D', $segment) === 1) {
            try {
                $steam2 = SteamID::toSteam2($segment);
            } catch (\Exception) {
                throw self::invalidId();
            }
            if (!is_string($steam2) || !SteamID::isValidID($steam2)) {
                throw self::invalidId();
            }
            $back = SteamID::toSteam64($steam2);
            if ((string) $back !== $segment) {
                throw self::invalidId();
            }
            return new self(null, $segment);
        }
        if (preg_match('/^[1-9]\d{0,8}$/D', $segment) === 1) {
            return new self((int) $segment, null);
        }
        throw self::invalidId();
    }

    private static function invalidId(): ApiError
    {
        return new ApiError(
            'validation',
            'Admin id must be a numeric aid or a 17-digit Steam64.',
            'id',
            400,
        );
    }

    public function isSteam64(): bool
    {
        return $this->steam64 !== null;
    }
}

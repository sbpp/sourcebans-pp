<?php
// PHPStan-only helper. Autoloaded via the `phpstan/` classmap entry in
// web/composer.json (see SbppSyntaxErrorInQueryMethodRule for the longer
// note); never reached at runtime.

declare(strict_types=1);

namespace Sbpp\PHPStan;

use PHPStan\Type\Type;
use staabm\PHPStanDba\Error;
use staabm\PHPStanDba\QueryReflection\DbaApi;
use staabm\PHPStanDba\QueryReflection\QueryReflector;

/**
 * No-op {@see QueryReflector} used when the schema-only DB is unavailable
 * (PHPSTAN_DBA_DISABLE=1, or the `db` service is down).
 *
 * phpstan-dba's bundled rules call {@see QueryReflection::setupReflector()}
 * eagerly during DI wiring; if no reflector has been registered they crash
 * with "Reflector not initialized" the first time a query node is visited.
 * Returning `null` from every introspection method neutralises those rules
 * without requiring us to surgically unregister them in the neon, so the
 * non-DBA half of the gate keeps running unchanged.
 */
final class SbppNullReflector implements QueryReflector
{
    public function validateQueryString(string $queryString): ?Error
    {
        return null;
    }

    public function getResultType(string $queryString, int $fetchType): ?Type
    {
        return null;
    }

    public function setupDbaApi(?DbaApi $dbaApi): void
    {
    }
}

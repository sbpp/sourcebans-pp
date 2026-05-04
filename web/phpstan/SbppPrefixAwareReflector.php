<?php
// PHPStan-only helper. Autoloaded via the `phpstan/` classmap entry in
// web/composer.json (see SbppSyntaxErrorInQueryMethodRule for the longer
// note); never reached at runtime.

declare(strict_types=1);

namespace Sbpp\PhpStan;

use PHPStan\Type\Type;
use staabm\PHPStanDba\Error;
use staabm\PHPStanDba\QueryReflection\DbaApi;
use staabm\PHPStanDba\QueryReflection\QueryReflector;

/**
 * Decorates a phpstan-dba {@see QueryReflector} so that the project's
 * `:prefix_<table>` placeholder is resolved before the SQL hits the database.
 *
 * The runtime equivalent is `Database::setPrefix()`, which does the same
 * `str_replace(':prefix', $this->prefix, $query)` substitution before
 * preparing the statement. We mirror that here so phpstan-dba sees fully
 * qualified table names like `sb_admins` instead of `:prefix_admins` (which
 * would otherwise look like a prepared-statement placeholder followed by an
 * undeclared identifier).
 */
final class SbppPrefixAwareReflector implements QueryReflector
{
    public function __construct(
        private readonly QueryReflector $inner,
        private readonly string $prefix,
    ) {
    }

    public function validateQueryString(string $queryString): ?Error
    {
        return $this->inner->validateQueryString($this->resolvePrefix($queryString));
    }

    public function getResultType(string $queryString, int $fetchType): ?Type
    {
        return $this->inner->getResultType($this->resolvePrefix($queryString), $fetchType);
    }

    public function setupDbaApi(?DbaApi $dbaApi): void
    {
        $this->inner->setupDbaApi($dbaApi);
    }

    private function resolvePrefix(string $queryString): string
    {
        return str_replace(':prefix', $this->prefix, $queryString);
    }
}

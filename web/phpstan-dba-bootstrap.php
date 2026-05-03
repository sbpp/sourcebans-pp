<?php
// Loaded only by PHPStan (see phpstan.neon `bootstrapFiles`).
//
// Wires staabm/phpstan-dba up against a MariaDB instance so PHPStan can
// type-check the SQL strings flowing through Database::query(). Queries are
// simulated inside a transaction that is rolled back, and writes are
// rewritten as EXPLAIN, so no data is persisted — any schema-only copy
// works. Typically that's the dev container's `db` service (./sbpp.sh up)
// or a fresh CI MariaDB seeded from web/install/includes/sql/struc.sql.
//
// Connection failures fall back to a no-op reflector so plain
// `./sbpp.sh phpstan` keeps working without the docker stack. CI sets
// DBA_REQUIRE=1 so a credentials drift or missing service surfaces as a
// hard failure instead of silently disabling the gate.
// Set PHPSTAN_DBA_DISABLE=1 to bypass entirely (useful when iterating on
// non-DBA rules).

declare(strict_types=1);

use Sbpp\PhpStan\SbppNullReflector;
use Sbpp\PhpStan\SbppPrefixAwareReflector;
use staabm\PHPStanDba\QueryReflection\PdoMysqlQueryReflector;
use staabm\PHPStanDba\QueryReflection\QueryReflection;
use staabm\PHPStanDba\QueryReflection\QueryReflector;
use staabm\PHPStanDba\QueryReflection\RuntimeConfiguration;

require_once __DIR__ . '/includes/vendor/autoload.php';
require_once __DIR__ . '/phpstan/SbppNullReflector.php';
require_once __DIR__ . '/phpstan/SbppPrefixAwareReflector.php';
require_once __DIR__ . '/phpstan/SbppSyntaxErrorInQueryMethodRule.php';

// phpstan-dba registers rules during DI bootstrap that eagerly look up the
// reflector via QueryReflection::setupReflector(); leaving it unset crashes
// the analyser with "Reflector not initialized" the first time a query node
// is visited. So even when we're skipping real SQL analysis we install a
// no-op reflector to keep those rules quiet.
$config = RuntimeConfiguration::create()
    ->stringifyTypes(true)
    ->defaultFetchMode(QueryReflector::FETCH_TYPE_ASSOC);

if (getenv('PHPSTAN_DBA_DISABLE') === '1') {
    fwrite(STDERR, "[phpstan-dba] PHPSTAN_DBA_DISABLE=1, skipping SQL analysis\n");
    QueryReflection::setupReflector(new SbppNullReflector(), $config);
    return;
}

$host    = getenv('DBA_HOST') ?: '127.0.0.1';
$port    = (int) (getenv('DBA_PORT') ?: '3306');
$user    = getenv('DBA_USER') ?: 'sourcebans';
$pass    = getenv('DBA_PASS') ?: 'sourcebans';
$dbname  = getenv('DBA_NAME') ?: 'sourcebans';
$prefix  = getenv('DBA_PREFIX') ?: 'sb';
$charset = getenv('DBA_CHARSET') ?: 'utf8mb4';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $message = sprintf(
        '[phpstan-dba] could not connect to %s@%s:%d/%s (%s)',
        $user, $host, $port, $dbname, $e->getMessage()
    );

    // CI is expected to set DBA_REQUIRE=1: we want the gate to FAIL on
    // credentials drift or a missing service rather than silently noop and
    // report green. Locally the default (unset) keeps the soft-fall-back
    // behaviour so plain `./sbpp.sh phpstan` works without the stack.
    if (getenv('DBA_REQUIRE') === '1') {
        fwrite(STDERR, $message . " — DBA_REQUIRE=1, aborting\n");
        exit(1);
    }

    fwrite(STDERR, $message . "; skipping SQL analysis\n");
    QueryReflection::setupReflector(new SbppNullReflector(), $config);
    return;
}

QueryReflection::setupReflector(
    new SbppPrefixAwareReflector(new PdoMysqlQueryReflector($pdo), $prefix),
    $config
);

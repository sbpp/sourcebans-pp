<?php

namespace Sbpp\Db;

use Generator;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class Database
 */
final class Database
{
    public const MAX_PREPARED_STATEMENT_PLACEHOLDERS = 65_535;

    /**
     * Keep generated IN-list statements comfortably below MySQL /
     * MariaDB's 65,535-placeholder prepared-statement ceiling. Fixed
     * parameters that appear before or after the list also count toward
     * that ceiling, so callers must not build the list themselves.
     */
    public const IN_LIST_CHUNK_SIZE = 10_000;

    private readonly string $prefix;

    private PDO $dbh;

    private ?PDOStatement $stmt = null;

    /**
     * Running count of `query()` calls, i.e. distinct SQL statements
     * prepared since the last {@see resetQueryCount()}. Every call
     * site in the codebase funnels through `query()` before it can
     * `execute()` / `resultset()` / `single()` / `iterate()`, so this
     * is a single choke point for counting logical database round
     * trips regardless of which page handler, API handler, or helper
     * issued the SQL.
     *
     * Static (not per-instance) because pages construct `Database`
     * once per request via `$GLOBALS['PDO']`, but tests that build a
     * fresh instance (e.g. to probe a specific query in isolation)
     * still want the count visible from the same place. The counter
     * is a single `int` increment per call: negligible on production
     * request paths, and it exists so PHPUnit tests can assert a
     * page/handler issues a bounded number of queries independent of
     * row count, instead of relying on flaky wall-clock timing.
     */
    private static int $queryCount = 0;

    public static function resetQueryCount(): void
    {
        self::$queryCount = 0;
    }

    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }

    public function __construct(string $host, int $port, string $dbname, string $user, string $password, string $prefix, string $charset = 'utf8')
    {
        $this->prefix = $prefix;
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=' . $charset;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Native (non-emulated) prepares: PDOStatement::execute([...])
            // forwards values via MySQL's binary protocol with proper
            // type metadata, instead of literal-substituting them as
            // strings client-side. The latter breaks `LIMIT ?,?` (MariaDB
            // rejects `LIMIT '0','30'` as a syntax error) — a regression
            // that surfaced once page.banlist.php / page.commslist.php
            // started running on PDO post-#1092 (the ADOdb→PDO refactor)
            // and went unnoticed until the e2e suite first exercised the
            // public ban list with rows present (#1124 Slice 3). Existing
            // call sites that go through Database::bind() already auto-
            // detect PARAM_INT for ints, so this is purely additive on
            // the array-shortcut path.
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->dbh = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            // `die($e->getMessage())` used to sit here. Two problems with
            // that shape: (1) it leaks the raw PDO message — hostname,
            // db name, sometimes the DSN — to whatever's reading the
            // process output (a page visitor's browser on the web SAPI,
            // or a shell script's captured stdout on the CLI SAPI); (2)
            // `exit($string)` exits with status 0, not a failure code, so
            // any caller checking the exit status (e.g. the production
            // entrypoint's headless updater-migration runner) sees
            // "succeeded" and continues booting against a DB it never
            // actually reached.
            error_log('[Sbpp\Db\Database] connection failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Database connection failed. See error log for detail.\n");
                exit(1);
            }
            http_response_code(500);
            die('Database connection failed. Please contact the site administrator.');
        }
    }

    public function __destruct()
    {
        unset($this->dbh);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    private function setPrefix(string $query): string
    {
        $query = str_replace(':prefix', $this->prefix, $query);
        return $query;
    }

    /**
     * Contrary to the name, this prepares the query and doesn't actually run the query.
     */
    public function query(string $query): self
    {
        self::$queryCount++;
        $query = $this->setPrefix($query);
        $this->stmt = $this->dbh->prepare($query);
        return $this;
    }

    /**
     * @param int|null $type PDO param type. Send null or leave blank for auto-detection.
     */
    public function bind(int|string $param, int|bool|null|string $value, ?int $type = null): void
    {
        if ($type === null) {
            $type = match (true) {
                is_int($value)   => PDO::PARAM_INT,
                is_bool($value)  => PDO::PARAM_BOOL,
                $value === null  => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };
        }

        $this->stmt->bindValue($param, $value, $type);
    }

    public function bindMultiple(array $params = []): void
    {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    public function execute(?array $inputParams = null): bool
    {
        return $this->stmt->execute($inputParams);
    }

    public function resultset(?array $inputParams = null, int $fetchType = PDO::FETCH_ASSOC): array
    {
        $this->execute($inputParams);
        return $this->stmt->fetchAll($fetchType);
    }

    /**
     * Execute a SELECT once per bounded slice of an IN-list and merge
     * the rows. Ordering is guaranteed within each slice only; callers
     * should consume the result as a set or regroup it by key.
     *
     * `$sqlBeforeValues` must end immediately before the first generated
     * placeholder and `$sqlAfterValues` must begin immediately after the
     * last one. For example:
     *
     *     $db->resultsetInList(
     *         'SELECT aid, user FROM `:prefix_admins` WHERE aid IN (',
     *         $aids,
     *         ')',
     *     );
     *
     * @param list<int|bool|null|string> $values
     * @param list<int|bool|null|string> $paramsBefore
     * @param list<int|bool|null|string> $paramsAfter
     * @return list<mixed>
     * @throws \InvalidArgumentException When a keyed PDO fetch mode is requested.
     */
    public function resultsetInList(
        string $sqlBeforeValues,
        array $values,
        string $sqlAfterValues = '',
        array $paramsBefore = [],
        array $paramsAfter = [],
        int $fetchType = PDO::FETCH_ASSOC,
    ): array {
        if (!in_array($fetchType, [PDO::FETCH_ASSOC, PDO::FETCH_COLUMN], true)) {
            throw new \InvalidArgumentException(
                'Chunked IN-list SELECTs support only PDO::FETCH_ASSOC and PDO::FETCH_COLUMN.'
            );
        }

        $rows = [];
        foreach ($this->inListChunks($values, count($paramsBefore) + count($paramsAfter)) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $chunkRows = $this
                ->query($sqlBeforeValues . $placeholders . $sqlAfterValues)
                ->resultset([...$paramsBefore, ...$chunk, ...$paramsAfter], $fetchType);
            array_push($rows, ...$chunkRows);
        }

        return $rows;
    }

    /**
     * Execute a write once per bounded slice of an IN-list.
     *
     * Pass `$atomic = true` when every chunk must commit or roll back as
     * one operation. The caller must not already have a transaction open
     * in that mode because PDO does not support nested transactions.
     *
     * @param list<int|bool|null|string> $values
     * @param list<int|bool|null|string> $paramsBefore
     * @param list<int|bool|null|string> $paramsAfter
     */
    public function executeInList(
        string $sqlBeforeValues,
        array $values,
        string $sqlAfterValues = '',
        array $paramsBefore = [],
        array $paramsAfter = [],
        bool $atomic = false,
    ): int {
        $chunks = $this->inListChunks($values, count($paramsBefore) + count($paramsAfter));
        if ($chunks === []) {
            return 0;
        }

        $affected = 0;
        $transactionOpen = false;
        if ($atomic) {
            if ($this->dbh->inTransaction()) {
                throw new \LogicException('Atomic IN-list execution cannot start inside an existing transaction.');
            }
            $this->beginTransaction();
            $transactionOpen = true;
        }
        try {
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $this
                    ->query($sqlBeforeValues . $placeholders . $sqlAfterValues)
                    ->execute([...$paramsBefore, ...$chunk, ...$paramsAfter]);
                $affected += $this->rowCount();
            }
            if ($atomic) {
                $this->endTransaction();
                $transactionOpen = false;
            }
        } catch (\Throwable $e) {
            if ($transactionOpen) {
                $this->cancelTransaction();
            }
            throw $e;
        }

        return $affected;
    }

    /**
     * @param list<int|bool|null|string> $values
     * @return list<list<int|bool|null|string>>
     */
    private function inListChunks(array $values, int $reservedPlaceholders): array
    {
        if ($values === []) {
            return [];
        }

        $available = self::MAX_PREPARED_STATEMENT_PLACEHOLDERS - $reservedPlaceholders;
        if ($available < 1) {
            throw new \InvalidArgumentException('IN-list query has no placeholder capacity left after fixed parameters.');
        }

        $unique = [];
        $seen   = [];
        foreach ($values as $value) {
            $key = serialize($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $value;
        }

        return array_chunk($unique, min(self::IN_LIST_CHUNK_SIZE, $available));
    }

    public function single(?array $inputParams = null, int $fetchType = PDO::FETCH_ASSOC): mixed
    {
        $this->execute($inputParams);
        return $this->stmt->fetch($fetchType);
    }

    /**
     * Yields rows one at a time so callers can stream large result sets
     * without materialising the full set in PHP memory like resultset() does.
     */
    public function iterate(?array $inputParams = null, int $fetchType = PDO::FETCH_ASSOC): Generator
    {
        $this->execute($inputParams);
        while (($row = $this->stmt->fetch($fetchType)) !== false) {
            yield $row;
        }
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->dbh->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->dbh->beginTransaction();
    }

    public function endTransaction(): bool
    {
        return $this->dbh->commit();
    }

    public function cancelTransaction(): bool
    {
        return $this->dbh->rollBack();
    }

    public function debugDumpParams(): ?bool
    {
        return $this->stmt->debugDumpParams();
    }
}

// Issue #1290 phase B: legacy global-name shim. The procedural code
// in init.php / pages/*.php / api/handlers/*.php still references
// `\Database`; this alias keeps those call sites working until the
// call-site sweep PR replaces them with `Sbpp\Db\Database`.
class_alias(\Sbpp\Db\Database::class, 'Database');

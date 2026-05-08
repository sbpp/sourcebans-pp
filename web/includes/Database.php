<?php

/**
 * Class Database
 */
final class Database
{
    private readonly string $prefix;

    private PDO $dbh;

    private ?PDOStatement $stmt = null;

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
            die($e->getMessage());
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

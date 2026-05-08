<?php

/**
 * Class Database
 */
class Database
{
    private readonly string $prefix;

    private PDO $dbh;

    private ?PDOStatement $stmt = null;

    /**
     * Database constructor.
     * @param string $host
     * @param int $port
     * @param string $dbname
     * @param string $user
     * @param string $password
     * @param string $prefix
     * @param string $charset
     */
    public function __construct($host, $port, $dbname, $user, $password, $prefix, $charset = 'utf8')
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

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string $query
     * @return string
     */
    private function setPrefix($query)
    {
        $query = str_replace(':prefix', $this->prefix, $query);
        return $query;
    }

    /**
     * Contrary to the name, this prepares the query and doesn't actually run the query.
     *
     * @param string $query
     * @return $this
     */
    public function query($query)
    {
        $query = $this->setPrefix($query);
        $this->stmt = $this->dbh->prepare($query);
        return $this;
    }

    /**
     * @param int|string $param
     * @param int|bool|null|string $value
     * @param int|null $type PDO param type.  Send null or leave blank for auto-detection
     */
    public function bind($param, $value, $type = null)
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

    /**
     * @param array $params
     */
    public function bindMultiple($params = [])
    {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    /**
     * @param null|array $inputParams
     * @return mixed
     */
    public function execute(?array $inputParams = null)
    {
        return $this->stmt->execute($inputParams);
    }

    /**
     * @param null|array $inputParams
     * @param int $fetchType
     * @return mixed
     */
    public function resultset(?array $inputParams = null, $fetchType = PDO::FETCH_ASSOC)
    {
        $this->execute($inputParams);
        return $this->stmt->fetchAll($fetchType);
    }

    /**
     * @param null|array $inputParams
     * @param int $fetchType
     * @return mixed
     */
    public function single(?array $inputParams = null, $fetchType = PDO::FETCH_ASSOC)
    {
        $this->execute($inputParams);
        return $this->stmt->fetch($fetchType);
    }

    /**
     * Yields rows one at a time so callers can stream large result sets
     * without materialising the full set in PHP memory like resultset() does.
     */
    public function iterate(?array $inputParams = null, $fetchType = PDO::FETCH_ASSOC): Generator
    {
        $this->execute($inputParams);
        while (($row = $this->stmt->fetch($fetchType)) !== false) {
            yield $row;
        }
    }

    /**
     * @return int
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * @return string
     */
    public function lastInsertId()
    {
        return $this->dbh->lastInsertId();
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->dbh->beginTransaction();
    }

    /**
     * @return bool
     */
    public function endTransaction()
    {
        return $this->dbh->commit();
    }

    /**
     * @return bool
     */
    public function cancelTransaction()
    {
        return $this->dbh->rollBack();
    }

    /**
     * @return bool
     */
    public function debugDumpParams()
    {
        return $this->stmt->debugDumpParams();
    }
}

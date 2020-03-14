<?php

/**
 * Class Database
 */
class Database
{
    /**
     * @var string
     */
    private $prefix;
    /**
     * @var PDO
     */
    private $dbh;
    /**
     * @var PDOStatement
     */
    private $stmt;

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
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

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
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
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

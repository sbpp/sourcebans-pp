<?php

/**
 * Class Log
 */
class Log
{
    /**
     * @var Database
     */
    private static ?Database $dbs = null;
    /**
     * @var CUserManager
     */
    private static ?CUserManager $user = null;

    /**
     * @param Database $dbs
     * @param CUserManager $user
     */
    public static function init(Database $dbs, CUserManager $user)
    {
        self::$dbs = $dbs;
        self::$user = $user;
    }

    /**
     * @param string $type
     * @param string $title
     * @param string $message
     */
    public static function add($type, $title, $message): void
    {
        $host = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '';

        self::$dbs->query(
            "INSERT INTO `:prefix_log` (`type`, `title`, `message`, `function`, `query`, `aid`, `host`, `created`)
            VALUES (:type, :title, :message, :function, :query, :aid, :host, UNIX_TIMESTAMP())"
        );
        self::$dbs->bind(':type', filter_var($type, FILTER_SANITIZE_SPECIAL_CHARS));
        self::$dbs->bind(':title', filter_var($title, FILTER_SANITIZE_SPECIAL_CHARS));
        self::$dbs->bind(':message', filter_var($message, FILTER_SANITIZE_SPECIAL_CHARS));
        self::$dbs->bind(':function', filter_var(self::getCaller(), FILTER_SANITIZE_SPECIAL_CHARS));
        self::$dbs->bind(':query', filter_var($_SERVER['QUERY_STRING'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        self::$dbs->bind(':aid', self::$user->GetAid());
        self::$dbs->bind(':host', $host);
        self::$dbs->execute();
    }

    /**
     * @param int $start
     * @param int $limit
     * @param string $search Entire "WHERE" statement including the word WHERE
     * @return mixed
     */
    public static function getAll($start, $limit): mixed
    {
        $where = null;
        $valueOther = null;
        $value = $_GET['advSearch'] ?? null;
        $type  = $_GET['advType'] ?? null;

        switch ($type) {
            case "admin":
                $where = " l.aid = :value";
                break;
            case "message":
                $value = "%$value%";
                $where = " l.message LIKE :value OR l.title LIKE :value";
                break;
            case "date":
                $date  = explode(",", $value);
                $date[0] = (is_numeric($date[0])) ? $date[0] : date('d');
                $date[1] = (is_numeric($date[1])) ? $date[1] : date('m');
                $date[2] = (is_numeric($date[2])) ? $date[2] : date('Y');
                $value  = mktime($date[3], $date[4], 0, (int)$date[1], (int)$date[0], (int)$date[2]);
                $valueOther = mktime($date[5], $date[6], 59, (int)$date[1], (int)$date[0], (int)$date[2]);
                $where = " l.created > :value AND l.created :valueOther";
                break;
            case "type":
                $where = " l.type = :value";
                break;
        }

        $query = 'SELECT ad.user, l.* FROM `:prefix_log` AS l
                  LEFT JOIN `:prefix_admins` AS ad ON l.aid = ad.aid
                 '. ($where ? "WHERE $where" : '') .'
                  ORDER BY l.created DESC
                  LIMIT :start, :lim';

        self::$dbs->query($query);

        if ($value !== null)
            self::$dbs->bind('value', $value);
        if ($valueOther !== null)
            self::$dbs->bind('valueOther', $valueOther);

        self::$dbs->bind(':start', (int)$start, PDO::PARAM_INT);
        self::$dbs->bind(':lim', (int)$limit, PDO::PARAM_INT);
        return self::$dbs->resultset();
    }

    /**
     * @param string $search Entire "WHERE" statement including the word WHERE
     * @return mixed
     */
    public static function getCount($search): mixed
    {
        $value = $_GET['advSearch'] ?? null;
        $valueOther = null;
        $type  = $_GET['advType'] ?? null;
        $query = "SELECT COUNT(l.lid) AS count FROM `:prefix_log` AS l ";
        switch ($type) {
            case "admin":
                $query .= "WHERE l.aid = :value";
                break;
            case "message":
                $value = "%$value%";
                $query .= "WHERE l.message LIKE :value OR l.title LIKE :value";
                break;
            case "date":
                $date  = explode(",", $value);
                $date[0] = (is_numeric($date[0])) ? $date[0] : date('d');
                $date[1] = (is_numeric($date[1])) ? $date[1] : date('m');
                $date[2] = (is_numeric($date[2])) ? $date[2] : date('Y');
                $value  = mktime($date[3], $date[4], 0, (int)$date[1], (int)$date[0], (int)$date[2]);
                $valueOther = mktime($date[5], $date[6], 59, (int)$date[1], (int)$date[0], (int)$date[2]);
                $query .= "WHERE l.created > :value AND l.created :valueOther";
                break;
            case "type":
                $query .= "WHERE l.type = :value";
                break;
        }

        self::$dbs->query($query);

        if ($value !== null)
            self::$dbs->bind('value', $value);
        if ($valueOther !== null)
            self::$dbs->bind('valueOther', $valueOther);

        $log = self::$dbs->single();
        return $log['count'];
    }

    /**
     * @return string
     */
    private static function getCaller(): string
    {
        $functions = '';
        foreach (debug_backtrace() as $key => $line) {
            $functions .= isset($line[$key]['file']) ? $line[$key]['file'].' - '.$line[$key]['line']."\r\n" : '';
        }
        return $functions;
    }
}

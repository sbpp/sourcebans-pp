<?php

class Log
{
    private static $dbs = null;
    public static function init(\Database $dbs)
    {
        self::$dbs = $dbs;
    }

    public static function add($type, $title, $message)
    {
        $aid = filter_var($_SESSION['aid'], FILTER_VALIDATE_INT) ? $_SESSION['aid'] : -1;
        $host = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '';

        self::$dbs->query(
            "INSERT INTO `:prefix_log` (`type`, `title`, `message`, `function`, `query`, `aid`, `host`, `created`)
            VALUES (:type, :title, :message, :function, :query, :aid, :host, UNIX_TIMESTAMP())"
        );
        self::$dbs->bind(':type', filter_var($type, FILTER_SANITIZE_STRING));
        self::$dbs->bind(':title', filter_var($title, FILTER_SANITIZE_STRING));
        self::$dbs->bind(':message', filter_var($message, FILTER_SANITIZE_STRING));
        self::$dbs->bind(':function', filter_var(self::getCaller(), FILTER_SANITIZE_STRING));
        self::$dbs->bind(':query', filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_STRING));
        self::$dbs->bind(':aid', $aid);
        self::$dbs->bind(':host', $host);
        self::$dbs->execute();
    }

    public static function getAll($start, $limit, $search = null)
    {
        $query = "SELECT ad.user, l.* FROM `:prefix_log` AS l
                  LEFT JOIN `:prefix_admins` AS ad ON l.aid = ad.aid
                  :search ORDER BY l.created DESC
                  LIMIT :start, :lim";
        $query = str_replace(':search', filter_var($search, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES), $query);
        self::$dbs->query($query);
        self::$dbs->bind(':start', (int)$start, \PDO::PARAM_INT);
        self::$dbs->bind(':lim', (int)$limit, \PDO::PARAM_INT);
        return self::$dbs->resultset();
    }

    public static function getCount($search = null)
    {
        $query = "SELECT COUNT(l.lid) AS count FROM `:prefix_log` AS l :search";
        $query = str_replace(':search', filter_var($search, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES), $query);
        self::$dbs->query($query);
        $log = self::$dbs->single();
        return $log['count'];
    }

    private static function getCaller()
    {
        foreach (debug_backtrace() as $key => $line) {
            $functions .= isset($line[$key]['file']) ? $line[$key]['file'].' - '.$line[$key]['line']."\r\n" : '';
        }
        return $functions;
    }
}

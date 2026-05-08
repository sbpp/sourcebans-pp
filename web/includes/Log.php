<?php

/**
 * Class Log
 */
final class Log
{
    private static ?Database $dbs = null;

    private static ?CUserManager $user = null;

    public static function init(Database $dbs, CUserManager $user): void
    {
        self::$dbs = $dbs;
        self::$user = $user;
    }

    /**
     * @param LogType $type Audit row severity (`Message`/`Warning`/`Error`).
     *                      The on-disk column is `enum('m','w','e')`; the
     *                      bind below pulls `$type->value` so phpstan-dba
     *                      sees the column-typed primitive (a string from
     *                      the ENUM's value set).
     */
    public static function add(LogType $type, string $title, string $message): void
    {
        $host = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '';

        self::$dbs->query(
            "INSERT INTO `:prefix_log` (`type`, `title`, `message`, `function`, `query`, `aid`, `host`, `created`)
            VALUES (:type, :title, :message, :function, :query, :aid, :host, UNIX_TIMESTAMP())"
        );
        self::$dbs->bind(':type', $type->value);
        self::$dbs->bind(':title', $title);
        self::$dbs->bind(':message', $message);
        self::$dbs->bind(':function', self::getCaller());
        self::$dbs->bind(':query', $_SERVER['QUERY_STRING'] ?? '');
        self::$dbs->bind(':aid', self::$user->GetAid());
        self::$dbs->bind(':host', $host);
        self::$dbs->execute();
    }

    public static function getAll(int $start, int $limit): array
    {
        $value      = $_GET['advSearch'] ?? null;
        $valueOther = null;
        $filter     = LogSearchType::tryFromGetParam($_GET['advType'] ?? null);
        $where      = null;

        if ($filter !== null) {
            [$value, $valueOther] = self::resolveSearchValues($filter, $value);
            $where = $filter->whereClause();
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
     * Count audit-log rows matching the active `?advType=` /
     * `?advSearch=` filter (or all rows when no filter is active).
     *
     * Mirrors `Log::getAll()`'s WHERE-construction so the pager's
     * "showing N of M" math agrees with the listing.
     *
     * @return mixed Single-column scalar from the `COUNT(l.lid)` row.
     */
    public static function getCount(): mixed
    {
        $value      = $_GET['advSearch'] ?? null;
        $valueOther = null;
        $filter     = LogSearchType::tryFromGetParam($_GET['advType'] ?? null);
        $query      = "SELECT COUNT(l.lid) AS count FROM `:prefix_log` AS l ";

        if ($filter !== null) {
            [$value, $valueOther] = self::resolveSearchValues($filter, $value);
            $query .= 'WHERE ' . $filter->whereClause();
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
     * Translate the raw `$_GET['advSearch']` into the
     * `[primary, secondary]` bind pair each search filter expects:
     *
     *   - Admin: `[$value, null]` — the aid is bound as-is.
     *   - Message: `["%$value%", null]` — wrapped for the LIKE clause.
     *   - Type: `[$value, null]` — the type letter is bound as-is.
     *   - Date: `[mktime(start), mktime(end)]` — the comma-separated
     *     `<dd>,<mm>,<yyyy>,<fhh>,<fmm>,<thh>,<tmm>` tuple is split and
     *     converted into a pair of UNIX timestamps. Missing fields fall
     *     back to today's day/month/year.
     *
     * @return array{0: mixed, 1: mixed}
     */
    private static function resolveSearchValues(LogSearchType $filter, mixed $value): array
    {
        return match ($filter) {
            LogSearchType::Admin, LogSearchType::Type => [$value, null],
            LogSearchType::Message => ["%$value%", null],
            LogSearchType::Date    => self::resolveDateRange((string) $value),
        };
    }

    /**
     * Resolve a `dd,mm,yyyy[,fhh,fmm,thh,tmm]` comma-separated date
     * string (the wire format the audit-log search filter posts via
     * `?advType=date&advSearch=…`) into a `[from, to]` UNIX
     * timestamp pair.
     *
     * Field-by-field fallbacks:
     *
     *   - `dd` / `mm` / `yyyy` (indices 0–2): missing or non-numeric
     *     fields fall back to today's day / month / year — the
     *     historical "search for today" UX when the user hits Submit
     *     with an empty form.
     *   - `fhh` / `fmm` (indices 3–4, the **from** time): missing
     *     fields fall back to `00:00`. With seconds pinned at `0`
     *     this is the start of the chosen day.
     *   - `thh` / `tmm` (indices 5–6, the **to** time): missing
     *     fields fall back to `00:00`. With seconds pinned at `59`
     *     and the form historically only submitting `dd,mm,yyyy`
     *     (the time-component inputs were dropped from the UI but
     *     the wire format kept the slots), the resulting range
     *     covers the full day via the "from = 00:00:00, to =
     *     00:00:59 + a full day's worth of LIMIT-bounded rows
     *     ordered DESC" assumption that the legacy search makes.
     *
     * Pre-#1290 (PHP < 8.1) the missing-time fallbacks fired on
     * `mktime($date[3], …)` reading an undefined offset; PHP 8.1+
     * promoted that to a runtime warning. The `?? 0` coalesces
     * lock the fallback shape statically.
     *
     * @return array{0: int, 1: int} `[from, to]` UNIX timestamps.
     */
    private static function resolveDateRange(string $raw): array
    {
        $date = explode(',', $raw);
        // explode() returns non-empty-list<string>, so $date[0] is
        // structurally guaranteed; the is_numeric() check is the
        // load-bearing fallback when the field is empty / non-numeric.
        $day   = is_numeric($date[0]) ? (int) $date[0] : (int) date('d');
        $month = (isset($date[1]) && is_numeric($date[1])) ? (int) $date[1] : (int) date('m');
        $year  = (isset($date[2]) && is_numeric($date[2])) ? (int) $date[2] : (int) date('Y');
        $start = mktime((int) ($date[3] ?? 0), (int) ($date[4] ?? 0), 0,  $month, $day, $year);
        $end   = mktime((int) ($date[5] ?? 0), (int) ($date[6] ?? 0), 59, $month, $day, $year);
        return [$start, $end];
    }

    private static function getCaller(): string
    {
        $functions = '';
        foreach (debug_backtrace() as $key => $line) {
            $functions .= isset($line[$key]['file']) ? $line[$key]['file'].' - '.$line[$key]['line']."\r\n" : '';
        }
        return $functions;
    }
}

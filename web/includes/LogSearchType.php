<?php

/**
 * Audit-log advanced-search filter shape.
 *
 * The audit log page (`?p=admin&c=settings&section=logs`) accepts a
 * `?advType=<slug>&advSearch=<value>` URL pair. Pre-#1290 phase D the
 * Log.php read the slug as a free-form string and did a 4-way
 * `switch ($type)` on it twice — once in `Log::getAll()`, once in
 * `Log::getCount()` — to build the WHERE fragment. The enum collapses
 * both switches into a single `match` keyed on the case.
 *
 * `tryFromGetParam` is the soft-parse helper for the `$_GET['advType']`
 * read site: a missing / unrecognised value short-circuits the search
 * (no WHERE clause appended). `whereClause()` returns the SQL fragment
 * the case contributes; it always references `:value` and (for
 * `Date`) `:valueOther`, both bound by the caller.
 *
 * Issue #1290 phase D.1.
 */
enum LogSearchType: string
{
    /** Search by admin id (l.aid = :value). */
    case Admin = 'admin';

    /** Search by free-text in the title or message column (LIKE %value%). */
    case Message = 'message';

    /** Search by date range. The caller splits `$_GET['advSearch']` into a
     *  comma-separated `<dd>,<mm>,<yyyy>,<fhh>,<fmm>,<thh>,<tmm>` tuple
     *  and binds the resulting `mktime()` pair as :value / :valueOther. */
    case Date = 'date';

    /** Search by `:prefix_log.type` letter ('m', 'w', or 'e'). */
    case Type = 'type';

    /**
     * Soft-parse a free-form `$_GET['advType']` into a case, returning
     * `null` for a missing / unrecognised slug so the caller can skip
     * the WHERE-fragment append entirely.
     */
    public static function tryFromGetParam(mixed $value): ?self
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        return self::tryFrom($value);
    }

    /**
     * SQL WHERE-clause fragment this filter contributes (without the
     * leading `WHERE`). The fragment binds `:value` (and, for `Date`,
     * `:valueOther`); the caller is responsible for invoking
     * `Database::bind()` with the matching primitive.
     */
    public function whereClause(): string
    {
        return match ($this) {
            self::Admin   => 'l.aid = :value',
            self::Message => 'l.message LIKE :value OR l.title LIKE :value',
            self::Date    => 'l.created > :value AND l.created < :valueOther',
            self::Type    => 'l.type = :value',
        };
    }
}

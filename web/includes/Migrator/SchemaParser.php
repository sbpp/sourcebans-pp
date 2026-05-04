<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

declare(strict_types=1);

namespace Sbpp\Migrator;

/**
 * Reads `web/install/includes/sql/struc.sql` and `data.sql` (with the
 * `{prefix}` / `{charset}` placeholders preserved) and turns them into a
 * structured representation the {@see SchemaDiffer} can compare against
 * the live database without re-executing the SQL.
 *
 * The parser is intentionally regex-based rather than reaching for a full
 * SQL parser:
 *   - Both files are vanilla DDL/DML hand-edited by humans, never machine
 *     generated; the grammar is tiny and stable (one CREATE TABLE block per
 *     table, one INSERT INTO ... VALUES (...), (...) ... block per seed).
 *   - The Fixture used by every PHPUnit test already executes the same
 *     files on a real MariaDB before each test run, so any malformed SQL
 *     would fail the gates loudly long before the parser cared.
 *   - Anything fancier (nikic/php-sql-parser, pgsql-parser bindings, etc.)
 *     would be a brand-new runtime dependency for a tool that's run a
 *     handful of times per release.
 *
 * Callers must pass the source as the *raw* file contents — i.e. with
 * `{prefix}` / `{charset}` still present. The parser strips the prefix so
 * the resulting `ParsedSchema` is independent of the deploying installation
 * and can be substituted late.
 */
final class SchemaParser
{
    /**
     * Parse a struc.sql source string into a list of tables.
     *
     * The parser keeps the `{prefix}` / `{charset}` placeholders intact in
     * the rendered SQL so the caller (typically {@see SchemaDiffer::renderDdl()})
     * can substitute them with the deployment-specific values just before
     * execution. That means the parsed representation is portable across
     * deployments with different prefixes/charsets without re-parsing.
     */
    public static function parseStructure(string $sql): ParsedSchema
    {
        $tables = [];

        $stripped = self::stripComments($sql);

        $rx = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?'
            . '`(\{prefix\}_[a-zA-Z0-9_]+)`\s*\(([\s\S]*?)\)\s*ENGINE\s*=\s*[a-zA-Z0-9]+'
            . '[^;]*;/i';

        if (preg_match_all($rx, $stripped, $matches, PREG_SET_ORDER) === false) {
            throw new \RuntimeException('SchemaParser: regex failed against struc.sql');
        }

        foreach ($matches as $hit) {
            $rawName  = $hit[1];                                                  // {prefix}_admins
            $shortName = (string) preg_replace('/^\{prefix\}_/', '', $rawName);   // admins
            $body     = $hit[2];

            $columns = self::parseColumns($body);
            $tables[] = new ParsedTable(
                shortName: $shortName,
                createSql: rtrim(trim($hit[0])),
                columns:   $columns,
            );
        }

        return new ParsedSchema($tables);
    }

    /**
     * Parse the `INSERT INTO {prefix}_settings (`setting`, `value`) VALUES
     * (...), (...);` block(s) out of data.sql and return the seed key/value
     * pairs.
     *
     * Settings are the only piece of `data.sql` the migrator backfills —
     * the `{prefix}_mods` rows and the seed `{prefix}_admins` row are
     * deliberately left to the install/dev fixture flow because clobbering
     * them on an upgrading panel would erase admin-edited rows.
     *
     * @return list<SettingSeed>
     */
    public static function parseSettings(string $sql): array
    {
        $stripped = self::stripComments($sql);

        // Anchor on the literal table name in the INSERT to be robust to
        // future seed inserts for other tables.
        $rx = '/INSERT\s+(?:IGNORE\s+)?INTO\s+`\{prefix\}_settings`'
            . '\s*\(\s*`setting`\s*,\s*`value`\s*\)\s*VALUES\s*([\s\S]*?);/i';

        if (preg_match_all($rx, $stripped, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $seeds = [];
        $seen  = [];
        foreach ($matches as $hit) {
            foreach (self::extractTuples($hit[1]) as [$key, $value]) {
                if (isset($seen[$key])) {
                    // data.sql is human-edited; flag a duplicate at parse
                    // time so we don't silently shadow one row with another.
                    throw new \RuntimeException(
                        "SchemaParser: duplicate settings key '$key' in data.sql"
                    );
                }
                $seen[$key] = true;
                $seeds[]    = new SettingSeed($key, $value);
            }
        }

        return $seeds;
    }

    /**
     * Strip `--`-prefixed line comments and `/* ... *\/` block comments. We
     * keep `#` literals alone — `data.sql` doesn't use them and stripping
     * them naively would clobber `value` payloads that happen to contain a
     * `#` (e.g. an HTML colour).
     */
    private static function stripComments(string $sql): string
    {
        $sql = (string) preg_replace('@/\*.*?\*/@s', '', $sql);
        $sql = (string) preg_replace('/^\s*--[^\n]*\n/m', '', $sql);
        return $sql;
    }

    /**
     * Pull `\`name\` TYPE ...` column declarations out of a CREATE TABLE
     * body. Index/constraint declarations (`PRIMARY KEY`, `UNIQUE KEY`,
     * `KEY`, `FULLTEXT`, `INDEX`, `UNIQUE`, `CONSTRAINT`, `FOREIGN KEY`)
     * are skipped.
     *
     * @return list<ParsedColumn>
     */
    private static function parseColumns(string $body): array
    {
        $columns = [];
        $depth   = 0;
        $buffer  = '';
        $len     = strlen($body);

        // Walk character by character so we can split on top-level commas
        // without falling for ones inside `int(11)` / `enum('a','b')`.
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '(') {
                $depth++;
                $buffer .= $ch;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $buffer .= $ch;
                continue;
            }
            if ($ch === ',' && $depth === 0) {
                $col = self::columnFromLine($buffer);
                if ($col !== null) {
                    $columns[] = $col;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }

        if (trim($buffer) !== '') {
            $col = self::columnFromLine($buffer);
            if ($col !== null) {
                $columns[] = $col;
            }
        }

        return $columns;
    }

    /**
     * Convert a single column-or-index declaration into a {@see ParsedColumn}
     * (or `null` if the line is an index/constraint, not a column).
     */
    private static function columnFromLine(string $line): ?ParsedColumn
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);
        // Order matters: FULLTEXT KEY / UNIQUE KEY share a tail with KEY.
        $skipPrefixes = [
            'PRIMARY KEY',
            'UNIQUE KEY',
            'UNIQUE INDEX',
            'UNIQUE ',
            'FULLTEXT KEY',
            'FULLTEXT INDEX',
            'FULLTEXT ',
            'INDEX ',
            'INDEX(',
            'KEY ',
            'KEY(',
            'CONSTRAINT ',
            'FOREIGN KEY',
            'CHECK ',
        ];
        foreach ($skipPrefixes as $p) {
            if (str_starts_with($upper, $p)) {
                return null;
            }
        }

        if (!preg_match('/^`([a-zA-Z0-9_]+)`\s+(.+)$/s', $trimmed, $m)) {
            return null;
        }

        return new ParsedColumn(
            name:       $m[1],
            definition: self::collapseWhitespace((string) $m[2]),
        );
    }

    private static function collapseWhitespace(string $s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Pull every `('key', 'value')` tuple out of an INSERT VALUES list. We
     * accept single-quoted literals (the only ones used in data.sql) and
     * tolerate escaped quotes via a doubled `''` SQL convention.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function extractTuples(string $valuesBody): array
    {
        $tuples = [];
        $len    = strlen($valuesBody);
        $i      = 0;

        while ($i < $len) {
            // Skip whitespace + commas between tuples.
            while ($i < $len && ($valuesBody[$i] === ' ' || $valuesBody[$i] === "\t"
                || $valuesBody[$i] === "\n" || $valuesBody[$i] === "\r"
                || $valuesBody[$i] === ',')) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            if ($valuesBody[$i] !== '(') {
                // Stray char — most likely a trailing newline; bail rather
                // than misparse half a tuple.
                break;
            }
            $i++; // consume '('

            $key = self::readQuoted($valuesBody, $i);
            self::skipCommaWs($valuesBody, $i);
            $val = self::readQuoted($valuesBody, $i);

            // Skip until the matching close paren.
            while ($i < $len && $valuesBody[$i] !== ')') {
                $i++;
            }
            if ($i < $len && $valuesBody[$i] === ')') {
                $i++;
            }

            $tuples[] = [$key, $val];
        }

        return $tuples;
    }

    /** Read a single-quoted SQL literal starting at `$pos`. Advances `$pos`. */
    private static function readQuoted(string $s, int &$pos): string
    {
        // Skip leading whitespace.
        while ($pos < strlen($s) && ($s[$pos] === ' ' || $s[$pos] === "\t" || $s[$pos] === "\n" || $s[$pos] === "\r")) {
            $pos++;
        }
        if ($pos >= strlen($s) || $s[$pos] !== "'") {
            throw new \RuntimeException(
                "SchemaParser: expected single-quoted literal at offset $pos in INSERT body"
            );
        }
        $pos++; // consume opening '

        $buf = '';
        $len = strlen($s);
        while ($pos < $len) {
            $ch = $s[$pos];
            if ($ch === "\\" && $pos + 1 < $len) {
                $buf .= $s[$pos + 1];
                $pos += 2;
                continue;
            }
            if ($ch === "'") {
                if ($pos + 1 < $len && $s[$pos + 1] === "'") {
                    $buf .= "'";
                    $pos += 2;
                    continue;
                }
                $pos++; // consume closing '
                return $buf;
            }
            $buf .= $ch;
            $pos++;
        }

        throw new \RuntimeException(
            'SchemaParser: unterminated single-quoted literal in INSERT body'
        );
    }

    private static function skipCommaWs(string $s, int &$pos): void
    {
        $len = strlen($s);
        while ($pos < $len && ($s[$pos] === ' ' || $s[$pos] === "\t" || $s[$pos] === "\n" || $s[$pos] === "\r" || $s[$pos] === ',')) {
            $pos++;
        }
    }
}

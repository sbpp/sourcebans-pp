<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1224: the install wizard's statement splitter.
 *
 * Steps 4 and 5 used to split struc.sql / data.sql with a plain
 * `explode(';', …)`, so a `;` inside a comment (#1221) or a string
 * literal cut a statement in half and broke a fresh install.
 * `sbpp_install_split_sql()` only splits on a `;` outside comments,
 * string literals and quoted identifiers; these tests pin each of
 * those contexts plus the shape of the two shipped SQL files.
 */
final class InstallSqlSplitTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('sbpp_install_split_sql')) {
            require_once ROOT . 'install/includes/helpers.php';
        }
    }

    public function testSplitsOnTopLevelSemicolons(): void
    {
        $this->assertSame(
            ['SELECT 1', 'SELECT 2', 'SELECT 3'],
            sbpp_install_split_sql("SELECT 1;\nSELECT 2;SELECT 3\n"),
        );
    }

    public function testSemicolonInLineCommentDoesNotSplit(): void
    {
        $sql = "-- Notes follow the player; a re-ban keeps them.\n"
            . "CREATE TABLE a (id int);\n"
            . "# hash comment; also opaque\n"
            . "CREATE TABLE b (id int);\n";

        $this->assertSame(
            ['CREATE TABLE a (id int)', 'CREATE TABLE b (id int)'],
            sbpp_install_split_sql($sql),
        );
    }

    public function testSemicolonInBlockCommentDoesNotSplit(): void
    {
        $sql = "/* one; two;\n three */ CREATE TABLE a (id int);\n"
            . "CREATE/* inline; */TABLE b (id int);";

        $this->assertSame(
            ['CREATE TABLE a (id int)', 'CREATE TABLE b (id int)'],
            sbpp_install_split_sql($sql),
        );
    }

    public function testSemicolonInStringLiteralDoesNotSplit(): void
    {
        $sql = "INSERT INTO s VALUES ('smtp.dsn', 'a;b'), ('x', \"c;d\");\n"
            . "INSERT INTO s VALUES ('it\\'s; fine', 'it''s; fine');\n"
            . "INSERT INTO s VALUES ('trailing backslash\\\\', ';');";

        $this->assertSame(
            [
                "INSERT INTO s VALUES ('smtp.dsn', 'a;b'), ('x', \"c;d\")",
                "INSERT INTO s VALUES ('it\\'s; fine', 'it''s; fine')",
                "INSERT INTO s VALUES ('trailing backslash\\\\', ';')",
            ],
            sbpp_install_split_sql($sql),
        );
    }

    public function testCommentMarkersInsideLiteralsAreKept(): void
    {
        $sql = "INSERT INTO s VALUES ('# heading', '-- dash', '/* not a comment */');\n"
            . "CREATE TABLE `odd;name` (id int);";

        $this->assertSame(
            [
                "INSERT INTO s VALUES ('# heading', '-- dash', '/* not a comment */')",
                'CREATE TABLE `odd;name` (id int)',
            ],
            sbpp_install_split_sql($sql),
        );
    }

    public function testDoubleDashWithoutWhitespaceIsNotAComment(): void
    {
        $this->assertSame(
            ['SELECT 1--1', 'SELECT 2'],
            sbpp_install_split_sql("SELECT 1--1;\nSELECT 2;"),
        );
    }

    public function testExecutableCommentsAreKept(): void
    {
        $sql = "/*!40101 SET NAMES utf8mb4 */;\n"
            . "/*M!100100 SET @x = 1 */;\n"
            . "SELECT /*+ MAX_EXECUTION_TIME(1) */ 1;";

        $this->assertSame(
            [
                '/*!40101 SET NAMES utf8mb4 */',
                '/*M!100100 SET @x = 1 */',
                'SELECT /*+ MAX_EXECUTION_TIME(1) */ 1',
            ],
            sbpp_install_split_sql($sql),
        );
    }

    public function testCommentOnlyAndEmptyChunksAreDropped(): void
    {
        $sql = ";;\n-- leading comment\nSELECT 1;\n  ;\n-- trailing comment; with a semicolon\n/* and a block */\n";

        $this->assertSame(['SELECT 1'], sbpp_install_split_sql($sql));
        $this->assertSame([], sbpp_install_split_sql("-- only a comment\n"));
        $this->assertSame([], sbpp_install_split_sql(''));
    }

    public function testShippedSchemaSplitsIntoOneStatementPerTable(): void
    {
        $sql = (string) file_get_contents(ROOT . 'install/includes/sql/struc.sql');
        $stmts = sbpp_install_split_sql($sql);

        $this->assertSame('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"', array_shift($stmts));
        $this->assertSame(preg_match_all('/^CREATE TABLE /m', $sql), count($stmts));
        foreach ($stmts as $stmt) {
            $this->assertMatchesRegularExpression('/^CREATE TABLE IF NOT EXISTS `\{prefix\}_\w+` \(/', $stmt);
            $this->assertStringEndsWith('DEFAULT CHARSET={charset}', $stmt);
        }
    }

    public function testShippedSeedDataSplitsIntoItsStatements(): void
    {
        $sql = (string) file_get_contents(ROOT . 'install/includes/sql/data.sql');
        $stmts = sbpp_install_split_sql($sql);

        $this->assertCount(4, $stmts);
        $this->assertSame("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'", $stmts[0]);
        foreach (array_slice($stmts, 1) as $stmt) {
            $this->assertStringStartsWith('INSERT IGNORE INTO `{prefix}_', $stmt);
        }
        // The Markdown intro seed carries `#` and `\n` inside a
        // string literal; neither may be read as a comment or cut.
        $this->assertStringContainsString(
            "'# Your new SourceBans install\\n\\nSourceBans++ successfully installed!'",
            implode("\n", $stmts),
        );
    }
}

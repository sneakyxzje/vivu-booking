<?php

namespace Tests\Unit;

use App\Support\SqlDumpStatements;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SqlDumpStatementsTest extends TestCase
{
    public function test_does_not_split_strings_identifiers_or_comments(): void
    {
        $sql = <<<'SQL'
-- comment ;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
/*!40101 SET NAMES utf8mb4 */;
INSERT INTO `semi;colon` VALUES ('one;two', 'it\'s; fine', 'it''s; fine', 'back\\');
/* comment ; */ SELECT "quoted;value";
SQL;
        $statements = iterator_to_array(SqlDumpStatements::read($sql));
        $this->assertCount(4, $statements);
        $this->assertStringContainsString("'it\\'s; fine'", $statements[2]);
        $this->assertStringContainsString("'it''s; fine'", $statements[2]);
        $this->assertStringContainsString('SELECT "quoted;value"', $statements[3]);
    }

    public function test_accepts_final_statement_without_semicolon(): void
    {
        $this->assertSame(['SELECT 1', 'SELECT 2'], iterator_to_array(SqlDumpStatements::read('SELECT 1; SELECT 2')));
    }

    public function test_rejects_truncated_string(): void
    {
        $this->expectException(RuntimeException::class);
        iterator_to_array(SqlDumpStatements::read("INSERT INTO t VALUES ('unfinished"));
    }
}

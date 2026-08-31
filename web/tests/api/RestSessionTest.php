<?php

namespace Sbpp\Tests\Api;

use PHPUnit\Framework\TestCase;

final class RestSessionTest extends TestCase
{
    public function testV1EntryDefinesRestFlagBeforeInit(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/api/v1.php');
        $definePos = strpos($src, "define('SBPP_REST'");
        $includePos = strpos($src, "include_once dirname(__DIR__) . '/init.php'");
        $this->assertNotFalse($definePos);
        $this->assertNotFalse($includePos);
        $this->assertLessThan($includePos, $definePos);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCsrfInitDoesNotStartSessionWhenRestFlagIsSet(): void
    {
        if (!defined('SBPP_REST')) {
            define('SBPP_REST', true);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
        \CSRF::init();
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
    }
}

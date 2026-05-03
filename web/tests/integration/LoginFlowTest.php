<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;

/**
 * Tier 1 smoke flow #5 from #1095: the Plogin handler. Hits auth.login
 * with bad creds and verifies the lockout counter increments.
 */
final class LoginFlowTest extends ApiTestCase
{
    public function testFailedLoginIncrementsAttempts(): void
    {
        // Enable normal login (default in install/data.sql).
        \Config::init($GLOBALS['PDO']);

        $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'wrong']);
        $this->assertSame('?p=login&m=failed', $env['redirect'] ?? null);

        $row = $this->row('admins', ['user' => 'admin']);
        $this->assertSame(1, (int)$row['attempts']);
    }
}

<?php

declare(strict_types=1);

namespace Sbpp\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Sbpp\Auth\Host;

/**
 * #1381 CRIT-4 — `Host::isSecure()` MUST NOT honour an unsigned
 * `X-Forwarded-Proto: https` from an attacker hitting the panel
 * over plain HTTP. Pre-fix it did, breaking the `Secure` cookie
 * flag, the `https://...` redirect scheme, and every
 * `protocol() . domain()` URL the panel emits.
 *
 * Resolution order under the fix:
 *
 *   1. `$_SERVER['HTTPS'] === 'on'` — authoritative, wins
 *      unconditionally. Apache's `mod_remoteip` + the paired
 *      `SetEnvIfExpr` mirror a trusted upstream's `XFP=https`
 *      into `HTTPS=on`, so the production Docker image always
 *      hits this branch when the upstream connection was TLS.
 *
 *   2. `$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'` AND
 *      `$_SERVER['REMOTE_ADDR']` is in `SBPP_TRUSTED_PROXIES`.
 *      Fallback for non-Docker deployments where Apache
 *      isn't doing the proxy-trust check; the operator pins
 *      the trust list in `config.php` instead.
 *
 *   3. Anything else — false (the secure default).
 *
 * Each scenario runs in a separate process because the
 * `SBPP_TRUSTED_PROXIES` constant is process-global; without
 * isolation the first `define()` would shadow every subsequent
 * test in the file and the trust-list checks would all silently
 * collapse onto one configured value.
 */
final class HostIsSecureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset(
            $_SERVER['HTTPS'],
            $_SERVER['HTTP_X_FORWARDED_PROTO'],
            $_SERVER['REMOTE_ADDR']
        );
    }

    public function testHttpsOnReturnsTrue(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue(Host::isSecure());
    }

    public function testNoHttpsNoXfpReturnsFalse(): void
    {
        $this->assertFalse(Host::isSecure());
    }

    public function testHttpsOffWithoutXfpReturnsFalse(): void
    {
        $_SERVER['HTTPS'] = 'off';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * Attacker case — direct-HTTP hit with a spoofed
     * `X-Forwarded-Proto: https` and no proxy trust configured.
     * Pre-fix this returned `true` (the bug). Post-fix it must
     * return `false`.
     */
    #[RunInSeparateProcess]
    public function testXfpFromUntrustedSourceWithoutTrustListReturnsFalse(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * Trust list defined but the attacker's REMOTE_ADDR is
     * outside it — XFP still rejected.
     */
    #[RunInSeparateProcess]
    public function testXfpFromIpOutsideTrustListReturnsFalse(): void
    {
        define('SBPP_TRUSTED_PROXIES', '10.0.0.0/8');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * Trusted-proxy case — REMOTE_ADDR is inside the configured
     * trust list AND XFP is `https`. Returns `true`.
     */
    #[RunInSeparateProcess]
    public function testXfpFromTrustedProxyReturnsTrue(): void
    {
        define('SBPP_TRUSTED_PROXIES', '10.0.0.0/8 192.168.0.0/16');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '10.0.5.13';

        $this->assertTrue(Host::isSecure());
    }

    /**
     * Trust list entry that's a literal IP, not a CIDR — exact
     * match path.
     */
    #[RunInSeparateProcess]
    public function testXfpFromTrustedLiteralIpReturnsTrue(): void
    {
        define('SBPP_TRUSTED_PROXIES', '192.0.2.100');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.100';

        $this->assertTrue(Host::isSecure());
    }

    /**
     * IPv6 CIDR — REMOTE_ADDR inside the range.
     */
    #[RunInSeparateProcess]
    public function testXfpFromTrustedIpv6CidrReturnsTrue(): void
    {
        define('SBPP_TRUSTED_PROXIES', 'fd00::/8');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = 'fd12:3456:789a::1';

        $this->assertTrue(Host::isSecure());
    }

    /**
     * IPv6 CIDR — REMOTE_ADDR outside the range.
     */
    #[RunInSeparateProcess]
    public function testXfpFromIpv6OutsideTrustedCidrReturnsFalse(): void
    {
        define('SBPP_TRUSTED_PROXIES', 'fd00::/8');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * Family mismatch — IPv4 IP against an IPv6 CIDR (and vice
     * versa) must NOT match. Pre-fix a naive `inet_pton` compare
     * could silently match across families if the binary
     * representations happened to share a prefix.
     */
    #[RunInSeparateProcess]
    public function testIpv4AgainstIpv6CidrDoesNotMatch(): void
    {
        define('SBPP_TRUSTED_PROXIES', 'fd00::/8');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '10.0.5.13';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * Trust list defined but empty (operator unset it via env).
     * XFP must NOT be honoured.
     */
    #[RunInSeparateProcess]
    public function testEmptyTrustListDoesNotHonourXfp(): void
    {
        define('SBPP_TRUSTED_PROXIES', '');
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '10.0.5.13';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * Whitespace-only trust list — same as empty.
     */
    #[RunInSeparateProcess]
    public function testWhitespaceOnlyTrustListDoesNotHonourXfp(): void
    {
        define('SBPP_TRUSTED_PROXIES', "  \n\t  ");
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '10.0.5.13';

        $this->assertFalse(Host::isSecure());
    }

    /**
     * `HTTPS=on` wins regardless of a missing / spoofed XFP —
     * the production-Docker happy path where `mod_remoteip` +
     * `SetEnvIfExpr` already mirrored the upstream scheme.
     */
    #[RunInSeparateProcess]
    public function testHttpsOnWinsRegardlessOfTrustList(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        $this->assertTrue(Host::isSecure());
    }
}

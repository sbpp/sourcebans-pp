<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use Lcobucci\JWT\Signer\Key\InMemory;
use PHPUnit\Framework\TestCase;
use Sbpp\Auth\JWT;

/**
 * SB_SECRET_KEY must be valid base64 for lcobucci's
 * InMemory::base64Encoded(). A UUID / hex / random password in the
 * env var used to surface as a raw CannotDecodeContent stack dump on
 * first login. JWT::signingKeyFromSecret() is the gate that turns
 * that into an operator-facing abort.
 */
final class JwtSecretKeyTest extends TestCase
{
    public function testValidBase64SecretProducesSigningKey(): void
    {
        $secret = base64_encode(random_bytes(47));
        $key = JWT::signingKeyFromSecret($secret);

        $this->assertInstanceOf(InMemory::class, $key);
        $this->assertNotSame('', $key->contents());
    }

    public function testInvalidSecretKeyAbortsWithOperatorMessage(): void
    {
        // failInvalidSecretKey uses exit(1) on the CLI SAPI, which
        // would kill the PHPUnit worker. Drive it in a subprocess so
        // the abort is observable.
        $autoload = dirname(__DIR__, 2) . '/includes/vendor/autoload.php';
        $jwtFile = dirname(__DIR__, 2) . '/includes/Auth/JWT.php';

        $script = <<<'PHP'
require $argv[1];
require $argv[2];
\Sbpp\Auth\JWT::signingKeyFromSecret('not-valid-base64!!!');
fwrite(STDERR, "EXPECTED_ABORT_MISSING\n");
exit(2);
PHP;

        $cmd = [
            PHP_BINARY,
            '-r',
            $script,
            '--',
            $autoload,
            $jwtFile,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        $this->assertIsResource($proc);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        $combined = $stdout . "\n" . $stderr;

        $this->assertSame(
            1,
            $exitCode,
            "invalid SB_SECRET_KEY must exit 1; output was:\n{$combined}",
        );
        $this->assertStringContainsString(
            'SB_SECRET_KEY is not valid base64',
            $combined,
            "subprocess output was:\n{$combined}",
        );
        $this->assertStringContainsString(
            'openssl rand -base64 47',
            $combined,
            "subprocess output was:\n{$combined}",
        );
        $this->assertStringNotContainsString(
            'EXPECTED_ABORT_MISSING',
            $combined,
        );
    }
}

<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use Lcobucci\JWT\Signer\Key\InMemory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sbpp\Auth\JWT;

/**
 * SB_SECRET_KEY must be base64 that decodes to at least
 * {@see JWT::MIN_SECRET_BYTES}. Invalid base64 and short-but-valid
 * base64 both used to reach Lcobucci's HMAC signer and dump a library
 * stack on first login. JWT::signingKeyFromSecret() is the shared gate.
 */
final class JwtSecretKeyTest extends TestCase
{
    public function testValidBase64SecretProducesSigningKey(): void
    {
        $secret = base64_encode(random_bytes(47));
        $key = JWT::signingKeyFromSecret($secret);

        $this->assertInstanceOf(InMemory::class, $key);
        $this->assertGreaterThanOrEqual(JWT::MIN_SECRET_BYTES, strlen($key->contents()));
    }

    public function testExactlyMinSecretBytesIsAccepted(): void
    {
        $secret = base64_encode(random_bytes(JWT::MIN_SECRET_BYTES));
        $key = JWT::signingKeyFromSecret($secret);

        $this->assertSame(JWT::MIN_SECRET_BYTES, strlen($key->contents()));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function rejectedSecretProvider(): array
    {
        return [
            'non-base64' => ['not-valid-base64!!!'],
            'short valid base64 (16 bytes)' => [base64_encode(random_bytes(16))],
            'one byte under minimum' => [base64_encode(random_bytes(JWT::MIN_SECRET_BYTES - 1))],
        ];
    }

    #[DataProvider('rejectedSecretProvider')]
    public function testRejectedSecretAbortsWithOperatorMessage(string $secret): void
    {
        // failInvalidSecretKey uses exit(1) on the CLI SAPI, which
        // would kill the PHPUnit worker. Drive it in a subprocess so
        // the abort is observable.
        $autoload = dirname(__DIR__, 2) . '/includes/vendor/autoload.php';
        $jwtFile = dirname(__DIR__, 2) . '/includes/Auth/JWT.php';

        $script = <<<'PHP'
require $argv[1];
require $argv[2];
\Sbpp\Auth\JWT::signingKeyFromSecret($argv[3]);
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
            $secret,
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
            "rejected SB_SECRET_KEY must exit 1; output was:\n{$combined}",
        );
        $this->assertStringContainsString(
            'SB_SECRET_KEY must be base64 that decodes to at least ' . JWT::MIN_SECRET_BYTES . ' bytes',
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

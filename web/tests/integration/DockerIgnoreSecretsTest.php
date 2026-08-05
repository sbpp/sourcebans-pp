<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Static gate for the production image build-context exclusions in
 * `.dockerignore`. Ensures local `config.php` / backups / `.env` files
 * cannot COPY into `docker/Dockerfile.prod`, while
 * `web/config.php.template` stays available for non-Docker installs.
 */
final class DockerIgnoreSecretsTest extends TestCase
{
    private const IGNORE_PATH = ROOT . '../.dockerignore';

    public function testDockerIgnoreExcludesConfigAndEnvSecrets(): void
    {
        $contents = (string) file_get_contents(self::IGNORE_PATH);
        $this->assertNotEmpty($contents, '.dockerignore must be readable.');

        foreach ([
            'web/config.php',
            'web/config.php.*',
            '!web/config.php.template',
            '.env',
            '.env.*',
            '**/.env',
            '**/.env.*',
        ] as $line) {
            $this->assertMatchesRegularExpression(
                '/^' . preg_quote($line, '/') . '\s*$/m',
                $contents,
                ".dockerignore must include the line `{$line}`",
            );
        }
    }
}

<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Upload\UploadHandler;

/**
 * Uploaded demos (including anonymous submissions from `?p=submit`)
 * land in `web/demos/` without an extension and must only reach a
 * browser through `getdemo.php`. Pins the three layers:
 *
 *   1. Stored names come from a CSPRNG.
 *   2. `/demos/` is denied by the shipped `.htaccess` and both Apache
 *      confs (prod image + dev stack).
 *   3. `init.php` and both Apache confs send `nosniff`.
 *
 * `web/tests/e2e/specs/flows/demo-direct-access.spec.ts` drives the
 * same contract over HTTP.
 */
final class DemoUploadHardeningTest extends TestCase
{
    private const PROD_CONF = ROOT . '../docker/apache/sbpp-prod.conf';
    private const DEV_CONF  = ROOT . '../docker/apache/sbpp-dev.conf';

    public function testRandomDiskNameIsThirtyTwoLowercaseHex(): void
    {
        $name = UploadHandler::randomDiskName();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $name);
    }

    public function testRandomDiskNameIsUniqueWithinTheSameSecond(): void
    {
        $names = [];
        for ($i = 0; $i < 64; $i++) {
            $names[] = UploadHandler::randomDiskName();
        }

        $this->assertCount(64, array_unique($names));
    }

    public function testUploadPathsUseRandomDiskName(): void
    {
        $handler = (string) file_get_contents(ROOT . 'includes/Upload/UploadHandler.php');
        $this->assertMatchesRegularExpression(
            '/\?\s*self::randomDiskName\(\)/',
            $handler,
            'UploadHandler must name renamed uploads via randomDiskName().',
        );

        $submit = (string) file_get_contents(ROOT . 'pages/page.submit.php');
        $this->assertMatchesRegularExpression(
            '/\$filename\s*=\s*\\\\Sbpp\\\\Upload\\\\UploadHandler::randomDiskName\(\);/',
            $submit,
            'page.submit.php must name stored demos via UploadHandler::randomDiskName().',
        );
    }

    public function testDemosHtaccessDeniesAllRequests(): void
    {
        $path = ROOT . 'demos/.htaccess';
        $this->assertFileExists($path, 'web/demos/.htaccess must ship so Apache tarball installs block /demos/.');

        $this->assertMatchesRegularExpression(
            '/^\s*Require\s+all\s+denied\s*$/mi',
            (string) file_get_contents($path),
        );
    }

    public function testApacheConfsDenyDemosAndSendNosniff(): void
    {
        foreach ([self::PROD_CONF, self::DEV_CONF] as $path) {
            $contents = (string) file_get_contents($path);
            $this->assertNotSame('', $contents, "{$path} must be readable.");

            $this->assertMatchesRegularExpression(
                '#<DirectoryMatch\s+"\^\$\{APACHE_DOCUMENT_ROOT\}/demos\(/\|\$\)">\s*Require\s+all\s+denied\s*</DirectoryMatch>#',
                $contents,
                "{$path} must deny direct access to demos/.",
            );
            $this->assertMatchesRegularExpression(
                '/^Header\s+always\s+set\s+X-Content-Type-Options\s+"nosniff"\s*$/m',
                $contents,
                "{$path} must send nosniff on every response.",
            );
        }
    }

    public function testInitSendsNosniffBeforeAnyOutput(): void
    {
        $init = (string) file_get_contents(ROOT . 'init.php');

        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*!headers_sent\(\)\s*\)\s*\{\s*header\('X-Content-Type-Options: nosniff'\);\s*\}/",
            $init,
        );

        $headerAt  = strpos($init, "header('X-Content-Type-Options: nosniff')");
        $installAt = strpos($init, "header('Location: install/')");
        $this->assertNotFalse($headerAt);
        $this->assertNotFalse($installAt);
        $this->assertLessThan($installAt, $headerAt, 'nosniff must be sent before the first early exit in init.php.');
    }
}

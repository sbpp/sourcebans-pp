<?php

namespace Sbpp\Tests\Api;

use Sbpp\Export\EntityExporter;

final class RestSettingsTest extends RestTestCase
{
    public function testGetRequiresToken(): void
    {
        $response = $this->rest('GET', '/settings');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCookieJwtDoesNotGet(): void
    {
        $this->loginAsAdmin();
        $response = $this->rest('GET', '/settings');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testGetOmitsForbiddenKeys(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('GET', '/settings', token: $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertIsArray($data);
        $this->assertArrayHasKey('template.title', $data);
        $this->assertArrayNotHasKey('smtp.pass', $data);
        $this->assertArrayNotHasKey('telemetry.instance_id', $data);
        foreach (EntityExporter::FORBIDDEN_SETTING_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
    }

    public function testPatchRoundTripAndRestore(): void
    {
        $token = $this->mintToken();
        $before = $this->rest('GET', '/settings', token: $token);
        $original = (string) $before->payload['data']['template.title'];

        $patched = $this->rest('PATCH', '/settings', [
            'template.title' => 'REST Title',
        ], $token);
        $this->assertSame(200, $patched->status, json_encode($patched->payload));
        $this->assertSame('REST Title', $patched->payload['data']['template.title']);
        $this->assertArrayNotHasKey('smtp.pass', $patched->payload['data']);

        $restored = $this->rest('PATCH', '/settings', [
            'template.title' => $original,
        ], $token);
        $this->assertSame(200, $restored->status);
        $this->assertSame($original, $restored->payload['data']['template.title']);
    }

    public function testPatchForbiddenKeyIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PATCH', '/settings', [
            'smtp.pass' => 'secret',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('smtp.pass', $response->payload['error']['field'] ?? null);
    }

    public function testPatchUnknownKeyIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PATCH', '/settings', [
            'not.a.real.setting' => 'x',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('not.a.real.setting', $response->payload['error']['field'] ?? null);
    }

    public function testPatchEmptyBodyIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PATCH', '/settings', [], $token);
        $this->assertRestError($response, 400, 'validation');
    }

    public function testPatchAcceptsBoolean(): void
    {
        $token = $this->mintToken();
        $before = $this->rest('GET', '/settings', token: $token);
        $original = (string) $before->payload['data']['config.enablecomms'];

        $patched = $this->rest('PATCH', '/settings', [
            'config.enablecomms' => false,
        ], $token);
        $this->assertSame(200, $patched->status, json_encode($patched->payload));
        $this->assertSame('0', $patched->payload['data']['config.enablecomms']);

        $this->rest('PATCH', '/settings', [
            'config.enablecomms' => $original,
        ], $token);
    }
}

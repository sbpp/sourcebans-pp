<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

/**
 * HTTP response returned by REST handlers. Tests inspect `$status` /
 * `$payload` in-process. Production calls {@see send()} which exits.
 */
final class Response
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $payload = [],
        public readonly array $headers = [],
        public readonly ?string $rawBody = null,
        public readonly string $contentType = 'application/json; charset=utf-8',
    ) {
    }

    public function send(): never
    {
        if (!headers_sent()) {
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            header('Content-Type: ' . $this->contentType);
            header('Cache-Control: no-store');
            http_response_code($this->status);
        }

        if ($this->status !== 204) {
            if ($this->rawBody !== null) {
                echo $this->rawBody;
            } else {
                echo json_encode(
                    $this->payload,
                    JSON_THROW_ON_ERROR
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_UNESCAPED_SLASHES,
                );
            }
        }
        exit;
    }
}

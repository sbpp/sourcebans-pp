<?php

/**
 * Per-session CSRF token issued at session start and validated on every
 * state-changing request (POST forms and JSON API calls).
 */
class CSRF
{
    public const FIELD_NAME = 'csrf_token';
    public const HEADER_NAME = 'HTTP_X_CSRF_TOKEN';

    private const SESSION_KEY = 'csrf_token';

    /**
     * Ensures a session is active and a token is bound to it.
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
    }

    public static function token(): string
    {
        return is_string($_SESSION[self::SESSION_KEY] ?? null) ? $_SESSION[self::SESSION_KEY] : '';
    }

    public static function validate($token): bool
    {
        $expected = self::token();
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /**
     * Pulls a candidate token from POST, GET, or the X-CSRF-Token header.
     */
    public static function fromRequest(): ?string
    {
        $post = $_POST[self::FIELD_NAME] ?? null;
        if (is_string($post)) {
            return $post;
        }
        $get = $_GET[self::FIELD_NAME] ?? null;
        if (is_string($get)) {
            return $get;
        }
        $header = $_SERVER[self::HEADER_NAME] ?? null;
        return is_string($header) ? $header : null;
    }

    /**
     * Sends a 403 response and terminates the request.
     */
    public static function reject(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'CSRF token validation failed. Please reload the page and try again.';
        exit();
    }

    public static function rejectIfInvalid(): void
    {
        if (!self::validate(self::fromRequest())) {
            self::reject();
        }
    }
}

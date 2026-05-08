<?php

namespace Sbpp\Security;

/**
 * Class Crypto
 */
final class Crypto
{
    public static function genJTI(int $length = 12): string
    {
        return self::base64RandomBytes($length);
    }

    public static function genSecret(int $length = 47): string
    {
        return self::base64RandomBytes($length);
    }

    public static function genPassword(int $length = 23): string
    {
        return self::base64RandomBytes($length);
    }

    public static function recoveryHash(): string
    {
        return hash('sha256', self::base64RandomBytes(12));
    }

    private static function base64RandomBytes(int $length): string
    {
        return base64_encode(openssl_random_pseudo_bytes($length));
    }
}

// Issue #1290 phase B: legacy global-name shim. Procedural code keeps
// using `\Crypto::*` until the call-site sweep PR.
class_alias(\Sbpp\Security\Crypto::class, 'Crypto');

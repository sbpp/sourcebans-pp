<?php

class Crypto
{
    public static function genJTI(int $length = 12)
    {
        return self::base64RandomBytes($length);
    }

    public static function genSecret(int $length = 47)
    {
        return self::base64RandomBytes($length);
    }

    public static function genPassword(int $length = 23)
    {
        return self::base64RandomBytes($length);
    }

    public static function recoveryHash()
    {
        return hash('sha256', self::base64RandomBytes(12));
    }

    private static function base64RandomBytes(int $length)
    {
        return base64_encode(openssl_random_pseudo_bytes($length));
    }
}

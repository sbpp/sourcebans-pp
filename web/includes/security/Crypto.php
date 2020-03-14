<?php

/**
 * Class Crypto
 */
class Crypto
{
    /**
     * @param int $length
     * @return string
     */
    public static function genJTI(int $length = 12)
    {
        return self::base64RandomBytes($length);
    }

    /**
     * @param int $length
     * @return string
     */
    public static function genSecret(int $length = 47)
    {
        return self::base64RandomBytes($length);
    }

    /**
     * @param int $length
     * @return string
     */
    public static function genPassword(int $length = 23)
    {
        return self::base64RandomBytes($length);
    }

    /**
     * @return string
     */
    public static function recoveryHash()
    {
        return hash('sha256', self::base64RandomBytes(12));
    }

    /**
     * @param int $length
     * @return string
     */
    private static function base64RandomBytes(int $length)
    {
        return base64_encode(openssl_random_pseudo_bytes($length));
    }
}

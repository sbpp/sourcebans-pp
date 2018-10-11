<?php

require_once(INCLUDES_PATH.'/session/FileSessionHandler.php');

class SessionManager
{
    public static function start()
    {
        $handler = new FileSessionHandler();
        session_set_save_handler($handler, true);
        register_shutdown_function('session_write_close');

        session_name('SBPP_Auth');
        $secure = (bool)(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_start();
        setcookie(session_name(), session_id(), self::getMaxLifetime(), '/', '', $secure, true);

        if (!self::preventHijacking()) {
            $_SESSION = [];
            $_SESSION['userAgent'] = hash("sha256", filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING));
            self::regenerate();
        } elseif (rand(1, 100) <= 5) {
            self::regenerate();
        }
    }

    private static function preventHijacking()
    {
        if (isset($_SESSION['userAgent'])) {
            $hash = hash("sha256", filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING));
            return (bool)hash_equals($_SESSION['userAgent'], $hash);
        }
        return false;
    }

    private static function regenerate()
    {
        session_regenerate_id(false);

        $sid = session_id();
        session_write_close();

        session_id($sid);
        session_start();
    }

    private static function getMaxLifetime()
    {
        $options = [
            'default' => 3600,
            'min_range' => 3600,
            'max_range' => 604800
        ];
        $time = filter_var($_COOKIE['remember_me'], FILTER_VALIDATE_INT, $options);

        return time() + $time;
    }
}

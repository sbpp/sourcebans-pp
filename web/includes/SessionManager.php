<?php
class SessionManager
{
    public static function sessionStart($name, $expires = 86400, $limit = 0, $path = '/', $domain = null)
    {
        $secure = false;
        session_name($name.'_Session');
        $domain = isset($domain) ? $domain : $_SERVER['SERVER_NAME'];
        if ($_SERVER['SERVER_PORT'] == 443) {
            $secure = true;
        }
        session_set_cookie_params($limit, $path, $domain, $secure, true);
        session_start();

        $_SESSION['userAgent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT']);
        $_SESSION['EXPIRES'] = time()+$expires;
    }
    public static function checkSession()
    {
        if (!isset($_SESSION['userAgent'])) {
            return false;
        }
        if (!self::validateSession() || !self::preventHijacking()) {
            session_destroy();
            session_start();
            return false;
        } elseif (rand(1, 100) <= 10) {
            self::regenerateSession();
        }
        return true;
    }
    protected static function preventHijacking()
    {
        if (!isset($_SESSION['userAgent'])) {
            return false;
        }
        if ($_SESSION['userAgent'] !== hash('sha256', $_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        return true;
    }
    protected static function regenerateSession()
    {
        $_SESSION['EXPIRES'] = time() + 10;
        session_regenerate_id(false);
        $newSession = session_id();
        session_write_close();
        session_id($newSession);
        session_start();
        unset($_SESSION['EXPIRES']);
    }
    protected static function validateSession()
    {
        if (isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time()) {
            return false;
        }
        return true;
    }
}

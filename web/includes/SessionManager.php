<?php
class SessionManager
{
    public static function sessionStart($name, $expires = 86400, $path = '/', $domain = null)
    {
        session_name($name.'_Session');

        $domain = isset($domain) ? $domain : $_SERVER['SERVER_NAME'];
        $secure = ($_SERVER['SERVER_PORT'] === 443) ? true : false;

        session_set_cookie_params($expires, $path, $domain, $secure, true);
        session_start();

        if (self::validateSession()) {
            if (!self::preventHijacking()) {
                $_SESSION = array();
                $_SESSION['userAgent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT']);
                $_SESSION['EXPIRES'] = time()+$expires;
                self::regenerateSession();
            } elseif ((rand(1, 100) <= 10) && !isset($_POST['xajax'])) {
                self::regenerateSession();
            }
        } else {
            $_SESSION = array();
            session_destroy();
            session_start();
        }
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

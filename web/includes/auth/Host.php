<?php

/**
 * Class Host
 */
class Host
{
    /**
     * @return string
     */
    public static function domain()
    {
        return filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    }

    /**
     * @return string
     */
    public static function protocol()
    {
        return sprintf('http%s://', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 's' : '');
    }

    /**
     * @return string
     */
    public static function complete()
    {
        $request = explode('/',
            filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
        foreach ($request as $id => $fragment) {
            switch (true) {
                case empty($fragment):
                case strpos($fragment, '.php') !== false:
                    unset($request[$id]);
                    break;
                default:
            }
        }
        $request = implode('/', $request);

        return self::protocol().self::domain()."/$request";
    }
}

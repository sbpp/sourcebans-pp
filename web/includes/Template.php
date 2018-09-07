<?php

class Template
{
    private static $engine;

    public static function init(Mustache_Engine $engine)
    {
        self::$engine = $engine;
    }

    public static function render($template, $data = [])
    {
        $tpl = self::$engine->loadTemplate($template);
        print $tpl->render($data);
    }
}

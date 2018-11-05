<?php
namespace App\Utils;

/**
 * 缓存对象
 */

final class VarStore
{
    private static $map = [];

    public static function put($key, $val)
    {
        self::$map[$key] = $val;
    }

    public static function get($key)
    {
        return isset(self::$map[$key]) ? self::$map[$key] : null;
    }

    public static function has($key)
    {
        return isset(self::$map[$key]) ? true : false;
    }
}
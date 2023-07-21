<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class App
{
    private static $apps = [];

    public static function set(string $appname, string $dir)
    {
        self::$apps[$appname] = [
            'name' => $appname,
            'dir' => $dir,
        ];
    }

    public static function get(string $appname): array
    {
        return self::$apps[$appname];
    }

    public static function has(string $appname): bool
    {
        return isset(self::$apps[$appname]);
    }

    public static function delete(string $appname)
    {
        unset(self::$apps[$appname]);
    }

    public static function all(): array
    {
        return self::$apps;
    }
}

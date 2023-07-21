<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class Route
{
    private static $found = false;
    private static $allowed = false;
    private static $handler = '';
    private static $middlewares = [];
    private static $params = [];

    public static function setFound(bool $found)
    {
        self::$found = $found;
    }

    public static function setAllowed(bool $allowed)
    {
        self::$allowed = $allowed;
    }

    public static function setHandler(string $handler)
    {
        self::$handler = $handler;
    }

    public static function setMiddlewares(array $middlewares)
    {
        self::$middlewares = $middlewares;
    }

    public static function setParams(array $params)
    {
        self::$params = $params;
    }

    public static function isFound(): bool
    {
        return self::$found;
    }

    public static function isAllowed(): bool
    {
        return self::$allowed;
    }

    public static function getHandler(): string
    {
        return self::$handler;
    }

    public static function getMiddleWares(): array
    {
        return self::$middlewares;
    }

    public static function getParams(): array
    {
        return self::$params;
    }
}

<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;

class Route
{
    private static $found = false;
    private static $allowed = false;
    private static $handler = null;
    private static $params = [];
    private static $appname = null;

    public static function isFound(): bool
    {
        self::init();
        return self::$found;
    }

    public static function isAllowed(): bool
    {
        self::init();
        return self::$allowed;
    }

    public static function getHandler(): ?string
    {
        self::init();
        return self::$handler;
    }

    public static function getParams(): array
    {
        self::init();
        return self::$params;
    }

    public static function getAppName(): ?string
    {
        self::init();
        return self::$appname;
    }

    private static function init()
    {
        static $init;
        if ($init) {
            return;
        }

        $uri = ServerRequest::getUriFromGlobals();
        $res = Framework::getRouter()->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', '' . $uri->withQuery(''));

        self::$found = $res[0];

        if (self::$found) {
            $cls = $res[2] ?? null;
            if (!is_string($cls) || !is_subclass_of($cls, RequestHandlerInterface::class)) {
                throw new Exception("路由仅支持"); // todl..
            }
            self::$allowed = $res[1] ?? false;
            self::$handler = $cls;
            self::$params = $res[3] ?? [];
        } else {
            $uri = ServerRequest::getUriFromGlobals();
            $paths = explode('/', $uri->getPath());
            $pathx = explode('/', $_SERVER['SCRIPT_NAME']);
            foreach ($pathx as $key => $value) {
                if (isset($paths[$key]) && ($paths[$key] == $value)) {
                    unset($paths[$key]);
                }
            }
            if (count($paths) >= 3) {
                array_splice($paths, 0, 0, 'App');
                array_splice($paths, 3, 0, 'Http');
                $cls = str_replace(['-'], [''], ucwords(implode('\\', $paths), '\\-'));
                if (is_subclass_of($cls, RequestHandlerInterface::class)) {
                    self::$found = true;
                    self::$allowed = true;
                    self::$handler = $cls;
                    self::$params = [];
                }
            }
        }

        if (self::$found) {
            $paths = explode('\\', self::$handler);
            if (!isset($paths[4]) || $paths[0] != 'App' || $paths[3] != 'Http') {
                self::$found = false;
            }
            $camelToLine = function (string $str): string {
                return strtolower(preg_replace('/([A-Z])/', "-$1", lcfirst($str)));
            };
            $appname = $camelToLine($paths[1]) . '/' . $camelToLine($paths[2]);
            if (!App::has($appname)) {
                self::$found = false;
            }
            self::$appname = $appname;
        }
        $init = 1;
    }
}

<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Handler implements RequestHandlerInterface
{
    private static $middlewares = [];

    public function __construct()
    {
        Framework::getEvent()->dispatch($this);
    }

    public static function pushMiddleware(...$middlewares)
    {
        array_push(self::$middlewares, ...$middlewares);
    }

    public static function unShiftMiddleware(...$middlewares)
    {
        array_unshift(self::$middlewares, ...$middlewares);
    }

    public static function popMiddleware()
    {
        return array_pop(self::$middlewares);
    }

    public static function shiftMiddleware()
    {
        return array_shift(self::$middlewares);
    }

    public function handle(ServerRequestInterface $serverRequest): ResponseInterface
    {
        if ($middleware = $this->shiftMiddleware()) {
            if (!is_a($middleware, MiddlewareInterface::class, true)) {
                throw new Exception('中间件必须实现接口：' . MiddlewareInterface::class);
            }
            return $this->getMiddleware($middleware)->process($serverRequest, $this);
        } else {
            if (!Route::isFound()) {
                return Framework::getHttpFactory()->createResponse(404);
            } else if (!Route::isAllowed()) {
                return Framework::getHttpFactory()->createResponse(405);
            } else {
                return $this->getRequestHandler()->handle($serverRequest);
            }
        }
    }

    private function getRequestHandler(): RequestHandlerInterface
    {
        return Framework::getContainer()->get(Route::getHandler());
    }

    private function getMiddleware(string $middleware): MiddlewareInterface
    {
        return Framework::getContainer()->get($middleware);
    }
}

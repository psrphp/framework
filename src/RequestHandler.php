<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    private $middlewares = [];

    public function __construct()
    {
        Framework::getEvent()->dispatch($this);
    }

    public function pushMiddleware(...$middlewares)
    {
        foreach ($middlewares as $vo) {
            if (!is_subclass_of($vo, MiddlewareInterface::class)) {
                throw new Exception('the middleware must be instance of ' . MiddlewareInterface::class);
            }
            array_push($this->middlewares, $vo);
        }
    }

    public function unShiftMiddleware(...$middlewares)
    {
        foreach ($middlewares as $vo) {
            if (!is_subclass_of($vo, MiddlewareInterface::class)) {
                throw new Exception('the middleware must be instance of ' . MiddlewareInterface::class);
            }
            array_unshift($this->middlewares, $vo);
        }
    }

    public function popMiddleware()
    {
        return array_pop($this->middlewares);
    }

    public function shiftMiddleware()
    {
        return array_shift($this->middlewares);
    }

    public function handle(ServerRequestInterface $serverRequest): ResponseInterface
    {
        if ($middleware = $this->shiftMiddleware()) {
            if (is_string($middleware)) {
                $middleware = Framework::getContainer()->get($middleware);
            }
            return $middleware->process($serverRequest, $this);
        } else {
            if (!Framework::getRoute()->isFound()) {
                return Framework::getHttpFactory()->createResponse(404);
            } else if (!Framework::getRoute()->isAllowed()) {
                return Framework::getHttpFactory()->createResponse(405);
            } else {
                $handler = Framework::getContainer()->get(Framework::getRoute()->getHandler());
                return $handler->handle($serverRequest);
            }
        }
    }
}

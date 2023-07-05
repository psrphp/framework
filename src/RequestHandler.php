<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    protected $container = [];
    protected $handler = [];
    protected $middlewares = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function setHandler(RequestHandlerInterface $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function pushMiddleware(...$middlewares): self
    {
        foreach ($middlewares as $vo) {
            if (!is_subclass_of($vo, MiddlewareInterface::class)) {
                throw new Exception('the middleware must be instance of ' . MiddlewareInterface::class);
            }
            array_push($this->middlewares, $vo);
        }
        return $this;
    }

    public function unShiftMiddleware(...$middlewares): self
    {
        foreach ($middlewares as $vo) {
            if (!is_subclass_of($vo, MiddlewareInterface::class)) {
                throw new Exception('the middleware must be instance of ' . MiddlewareInterface::class);
            }
            array_unshift($this->middlewares, $vo);
        }
        return $this;
    }

    public function popMiddleware()
    {
        return array_pop($this->middlewares);
    }

    public function shiftMiddleware()
    {
        return array_shift($this->middlewares);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($middleware = $this->shiftMiddleware()) {
            if (is_string($middleware)) {
                $middleware = $this->container->get($middleware);
            }
            return $middleware->process($request, $this);
        } else {
            return $this->handler->handle($request);
        }
    }
}

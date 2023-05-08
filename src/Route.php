<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use PsrPHP\Psr17\Factory;
use PsrPHP\Router\Router;
use Psr\Http\Message\UriInterface;
use ReflectionClass;

class Route
{
    private $found = false;
    private $allowed = false;
    private $handler = null;
    private $middlewares = [];
    private $params = [];
    private $app = null;
    private $uri = null;

    public function __construct(
        Factory $factory,
        Router $router
    ) {
        $this->uri = $factory->createUriFromGlobals();
        $res = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', '' . $this->uri->withQuery(''));
        $this->found = $res[0] ?? false;
        $this->allowed = $res[1] ?? false;
        $this->handler = $res[2] ?? null;
        $this->middlewares = $res[3] ?? [];
        $this->params = $res[4] ?? [];

        if ($this->isFound()) {
            $handler = $this->getHandler();
            $cls = null;
            if (is_array($handler) && $handler[1] == 'handle') {
                $cls = $handler[0];
            } elseif (is_string($handler)) {
                $cls = $handler;
            }
            if ($cls) {
                $paths = explode('\\', is_object($cls) ? (new ReflectionClass($cls))->getName() : $cls);
                if (isset($paths[4]) && $paths[0] == 'App' && $paths[3] == 'Http') {
                    $this->app = $this->camel($paths[1]) . '/' . $this->camel($paths[2]);
                }
            }
        } else {
            $paths = explode('/', $this->uri->getPath());
            $pathx = explode('/', $_SERVER['SCRIPT_NAME']);
            foreach ($pathx as $key => $value) {
                if (isset($paths[$key]) && ($paths[$key] == $value)) {
                    unset($paths[$key]);
                }
            }
            if (count($paths) >= 3) {
                array_splice($paths, 0, 0, 'App');
                array_splice($paths, 3, 0, 'Http');
                $class = str_replace(['-'], [''], ucwords(implode('\\', $paths), '\\-'));
                $this->setFound(true);
                $this->setAllowed(true);
                $this->setHandler($class);
                $this->app = $this->camel($paths[1]) . '/' . $this->camel($paths[2]);
            }
        }
    }

    public function setFound(bool $found): self
    {
        $this->found = $found;
        return $this;
    }

    public function setApp(?string $app): self
    {
        $this->app = $app;
        return $this;
    }

    public function setAllowed(bool $allowed): self
    {
        $this->allowed = $allowed;
        return $this;
    }

    public function setHandler($handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function setMiddlewares(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getMiddleWares(): array
    {
        return $this->middlewares;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getApp(): ?string
    {
        return $this->app;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    private function camel(string $str): string
    {
        return strtolower(preg_replace('/([A-Z])/', "-$1", lcfirst($str)));
    }
}

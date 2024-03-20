<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;

class Route
{
    private $found = false;
    private $allowed = false;
    private $handler = '';
    private $params = [];

    public function __construct()
    {
        $uri = ServerRequest::getUriFromGlobals();
        $res = Framework::getRouter()->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', '' . $uri->withQuery(''));

        $this->setFound($res[0]);
        $this->setAllowed($res[1] ?? false);
        $this->setHandler($res[2] ?? '');
        $this->setParams($res[3] ?? []);

        if (!$this->isFound()) {
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
                $this->setFound(true);
                $this->setAllowed(true);
                $this->setHandler(str_replace(['-'], [''], ucwords(implode('\\', $paths), '\\-')));
            }
        }

        if ($this->isFound()) {
            if (!is_subclass_of($this->getHandler(), RequestHandlerInterface::class)) {
                $this->setFound(false);
            }
            $paths = explode('\\', $this->getHandler());
            if (!isset($paths[4]) || $paths[0] != 'App' || $paths[3] != 'Http') {
                $this->setFound(false);
            }
        }

        if ($this->isFound()) {
            $paths = explode('\\', $this->getHandler());
            $camelToLine = function (string $str): string {
                return strtolower(preg_replace('/([A-Z])/', "-$1", lcfirst($str)));
            };
            $appname = $camelToLine($paths[1]) . '/' . $camelToLine($paths[2]);
            if (!array_key_exists($appname, Framework::getAppList())) {
                $this->setFound(false);
            }
        }

        Framework::getEvent()->dispatch($this);
    }

    private function setFound(bool $found)
    {
        $this->found = $found;
    }

    private function setAllowed(bool $allowed)
    {
        $this->allowed = $allowed;
    }

    private function setHandler(string $handler)
    {
        $this->handler = $handler;
    }

    private function setParams(array $params)
    {
        $this->params = $params;
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function getHandler(): string
    {
        return $this->handler;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}

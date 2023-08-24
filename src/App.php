<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class App
{
    private $apps = [];

    public function __construct()
    {
        $root = dirname(dirname(dirname(dirname(__DIR__))));
        foreach (json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true)['packages'] as $pkg) {
            if ($pkg['type'] != 'psrapp') {
                continue;
            }
            if (file_exists($root . '/config/' . $pkg['name'] . '/disabled.lock')) {
                continue;
            }
            $this->set($pkg['name'], $root . '/vendor/' . $pkg['name']);
        }
    }

    public function set(string $appname, string $dir)
    {
        $this->apps[$appname] = [
            'name' => $appname,
            'dir' => $dir,
        ];
    }

    public function delete(string $appname)
    {
        unset($this->apps[$appname]);
    }

    public function get(string $appname): array
    {
        return $this->apps[$appname];
    }

    public function has(string $appname): bool
    {
        return isset($this->apps[$appname]);
    }

    public function all(): array
    {
        return $this->apps;
    }
}

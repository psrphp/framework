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

        spl_autoload_register(function (string $class) use ($root) {
            $paths = explode('\\', $class);
            if (isset($paths[3]) && $paths[0] == 'App' && $paths[1] == 'Plugin') {
                $file = $root . '/plugin/'
                    . strtolower(preg_replace('/([A-Z])/', "-$1", lcfirst($paths[2])))
                    . '/src/library/'
                    . str_replace('\\', '/', substr($class, strlen($paths[0]) + strlen($paths[1]) + strlen($paths[2]) + 3))
                    . '.php';
                if (file_exists($file)) {
                    include $file;
                }
            }
        });

        $dir = $root . '/plugin';
        foreach (scandir($dir) as $vo) {
            if (in_array($vo, array('.', '..'))) {
                continue;
            }
            if (!is_dir($dir . DIRECTORY_SEPARATOR . $vo)) {
                continue;
            }
            $app = 'plugin/' . $vo;
            if (file_exists($root . '/config/' . $app . '/disabled.lock')) {
                continue;
            }
            if (!file_exists($root . '/config/' . $app . '/install.lock')) {
                continue;
            }
            $this->set($app, $root . '/' . $app);
        }
    }

    public function set(string $appname, string $dir)
    {
        $this->apps[$appname] = [
            'name' => $appname,
            'dir' => $dir,
        ];
    }

    public function get(string $appname): array
    {
        return $this->apps[$appname];
    }

    public function has(string $appname): bool
    {
        return isset($this->apps[$appname]);
    }

    public function delete(string $appname)
    {
        unset($this->apps[$appname]);
    }

    public function all(): array
    {
        return $this->apps;
    }
}

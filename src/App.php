<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class App
{
    private static $list;

    public static function has(string $appname): bool
    {
        self::init();
        return isset(self::$list[$appname]);
    }

    public static function getDir(string $appname): string
    {
        return self::$list[$appname]['dir'];
    }

    public static function getList(): array
    {
        self::init();
        return array_keys(self::$list);
    }

    private static function init()
    {
        if (is_null(self::$list)) {
            self::$list = [];
            $root = dirname(__DIR__, 4);
            if (file_exists($root . '/vendor/composer/installed.json')) {
                foreach (json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true)['packages'] as $pkg) {
                    if ($pkg['type'] != 'psrapp') {
                        continue;
                    }
                    if (file_exists($root . '/config/' . $pkg['name'] . '/disabled.lock')) {
                        continue;
                    }
                    self::$list[$pkg['name']] = [
                        'dir' => $root . '/vendor/' . $pkg['name'],
                    ];
                }
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

            foreach (scandir($root . '/plugin') as $vo) {
                if (in_array($vo, array('.', '..'))) {
                    continue;
                }
                if (!is_dir($root . '/plugin' . DIRECTORY_SEPARATOR . $vo)) {
                    continue;
                }
                $appname = 'plugin/' . $vo;
                if (file_exists($root . '/config/' . $appname . '/disabled.lock')) {
                    continue;
                }
                if (!file_exists($root . '/config/' . $appname . '/install.lock')) {
                    continue;
                }
                self::$list[$appname] = [
                    'dir' => $root . '/' . $appname,
                ];
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\Autoload\ClassLoader;
use ReflectionClass;

class Plugin
{

    public function __construct()
    {
        spl_autoload_register(function (string $class) {
            $paths = explode('\\', $class);
            if (isset($paths[3])  && $paths[0] == 'App' && $paths[1] == 'Plugin') {
                $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));
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
    }

    public function getList(): array
    {
        $list = [];
        $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));
        foreach (glob($root . '/plugin/*/src/library/App.php') as $file) {
            $app = substr($file, strlen($root . '/'), -strlen('/src/library/App.php'));

            $class_name = str_replace(['-', '/'], ['', '\\'], ucwords('App\\' . $app . '\\App', '/\\-'));
            if (
                !class_exists($class_name)
                || !is_subclass_of($class_name, AppInterface::class)
            ) {
                continue;
            }

            if (file_exists($root . '/config/' . $app . '/disabled.lock')) {
                continue;
            }

            if (!file_exists($root . '/config/' . $app . '/install.lock')) {
                continue;
            }

            $list[$app] = [
                'name' => $app,
                'dir' => $root . '/' . $app,
            ];
        }
        return $list;
    }
}

<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\Autoload\ClassLoader;
use Exception;
use InvalidArgumentException;
use PsrPHP\Template\Template;
use ReflectionClass;

class Widget
{
    public static function get(string $key): string
    {
        $widget_file = self::parseKey($key);
        if (!$widget_file || !file_exists($widget_file)) {
            throw new Exception('not found widget [' . $key . '].');
        }

        return Framework::execute(function (
            Template $template
        ) use ($widget_file): string {
            return $template->renderFromString(file_get_contents($widget_file));
        });
    }

    private static function parseKey(string $key): ?string
    {
        list($filename, $group) = explode('@', $key . '@');

        if (!strlen($filename)) {
            throw new InvalidArgumentException('Invalid Argument Exception');
        }

        if (!strlen($group)) {
            $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));
            return $root . '/widget/' . $filename . '.php';
        }

        $group = str_replace('.', '/', $group);
        if (!App::has($group)) {
            return null;
        }
        return App::get($group)['dir'] . '/src/widget/' . $filename . '.php';
    }
}

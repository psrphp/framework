<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\Autoload\ClassLoader;
use InvalidArgumentException;
use PsrPHP\Template\Template;
use ReflectionClass;

class Widget
{
    private $template;

    public function __construct(
        Template $template
    ) {
        $this->template = $template;
    }

    public function get(string $key): string
    {
        $widget_file = $this->parseKey($key);
        $string = file_exists($widget_file) ? file_get_contents($widget_file) : '';
        return $this->template->renderFromString($string);
    }

    private function parseKey(string $key): string
    {
        list($filename, $group) = explode('@', $key . '@');

        if (!strlen($filename)) {
            throw new InvalidArgumentException('Invalid Argument Exception');
        }

        if (!strlen($group)) {
            $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));
            $widget_file = $root . '/widget/' . $filename . '.php';
        } else {
            $group = str_replace('.', '/', $group);
            $class_name = str_replace(['-', '/'], ['', '\\'], ucwords('App\\' . $group . '\\App', '/\\-'));
            $reflector = new ReflectionClass($class_name);
            $widget_file = dirname(dirname($reflector->getFileName())) . '/widget/' . $filename . '.php';
        }

        return $widget_file;
    }
}

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
    private $template;

    public function __construct(
        Template $template
    ) {
        $this->template = $template;
    }

    public function get(string $key): string
    {
        $widget_file = $this->parseKey($key);
        if (!$widget_file || !file_exists($widget_file)) {
            throw new Exception('not found widget [' . $key . '].');
        }

        return $this->template->renderFromString(file_get_contents($widget_file));
    }

    private function parseKey(string $key): ?string
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
        if (!isset(Framework::getAppList()[$group])) {
            return null;
        }
        return Framework::getAppList()[$group]['dir'] . '/src/widget/' . $filename . '.php';
    }
}

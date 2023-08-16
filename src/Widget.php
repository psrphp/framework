<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use InvalidArgumentException;
use PsrPHP\Template\Template;

class Widget
{
    private $app;
    private $template;

    public function __construct(
        App $app,
        Template $template
    ) {
        $this->app = $app;
        $this->template = $template;
    }

    public function get(string $key): string
    {
        $widget_file = $this->parseKey($key);
        if (!$widget_file || !file_exists($widget_file)) {
            throw new Exception('Not found widget [' . $key . '].');
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
            $root = dirname(dirname(dirname(dirname(__DIR__))));
            return $root . '/widget/' . $filename . '.php';
        }

        $group = str_replace('.', '/', $group);
        if (!$this->app->has($group)) {
            return null;
        }
        return $this->app->get($group)['dir'] . '/src/widget/' . $filename . '.php';
    }
}

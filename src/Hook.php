<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class Hook
{
    public static function execute(string $action, array $default_args = [])
    {
        foreach (array_keys(Framework::getAppList()) as $app) {
            $class_name = str_replace(['-', '/'], ['', '\\'], ucwords('App\\' . $app . '\\PsrPHP\\Hook', '/\\-'));
            if (method_exists($class_name, $action)) {
                Framework::execute([$class_name, $action], $default_args);
            }
        }
    }
}

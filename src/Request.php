<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class Request
{
    public static function has(string $field): bool
    {
        $fields = self::fieldFilter($field);
        $type = array_shift($fields);
        switch ($type) {
            case 'server':
                return self::isSetValue(Framework::getServerRequest()->getServerParams(), $fields);
                break;

            case 'get':
                return self::isSetValue(Framework::getServerRequest()->getQueryParams(), $fields);
                break;

            case 'post':
                return self::isSetValue(Framework::getServerRequest()->getParsedBody(), $fields);
                break;

            case 'request':
                return self::isSetValue(Framework::getServerRequest()->getQueryParams(), $fields) || self::isSetValue(Framework::getServerRequest()->getParsedBody(), $fields);
                break;

            case 'cookie':
                return self::isSetValue(Framework::getServerRequest()->getCookieParams(), $fields);
                break;

            case 'file':
                return self::isSetValue(Framework::getServerRequest()->getUploadedFiles(), $fields);
                break;

            case 'attr':
                return self::isSetValue(Framework::getServerRequest()->getAttributes(), $fields);
                break;

            case 'header':
                return self::isSetValue(Framework::getServerRequest()->getHeaders(), $fields);
                break;

            default:
                return false;
                break;
        }
    }

    public static function server(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getServerParams(), self::fieldFilter($field), $default);
    }

    public static function get(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getQueryParams(), self::fieldFilter($field), $default);
    }

    public static function post(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getParsedBody(), self::fieldFilter($field), $default);
    }

    public static function request(string $field = '', $default = null)
    {
        if (self::has('get.' . $field)) {
            return self::getValue(Framework::getServerRequest()->getQueryParams(), self::fieldFilter($field), $default);
        } else {
            return self::getValue(Framework::getServerRequest()->getParsedBody(), self::fieldFilter($field), $default);
        }
    }

    public static function cookie(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getCookieParams(), self::fieldFilter($field), $default);
    }

    public static function file(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getUploadedFiles(), self::fieldFilter($field), $default);
    }

    public static function attr(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getAttributes(), self::fieldFilter($field), $default);
    }

    public static function header(string $field = '', $default = null)
    {
        return self::getValue(Framework::getServerRequest()->getHeaders(), self::fieldFilter($field), $default);
    }

    private static function isSetValue(array $data = [], array $arr = []): bool
    {
        $key = array_shift($arr);
        if (!$arr) {
            return isset($data[$key]);
        }
        if (!isset($data[$key])) {
            return false;
        }
        return self::isSetValue($data[$key], $arr);
    }

    private static function getValue($data = [], array $arr = [], $default = null)
    {
        if (!$arr) {
            return $data;
        }
        if (!is_array($data)) {
            return $default;
        }
        $key = array_shift($arr);
        if (!$arr) {
            return isset($data[$key]) ? $data[$key] : $default;
        }
        if (!isset($data[$key])) {
            return $default;
        }
        return self::getValue($data[$key], $arr, $default);
    }

    private static function fieldFilter(string $field): array
    {
        return array_filter(
            explode('.', $field),
            function ($val) {
                return strlen($val) > 0 ? true : false;
            }
        );
    }
}

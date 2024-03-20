<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

class Request
{
    public function has(string $field): bool
    {
        $fields = $this->fieldFilter($field);
        $type = array_shift($fields);
        switch ($type) {
            case 'server':
                return $this->isSetValue(Framework::getServerRequest()->getServerParams(), $fields);
                break;

            case 'get':
                return $this->isSetValue(Framework::getServerRequest()->getQueryParams(), $fields);
                break;

            case 'post':
                return $this->isSetValue(Framework::getServerRequest()->getParsedBody(), $fields);
                break;

            case 'request':
                return $this->isSetValue(Framework::getServerRequest()->getQueryParams(), $fields) || $this->isSetValue(Framework::getServerRequest()->getParsedBody(), $fields);
                break;

            case 'cookie':
                return $this->isSetValue(Framework::getServerRequest()->getCookieParams(), $fields);
                break;

            case 'file':
                return $this->isSetValue(Framework::getServerRequest()->getUploadedFiles(), $fields);
                break;

            case 'attr':
                return $this->isSetValue(Framework::getServerRequest()->getAttributes(), $fields);
                break;

            case 'header':
                return $this->isSetValue(Framework::getServerRequest()->getHeaders(), $fields);
                break;

            default:
                return false;
                break;
        }
    }

    public function server(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getServerParams(), $this->fieldFilter($field), $default);
    }

    public function get(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getQueryParams(), $this->fieldFilter($field), $default);
    }

    public function post(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getParsedBody(), $this->fieldFilter($field), $default);
    }

    public function request(string $field = '', $default = null)
    {
        if ($this->has('get.' . $field)) {
            return $this->getValue(Framework::getServerRequest()->getQueryParams(), $this->fieldFilter($field), $default);
        } else {
            return $this->getValue(Framework::getServerRequest()->getParsedBody(), $this->fieldFilter($field), $default);
        }
    }

    public function cookie(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getCookieParams(), $this->fieldFilter($field), $default);
    }

    public function file(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getUploadedFiles(), $this->fieldFilter($field), $default);
    }

    public function attr(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getAttributes(), $this->fieldFilter($field), $default);
    }

    public function header(string $field = '', $default = null)
    {
        return $this->getValue(Framework::getServerRequest()->getHeaders(), $this->fieldFilter($field), $default);
    }

    private function isSetValue(array $data = [], array $arr = []): bool
    {
        $key = array_shift($arr);
        if (!$arr) {
            return isset($data[$key]);
        }
        if (!isset($data[$key])) {
            return false;
        }
        return $this->isSetValue($data[$key], $arr);
    }

    private function getValue($data = [], array $arr = [], $default = null)
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
        return $this->getValue($data[$key], $arr, $default);
    }

    private function fieldFilter(string $field): array
    {
        return array_filter(
            explode('.', $field),
            function ($val) {
                return strlen($val) > 0 ? true : false;
            }
        );
    }
}

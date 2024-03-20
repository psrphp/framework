<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use InvalidArgumentException;

class Config
{
    private $configs = [];

    public function get(string $key = '', $default = null)
    {
        list($filename, $appname, $paths) = $this->parseKey($key);
        $ck = $filename . '@' . $appname;

        if (!isset($this->configs[$ck])) {
            $this->configs[$ck] = $this->load($filename, $appname);
        }

        return $this->getValue($this->configs[$ck], $paths, $default);
    }

    public function set(string $key, $value = null)
    {
        list($filename, $appname, $paths) = $this->parseKey($key);
        $ck = $filename . '@' . $appname;

        if (!isset($this->configs[$ck])) {
            $this->configs[$ck] = $this->load($filename, $appname);
        }

        if (!$paths && !is_array($value)) {
            throw new Exception('the first level:[' . $ck . '] must be array!');
        }

        $this->setValue($this->configs[$ck], $paths, $value);
    }

    public function save(string $key, $value)
    {
        list($filename, $appname, $paths) = $this->parseKey($key);
        $ck = $filename . '@' . $appname;

        if (!isset($this->configs[$ck])) {
            $this->configs[$ck] = $this->load($filename, $appname);
        }

        if (!$paths && !is_array($value)) {
            throw new Exception('the first level:[' . $ck . '] must be array!');
        }

        $res = [];
        $file = dirname(__DIR__, 4) . '/config/' . $filename . '.php';
        if (is_file($file)) {
            $tmp = $this->requireFile($file);
            if (!is_array($tmp)) {
                throw new Exception('the config file:[' . $file . '] must return array!');
            }
            $res = $tmp;
        }

        $this->setValue($res, $paths, $value);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, '<?php return ' . var_export($res, true) . ';');
    }

    private function load(string $filename, string $appname): array
    {
        $files = [];
        $root = dirname(__DIR__, 4);
        if (!strlen($appname)) {
            if (file_exists($root . '/config/' . $filename . '.php')) {
                $files[] = $root . '/config/' . $filename . '.php';
            }
        } else {
            if (!array_key_exists($appname, Framework::getAppList())) {
                throw new Exception('配置读取错误：应用' . $appname . '不存在~');
            }
            $file = Framework::getAppList()[$appname] . '/src/config/' . $filename . '.php';
            if (file_exists($file)) {
                $files[] = $file;
            }
            if (file_exists($root . '/config/' . $appname . '/' . $filename . '.php')) {
                $files[] = $root . '/config/' . $appname . '/' . $filename . '.php';
            }
        }

        $res = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $tmp = $this->requireFile($file);
                if (is_array($tmp)) {
                    $res = array_merge($res, $tmp);
                } elseif (!is_null($tmp)) {
                    throw new Exception('the config file:[' . $file . '] must return array!');
                }
            }
        }
        return $res;
    }

    private function getValue($data, $paths, $default)
    {
        if (!$paths) {
            return $data;
        }

        $key = array_shift($paths);

        if (!isset($data[$key])) {
            return $default;
        }

        return $this->getValue($data[$key], $paths, $default);
    }

    private function setValue(&$data, array $paths, $value)
    {
        $key = array_shift($paths);
        if (is_null($key)) {
            $data = $value;
        } else {
            if (!isset($data[$key])) {
                $data[$key] = null;
            }
            $this->setValue($data[$key], $paths, $value);
        }
    }

    private function parseKey(string $key): array
    {
        $res = [];
        list($path, $group) = explode('@', $key . '@');
        if (!strlen($path)) {
            throw new InvalidArgumentException('Invalid Argument Exception');
        }

        $paths = array_filter(
            explode('.', $path),
            function ($val) {
                return strlen($val) > 0 ? true : false;
            }
        );

        $res[] = array_shift($paths);
        $res[] = str_replace('.', '/', $group);
        $res[] = $paths;
        return $res;
    }

    private function requireFile(string $file)
    {
        static $loader;
        if (!$loader) {
            $loader = new class()
            {
                public function load(string $file)
                {
                    return require $file;
                }
            };
        }
        return $loader->load($file);
    }
}

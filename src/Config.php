<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use InvalidArgumentException;

class Config
{
    private static $configs = [];

    public static function get(string $key = '', $default = null)
    {
        list($filename, $appname, $paths) = self::parseKey($key);
        $ck = $filename . '@' . $appname;

        if (!isset(self::$configs[$ck])) {
            self::$configs[$ck] = self::load($filename, $appname);
        }

        return self::getValue(self::$configs[$ck], $paths, $default);
    }

    public static function set(string $key, $value = null)
    {
        list($filename, $appname, $paths) = self::parseKey($key);
        $ck = $filename . '@' . $appname;

        if (!isset(self::$configs[$ck])) {
            self::$configs[$ck] = self::load($filename, $appname);
        }

        if (!$paths && !is_array($value)) {
            throw new Exception('the first level:[' . $ck . '] must be array!');
        }

        self::setValue(self::$configs[$ck], $paths, $value);
    }

    public static function save(string $key, $value)
    {
        list($filename, $appname, $paths) = self::parseKey($key);
        $ck = $filename . '@' . $appname;

        if (!isset(self::$configs[$ck])) {
            self::$configs[$ck] = self::load($filename, $appname);
        }

        if (!$paths && !is_array($value)) {
            throw new Exception('the first level:[' . $ck . '] must be array!');
        }

        $res = [];
        $file = dirname(__DIR__, 4) . '/config/' . $filename . '.php';
        if (is_file($file)) {
            $tmp = self::requireFile($file);
            if (!is_array($tmp)) {
                throw new Exception('the config file:[' . $file . '] must return array!');
            }
            $res = $tmp;
        }

        self::setValue($res, $paths, $value);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, '<?php return ' . var_export($res, true) . ';');
    }

    private static function load(string $filename, string $appname): array
    {
        $files = [];
        $root = dirname(__DIR__, 4);
        if (!strlen($appname)) {
            if (file_exists($root . '/config/' . $filename . '.php')) {
                $files[] = $root . '/config/' . $filename . '.php';
            }
        } else {
            if (!App::has($appname)) {
                throw new Exception('配置读取错误：应用' . $appname . '不存在~');
            }
            $file = App::getDir($appname) . '/src/config/' . $filename . '.php';
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
                $tmp = self::requireFile($file);
                if (is_array($tmp)) {
                    $res = array_merge($res, $tmp);
                } elseif (!is_null($tmp)) {
                    throw new Exception('the config file:[' . $file . '] must return array!');
                }
            }
        }
        return $res;
    }

    private static function getValue($data, $paths, $default)
    {
        if (!$paths) {
            return $data;
        }

        $key = array_shift($paths);

        if (!isset($data[$key])) {
            return $default;
        }

        return self::getValue($data[$key], $paths, $default);
    }

    private static function setValue(&$data, array $paths, $value)
    {
        $key = array_shift($paths);
        if (is_null($key)) {
            $data = $value;
        } else {
            if (!isset($data[$key])) {
                $data[$key] = null;
            }
            self::setValue($data[$key], $paths, $value);
        }
    }

    private static function parseKey(string $key): array
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

    private static function requireFile(string $file)
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

<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\Autoload\ClassLoader;
use Exception;
use InvalidArgumentException;
use ReflectionClass;

class Config
{
    private static $configs = [];

    public static function get(string $key = '', $default = null)
    {
        $parse = self::parseKey($key);

        if (!isset(self::$configs[$parse['key']])) {
            self::load($parse);
        }

        if (is_null(self::$configs[$parse['key']])) {
            return $default;
        } else {
            return self::getValue(self::$configs[$parse['key']], $parse['paths'], $default);
        }
    }

    public static function set(string $key, $value = null)
    {
        $parse = self::parseKey($key);

        if (!isset(self::$configs[$parse['key']])) {
            self::load($parse);
        }

        if (!$parse['paths'] && !is_array($value)) {
            throw new Exception('the first level:[' . $parse['key'] . '] must be array!');
        }

        self::setValue(self::$configs[$parse['key']], $parse['paths'], $value);
    }

    public static function save(string $key, $value)
    {
        $parse = self::parseKey($key);

        if (!isset(self::$configs[$parse['key']])) {
            self::load($parse);
        }

        if (!$parse['paths'] && !is_array($value)) {
            throw new Exception('the first level:[' . $parse['key'] . '] must be array!');
        }

        $res = null;
        if (is_file($parse['config_file'])) {
            $tmp = self::requireFile($parse['config_file']);
            if (is_array($tmp)) {
                $res = $tmp;
            } elseif (!is_null($tmp)) {
                throw new Exception('the config file:[' . $parse['config_file'] . '] must return array!');
            }
        }

        self::setValue($res, $parse['paths'], $value);

        if (!is_dir(dirname($parse['config_file']))) {
            mkdir(dirname($parse['config_file']), 0755, true);
        }

        file_put_contents($parse['config_file'], '<?php return ' . var_export($res, true) . ';');
    }

    private static function load(array $parse)
    {
        $args = [];

        if (isset($parse['default_file']) && is_file($parse['default_file'])) {
            $tmp = self::requireFile($parse['default_file']);
            if (is_array($tmp)) {
                $args[] = $tmp;
            } elseif (!is_null($tmp)) {
                throw new Exception('the config file:[' . $parse['default_file'] . '] must return array!');
            }
        }

        if (is_file($parse['config_file'])) {
            $tmp = self::requireFile($parse['config_file']);
            if (is_array($tmp)) {
                $args[] = $tmp;
            } elseif (!is_null($tmp)) {
                throw new Exception('the config file:[' . $parse['config_file'] . '] must return array!');
            }
        }

        self::$configs[$parse['key']] = $args ? array_merge(...$args) : null;
    }

    private static function getValue($data, $path, $default)
    {
        $key = array_shift($path);

        if (is_null($key)) {
            return $data;
        }

        if (!isset($data[$key])) {
            return $default;
        }

        return self::getValue($data[$key], $path, $default);
    }

    private static function setValue(&$data, $path, $value)
    {
        $key = array_shift($path);
        if (is_null($key)) {
            $data = $value;
        } else {
            if (!isset($data[$key])) {
                $data[$key] = null;
            }
            self::setValue($data[$key], $path, $value);
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

        $res['filename'] = array_shift($paths);
        $res['paths'] = $paths;
        $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));
        if (!strlen($group)) {
            $res['config_file'] = $root . '/config/' . $res['filename'] . '.php';
            $res['key'] = $res['filename'];
        } else {
            $group = str_replace('.', '/', $group);
            $res['default_file'] = App::get($group)['dir'] . '/src/config/' . $res['filename'] . '.php';
            $res['config_file'] = $root . '/config/' . $group . '/' . $res['filename'] . '.php';
            $res['key'] = $res['filename'] . '@' . $group;
        }

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

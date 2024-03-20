<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Exception;
use PDO;
use Throwable;

class Script
{
    public static function onInstall(PackageEvent $event)
    {
        /**
         * @var InstallOperation $operation
         */
        $operation = $event->getOperation();
        $package_name = $operation->getPackage()->getName();
        if ($callable = self::getCallable($package_name, 'onInstall')) {
            self::exec($callable, [
                PackageEvent::class => $event,
            ]);
        }
    }

    public static function onUnInstall(PackageEvent $event)
    {
        /**
         * @var UninstallOperation $operation
         */
        $operation = $event->getOperation();
        $package_name = $operation->getPackage()->getName();
        if ($callable = self::getCallable($package_name, 'onUnInstall')) {
            self::exec($callable, [
                PackageEvent::class => $event,
            ]);
        }
    }

    public static function onUpdate(PackageEvent $event)
    {
        /**
         * @var UpdateOperation $operation
         */
        $operation = $event->getOperation();
        $package_name = $operation->getTargetPackage()->getName();
        if ($callable = self::getCallable($package_name, 'onUpdate')) {
            self::exec($callable, [
                PackageEvent::class => $event,
            ]);
        }
    }

    private static function exec(callable $callable, $params = [])
    {
        start:
        try {
            Framework::execute($callable, $params);
        } catch (Throwable $th) {
            fwrite(STDOUT, "发生错误：" . $th->getMessage() . "\n");
            fwrite(STDOUT, "重试请输[r] 忽略请输[y] 终止请输[q]：");
            $input = trim((string) fgets(STDIN));
            switch ($input) {
                case '':
                case 'r':
                    goto start;
                    break;

                case 'y':
                    fwrite(STDOUT, "已忽略该错误~\n");
                    break;

                default:
                    throw new Exception("发生错误，终止！");
                    break;
            }
        }
    }

    private static function getCallable(string $package_name, string $action): ?callable
    {
        $file = dirname(__DIR__, 3) . '/' . $package_name . '/src/config/package.php';
        if (file_exists($file)) {
            $cfg = self::requireFile($file);
            if (isset($cfg[$action]) && is_callable($cfg[$action])) {
                return $cfg[$action];
            }
        }
        return null;
    }

    public static function execSql(string $sql)
    {
        $sqls = array_filter(explode(";" . PHP_EOL, $sql));

        $prefix = 'prefix_';
        $root = dirname(__DIR__, 4);
        $cfg_file = $root . '/config/database.php';
        $cfg = self::requireFile($cfg_file);
        if (isset($cfg['master']['prefix'])) {
            $prefix = $cfg['master']['prefix'];
        }

        $dsn = $cfg['master']['database_type'] . ':'
            . 'host=' . $cfg['master']['server'] . ';'
            . 'dbname=' . $cfg['master']['database_name'] . ';';
        $pdo = new PDO($dsn, $cfg['master']['username'], $cfg['master']['password'], $cfg['master']['option']);

        $pdo->exec('SET SQL_MODE=ANSI_QUOTES');
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');

        foreach ($sqls as $sql) {
            $pdo->exec(str_replace('prefix_', $prefix, $sql . ';'));
        }
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

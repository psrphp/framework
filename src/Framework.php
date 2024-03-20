<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use GuzzleHttp\Psr7\ServerRequest;
use PsrPHP\Psr11\Container;
use PsrPHP\Psr14\Event;
use PsrPHP\Psr16\NullAdapter;
use PsrPHP\Psr17\Factory;
use PsrPHP\Router\Router;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use PsrPHP\Database\Db;
use PsrPHP\Session\Session;

class Framework
{
    public static function run()
    {
        static $run;
        if ($run) {
            return;
        }
        $run = true;

        self::execute(function (
            Event $event,
            ListenerProvider $listenerProvider,
        ) {
            $event->addProvider($listenerProvider);
        });

        self::execute(function (
            Event $event,
            Container $container
        ) {
            $event->dispatch($container);
        });

        self::execute(function (
            Event $event,
            Router $router
        ) {
            $event->dispatch($router);
        });

        self::execute(function (
            RequestHandler $requestHandler,
            ResponseEmitter $responseEmitter,
        ) {
            $serverRequest = self::getServerRequest();
            $response = $requestHandler->handle($serverRequest);
            $responseEmitter->emit($response);
        });
    }

    public static function execute(callable $callable, array $params = [])
    {
        $args = self::getContainer()->reflectArguments($callable, $params);
        return call_user_func($callable, ...$args);
    }

    public static function getServerRequest(): ServerRequest
    {
        return ServerRequest::fromGlobals()->withQueryParams(array_merge($_GET, self::getRoute()->getParams()));
    }

    public static function getDb(): Db
    {
        return self::getContainer()->get(Db::class);
    }

    public static function getTemplate(): Template
    {
        return self::getContainer()->get(Template::class);
    }

    public static function getRouter(): Router
    {
        return self::getContainer()->get(Router::class);
    }

    public static function getRoute(): Route
    {
        return self::getContainer()->get(Route::class);
    }

    public static function getEvent(): Event
    {
        return self::getContainer()->get(Event::class);
    }

    public static function getCache(): CacheInterface
    {
        return self::getContainer()->get(CacheInterface::class);
    }

    public static function getLogger(): LoggerInterface
    {
        return self::getContainer()->get(LoggerInterface::class);
    }

    public static function getHttpFactory(): Factory
    {
        return self::getContainer()->get(Factory::class);
    }

    public static function getSession(): Session
    {
        return self::getContainer()->get(Session::class);
    }

    public static function getConfig(): Config
    {
        return self::getContainer()->get(Config::class);
    }

    public static function getRequest(): Request
    {
        return self::getContainer()->get(Request::class);
    }

    public static function getAppList(): array
    {
        static $apps;
        if (is_null($apps)) {
            $root = dirname(__DIR__, 4);
            if (file_exists($root . '/vendor/composer/installed.json')) {
                foreach (json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true)['packages'] as $pkg) {
                    if ($pkg['type'] != 'psrapp') {
                        continue;
                    }
                    if (file_exists($root . '/config/' . $pkg['name'] . '/disabled.lock')) {
                        continue;
                    }
                    $apps[$pkg['name']] = $root . '/vendor/' . $pkg['name'];
                }
            }

            spl_autoload_register(function (string $class) use ($root) {
                $paths = explode('\\', $class);
                if (isset($paths[3]) && $paths[0] == 'App' && $paths[1] == 'Plugin') {
                    $file = $root . '/plugin/'
                        . strtolower(preg_replace('/([A-Z])/', "-$1", lcfirst($paths[2])))
                        . '/src/library/'
                        . str_replace('\\', '/', substr($class, strlen($paths[0]) + strlen($paths[1]) + strlen($paths[2]) + 3))
                        . '.php';
                    if (file_exists($file)) {
                        include $file;
                    }
                }
            });

            foreach (scandir($root . '/plugin') as $vo) {
                if (in_array($vo, array('.', '..'))) {
                    continue;
                }
                if (!is_dir($root . '/plugin' . DIRECTORY_SEPARATOR . $vo)) {
                    continue;
                }
                $appname = 'plugin/' . $vo;
                if (file_exists($root . '/config/' . $appname . '/disabled.lock')) {
                    continue;
                }
                if (!file_exists($root . '/config/' . $appname . '/install.lock')) {
                    continue;
                }
                $apps[$appname] = $root . '/' . $appname;
            }
        }
        return $apps;
    }

    public static function getContainer(): Container
    {
        static $container;
        if ($container == null) {
            $container = new Container;
            foreach ([
                Container::class => $container,
                ContainerInterface::class => $container,
                LoggerInterface::class => NullLogger::class,
                CacheInterface::class => NullAdapter::class,
                ResponseFactoryInterface::class => Factory::class,
                UriFactoryInterface::class => Factory::class,
                ServerRequestFactoryInterface::class => Factory::class,
                RequestFactoryInterface::class => Factory::class,
                StreamFactoryInterface::class => Factory::class,
                UploadedFileFactoryInterface::class => Factory::class,
                EventDispatcherInterface::class => Event::class,
                ListenerProviderInterface::class => Event::class,
            ] as $key => $obj) {
                $container->set($key, function () use ($obj, $container) {
                    return is_string($obj) ? $container->get($obj) : $obj;
                });
            }
        }

        return $container;
    }
}

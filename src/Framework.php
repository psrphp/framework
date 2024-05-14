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

        self::getEvent()->addProvider(new Provider);

        self::getEvent()->dispatch(self::getContainer());

        self::getEvent()->dispatch(self::getRouter());

        $response = (new Handler)->handle(self::getServerRequest());
        (new Emitter)->emit($response);
    }

    public static function execute(callable $callable, array $params = [])
    {
        $args = self::getContainer()->reflectArguments($callable, $params);
        return call_user_func($callable, ...$args);
    }

    public static function getServerRequest(): ServerRequest
    {
        static $serverRequest;
        if (is_null($serverRequest)) {
            $serverRequest = ServerRequest::fromGlobals()->withQueryParams(array_merge($_GET, Route::getParams()));
        }
        return $serverRequest;
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

    public static function getRequest(): Request
    {
        return self::getContainer()->get(Request::class);
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

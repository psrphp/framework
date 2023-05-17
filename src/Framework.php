<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Exception;
use PsrPHP\Framework\Route;
use PsrPHP\Psr3\LocalLogger;
use PsrPHP\Psr11\Container;
use PsrPHP\Psr14\Event;
use PsrPHP\Psr15\RequestHandler;
use PsrPHP\Psr16\LocalAdapter;
use PsrPHP\Psr17\Factory;
use PsrPHP\Responser\Emitter;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use PsrPHP\Plugin\Plugin;
use PsrPHP\Template\Template;
use PsrPHP\Theme\Theme;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class Framework
{
    public static function run()
    {
        static $run;
        if ($run) {
            return;
        }
        $run = true;

        if (!class_exists(InstalledVersions::class)) {
            die('composer 2 is required!');
        }

        self::hook('onInit');

        self::execute(function (
            RequestHandler $requestHandler,
            ServerRequestInterface $serverRequest,
            Route $route,
            Emitter $emitter
        ) {
            self::hook('onStart');

            foreach ($route->getMiddleWares() as $middleware) {
                $requestHandler->appendMiddleware(is_string($middleware) ? self::getContainer()->get($middleware) : $middleware);
            }

            $handler = self::renderHandler($route);
            $response = $requestHandler->setHandler($handler)->handle($serverRequest);

            $emitter->emit($response);

            self::hook('onEnd');
        });
    }

    public static function hook(string $action, array $args = [])
    {
        foreach (array_keys(self::getAppList()) as $app) {
            $class_name = str_replace(['-', '/'], ['', '\\'], ucwords('App\\' . $app . '\\App', '/\\-'));
            if (method_exists($class_name, $action)) {
                self::execute([$class_name, $action], $args);
            }
        }
    }

    public static function getAppList(): array
    {
        static $list;
        if (!is_null($list)) {
            return $list;
        }
        $list = self::execute(function (
            CacheInterface $cache
        ): array {
            if (null == $list = $cache->get('applist!system')) {
                $list = [];
                $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));
                foreach (array_unique(InstalledVersions::getInstalledPackages()) as $app) {
                    $class_name = str_replace(['-', '/'], ['', '\\'], ucwords('App\\' . $app . '\\App', '/\\-'));
                    if (
                        !class_exists($class_name)
                        || !is_subclass_of($class_name, AppInterface::class)
                    ) {
                        continue;
                    }
                    if (file_exists($root . '/config/' . $app . '/disabled.lock')) {
                        continue;
                    }
                    $list[$app] = [
                        'name' => $app,
                        'dir' => dirname(dirname(dirname((new ReflectionClass($class_name))->getFileName()))),
                    ];
                }
                $cache->set('applist!system', $list, 86400);
            }
            return $list;
        });
        if (class_exists(Plugin::class)) {
            self::execute(function (
                Plugin $plugin
            ) use (&$list) {
                $plugin->run($list);
            });
        }
        return $list;
    }

    public static function execute(callable $callable, array $default_args = [])
    {
        if (is_array($callable)) {
            $args = self::getContainer()->reflectArguments(new ReflectionMethod(...$callable), $default_args);
        } elseif (is_object($callable)) {
            $args = self::getContainer()->reflectArguments(new ReflectionMethod($callable, '__invoke'), $default_args);
        } elseif (is_string($callable) && strpos($callable, '::')) {
            $args = self::getContainer()->reflectArguments(new ReflectionMethod($callable), $default_args);
        } else {
            $args = self::getContainer()->reflectArguments(new ReflectionFunction($callable), $default_args);
        }
        return call_user_func($callable, ...$args);
    }

    public static function getContainer(): Container
    {
        static $container;
        if ($container == null) {
            $container = new Container;
            $config = new Config;
            foreach (array_merge([
                ContainerInterface::class => $container,
                LoggerInterface::class => LocalLogger::class,
                CacheInterface::class => LocalAdapter::class,
                RequestHandlerInterface::class => RequestHandler::class,
                ResponseFactoryInterface::class => Factory::class,
                UriFactoryInterface::class => Factory::class,
                ServerRequestFactoryInterface::class => Factory::class,
                RequestFactoryInterface::class => Factory::class,
                StreamFactoryInterface::class => Factory::class,
                UploadedFileFactoryInterface::class => Factory::class,
                EventDispatcherInterface::class => Event::class,
                ListenerProviderInterface::class => Event::class,
            ], $config->get('alias', []), [
                Container::class => $container,
                Config::class => $config,
            ]) as $key => $obj) {
                $container->set($key, function () use ($obj, $container) {
                    return is_string($obj) ? $container->get($obj) : $obj;
                });
            }

            $container->set(ServerRequestInterface::class, function (
                Factory $factory,
                Route $route
            ): ServerRequestInterface {
                $server_request = $factory->createServerRequestFromGlobals();
                return $server_request
                    ->withQueryParams(array_merge($server_request->getQueryParams(), $route->getParams()));
            });

            $container->set(Template::class, function (
                CacheInterface $cache
            ): Template {
                $template = new Template($cache);
                foreach (Framework::getAppList() as $app) {
                    $template->addPath($app['name'], $app['dir'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'template');
                }
                return $template;
            });

            $themename = $config->get('theme.name', '');
            if ($themename && class_exists(Theme::class)) {
                self::execute(function (
                    Theme $theme
                ) use ($themename) {
                    $theme->set($themename);
                });
            }
        }
        return $container;
    }

    private static function renderHandler(Route $route): callable
    {
        if (!$route->isFound()) {
            return function (): ResponseInterface {
                return self::execute(function (
                    Factory $factory
                ) {
                    return $factory->createResponse(404);
                });
            };
        }

        if ($route->getApp() && !isset(self::getAppList()[$route->getApp()])) {
            return function (): ResponseInterface {
                return self::execute(function (
                    Factory $factory
                ) {
                    return $factory->createResponse(404);
                });
            };
        }

        if (!$route->isAllowed()) {
            return function (): ResponseInterface {
                return self::execute(function (
                    Factory $factory
                ) {
                    return $factory->createResponse(405);
                });
            };
        }

        $handler = $route->getHandler();

        if (is_array($handler)) {
            if (false === (new ReflectionMethod(...$handler))->isStatic()) {
                if (!is_object($handler[0])) {
                    $handler[0] = self::getContainer()->get($handler[0]);
                }
            }
        } elseif (is_string($handler) && class_exists($handler)) {
            $handler = self::getContainer()->get($handler);
        }

        if (!is_callable($handler)) {
            return function (): ResponseInterface {
                return self::execute(function (
                    Factory $factory
                ) {
                    return $factory->createResponse(404);
                });
            };
        }

        return function () use ($handler): ResponseInterface {
            return self::execute(function (
                Factory $factory
            ) use ($handler): ResponseInterface {

                $resp = self::execute($handler);

                if (is_null($resp)) {
                    return $factory->createResponse(200);
                }

                if ($resp instanceof ResponseInterface) {
                    return $resp;
                }

                if (is_scalar($resp) || (is_object($resp) && method_exists($resp, '__toString'))) {
                    $response = $factory->createResponse(200);
                    $response->getBody()->write('' . $resp);
                    return $response;
                } else {
                    throw new Exception('Unrecognized Response');
                }
            });
        };
    }
}

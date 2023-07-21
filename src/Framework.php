<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use GuzzleHttp\Psr7\ServerRequest;
use PsrPHP\Psr3\LocalLogger;
use PsrPHP\Psr11\Container;
use PsrPHP\Psr14\Event;
use PsrPHP\Psr16\NullAdapter;
use PsrPHP\Psr17\Factory;
use PsrPHP\Responser\Emitter;
use PsrPHP\Router\Router;
use PsrPHP\Template\Template;
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
use ReflectionClass;

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

        self::execute(function (
            Event $event,
            Listener $listener
        ) {
            $event->addProvider($listener);
        });

        self::execute(function () {
            $root = dirname(dirname(dirname((new ReflectionClass(ClassLoader::class))->getFileName())));

            foreach (json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true)['packages'] as $pkg) {
                if ($pkg['type'] != 'psrapp') {
                    continue;
                }
                if (file_exists($root . '/config/' . $pkg['name'] . '/disabled.lock')) {
                    continue;
                }
                App::set($pkg['name'], $root . '/vendor/' . $pkg['name']);
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

            $dir = $root . '/plugin';
            foreach (scandir($dir) as $vo) {
                if (in_array($vo, array('.', '..'))) {
                    continue;
                }
                if (!is_dir($dir . DIRECTORY_SEPARATOR . $vo)) {
                    continue;
                }
                $app = 'plugin/' . $vo;
                if (file_exists($root . '/config/' . $app . '/disabled.lock')) {
                    continue;
                }
                if (!file_exists($root . '/config/' . $app . '/install.lock')) {
                    continue;
                }
                App::set($app, $root . '/' . $app);
            }
        });

        self::execute(function (
            Event $event,
            App $app
        ) {
            $event->dispatch($app);
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
            Router $router
        ) {
            $uri = ServerRequest::getUriFromGlobals();
            $res = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', '' . $uri->withQuery(''));

            Route::setFound($res[0]);
            Route::setAllowed($res[1] ?? false);
            Route::setHandler($res[2] ?? '');
            Route::setMiddlewares($res[3] ?? []);
            Route::setParams($res[5] ?? []);

            if (!Route::isFound()) {
                $paths = explode('/', $uri->getPath());
                $pathx = explode('/', $_SERVER['SCRIPT_NAME']);
                foreach ($pathx as $key => $value) {
                    if (isset($paths[$key]) && ($paths[$key] == $value)) {
                        unset($paths[$key]);
                    }
                }
                if (count($paths) >= 3) {
                    array_splice($paths, 0, 0, 'App');
                    array_splice($paths, 3, 0, 'Http');
                    Route::setFound(true);
                    Route::setAllowed(true);
                    Route::setHandler(str_replace(['-'], [''], ucwords(implode('\\', $paths), '\\-')));
                }
            }

            if (Route::isFound()) {
                if (!is_subclass_of(Route::getHandler(), RequestHandlerInterface::class)) {
                    Route::setFound(false);
                }
                $paths = explode('\\', Route::getHandler());
                if (!isset($paths[4]) || $paths[0] != 'App' || $paths[3] != 'Http') {
                    Route::setFound(false);
                }
            }

            if (Route::isFound()) {
                $paths = explode('\\', Route::getHandler());
                $camelToLine = function (string $str): string {
                    return strtolower(preg_replace('/([A-Z])/', "-$1", lcfirst($str)));
                };
                $appname = $camelToLine($paths[1]) . '/' . $camelToLine($paths[2]);
                if (!App::has($appname)) {
                    Route::setFound(false);
                }
            }
        });

        self::execute(function (
            Event $event,
            Route $route
        ) {
            $event->dispatch($route);
        });

        self::execute(function (
            Route $route,
            Handler $handler,
            Event $event,
            ServerRequestInterface $serverRequest,
            ResponseFactoryInterface $responseFactory,
            Emitter $emitter
        ) {
            Handler::pushMiddleware(...$route->getMiddleWares());
            $event->dispatch($handler);

            if (!$route->isFound()) {
                $request_handler = self::makeRequestHandler($responseFactory->createResponse(404));
            } else if (!$route->isAllowed()) {
                $request_handler = self::makeRequestHandler($responseFactory->createResponse(405));
            } else {
                $request_handler = self::getContainer()->get($route->getHandler());
            }
            $response = $handler->run($request_handler, $serverRequest);
            $event->dispatch($response);
            $emitter->emit($response);
        });
    }

    public static function execute(callable $callable, array $params = [])
    {
        $args = self::getContainer()->reflectArguments($callable, $params);
        return call_user_func($callable, ...$args);
    }

    public static function getContainer(): Container
    {
        static $container;
        if ($container == null) {
            $container = new Container;
            foreach ([
                Container::class => $container,
                ContainerInterface::class => $container,
                LoggerInterface::class => LocalLogger::class,
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

            $container->set(Template::class, function (
                Template $template
            ): Template {
                foreach (App::all() as $app) {
                    $template->addPath($app['name'], $app['dir'] . '/src/template');
                }
                $root = dirname(dirname(dirname((new ReflectionClass(InstalledVersions::class))->getFileName())));
                foreach (Config::get('theme', []) as $key => $name) {
                    foreach (App::all() as $app) {
                        $template->addPath($app['name'], $root . '/theme/' . $name . '/' . $app['name'], 99 - $key);
                    }
                }
                return $template;
            });

            $container->set(ServerRequestInterface::class, function (
                Route $route
            ): ServerRequestInterface {
                $request = ServerRequest::fromGlobals();
                return $request->withQueryParams(array_merge($request->getQueryParams(), $route->getParams()));
            });
        }

        return $container;
    }

    private static function makeRequestHandler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface
        {
            private $response;
            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}

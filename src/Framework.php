<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Composer\InstalledVersions;
use GuzzleHttp\Psr7\ServerRequest;
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
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

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
            App $app,
            Event $event,
            Container $container,
        ) {
            foreach ($app->all() as $vo) {
                $cls = 'App\\' . str_replace(['-', '/'], ['', '\\'], ucwords($vo['name'], '/-')) . '\\Psrphp\\ListenerProvider';
                if (class_exists($cls) && is_subclass_of($cls, ListenerProviderInterface::class)) {
                    $event->addProvider($container->get($cls));
                }
            }
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
            Event $event,
            Route $route
        ) {
            $event->dispatch($route);
        });

        self::execute(function (
            Event $event,
            Route $route,
            Handler $handler,
        ) {
            $handler->pushMiddleware(...$route->getMiddleWares());
            $event->dispatch($handler);
        });

        self::execute(function (
            Route $route,
            Event $event,
            Emitter $emitter,
            Handler $handler,
            ServerRequestInterface $serverRequest,
            ResponseFactoryInterface $responseFactory,
        ) {
            if (!$route->isFound()) {
                $requestHandler = self::makeRequestHandler($responseFactory->createResponse(404));
            } else if (!$route->isAllowed()) {
                $requestHandler = self::makeRequestHandler($responseFactory->createResponse(405));
            } else {
                $requestHandler = self::getContainer()->get($route->getHandler());
            }
            $event->dispatch($requestHandler);

            $response = $handler->run($requestHandler, $serverRequest);
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

            $container->set(Template::class, function (
                App $app,
                Template $template
            ): Template {
                foreach ($app->all() as $vo) {
                    $template->addPath($vo['name'], $vo['dir'] . '/src/template');
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

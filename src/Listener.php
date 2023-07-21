<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Psr\EventDispatcher\ListenerProviderInterface;

class Listener implements ListenerProviderInterface
{
    private $app;
    private $config;

    public function __construct(
        App $app,
        Config $config
    ) {
        $this->app = $app;
        $this->config = $config;
    }

    public function getListenersForEvent(object $event): iterable
    {
        $class = get_class($event);
        foreach ($this->app->all() as $app) {
            foreach ($this->config->get('event@' . $app['name'], []) as $type => $listeners) {
                if (!is_subclass_of($class, $type) && $class != $type) {
                    continue;
                }
                foreach ($listeners as $listener) {
                    yield function () use ($listener, $class, $event) {
                        Framework::execute($listener, [
                            $class => $event
                        ]);
                    };
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Psr\EventDispatcher\ListenerProviderInterface;

class Listener implements ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable
    {
        $class = get_class($event);
        foreach (App::all() as $app) {
            foreach (Config::get('event@' . $app['name'], []) as $type => $listeners) {
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

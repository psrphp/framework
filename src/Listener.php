<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Psr\EventDispatcher\ListenerProviderInterface;

abstract class Listener implements ListenerProviderInterface
{
    protected $listeners = [];

    protected function add(string $cls, callable $callable)
    {
        $this->listeners[$cls][] = $callable;
    }

    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->listeners as $type => $listeners) {
            if (!is_a($event, $type, true)) {
                continue;
            }
            foreach ($listeners as $listener) {
                yield function () use ($listener, $type, $event) {
                    Framework::execute($listener, [
                        $type => $event,
                    ]);
                };
            }
        }
    }
}

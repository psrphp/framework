<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Psr\EventDispatcher\ListenerProviderInterface;

class Provider implements ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable
    {
        foreach (App::getList() as $appname) {
            foreach (Config::get('listen@' . $appname, []) as $key => $value) {
                if (is_a($event, $key)) {
                    // yield $value;
                    yield function ($event) use ($key, $value) {
                        Framework::execute($value, [
                            $key => $event,
                        ]);
                    };
                }
            }
        }
    }
}

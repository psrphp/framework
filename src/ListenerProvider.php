<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Psr\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable
    {
        foreach (Framework::getAppList() as $appname => $appdir) {
            foreach (Framework::getConfig()->get('listen@' . $appname, []) as $key => $value) {
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

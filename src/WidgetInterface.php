<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

interface WidgetInterface
{
    public function getTitle(): string;
    public function getContent(): string;
}

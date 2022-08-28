<?php

namespace Sillynet\Adretto\Action;

abstract class WordpressHookAction implements Action
{
    public const WP_HOOK = 'some-wordpress-hook';
    public const ARGUMENT_COUNT = 1;
    public const PRIORITY = 10;

    public static function getWpHookName(): string
    {
        return static::WP_HOOK;
    }

    public static function getArgumentCount(): int
    {
        return static::ARGUMENT_COUNT;
    }

    public static function getPriority(): int
    {
        return static::PRIORITY;
    }

    public function getData(): array
    {
        return [];
    }
}

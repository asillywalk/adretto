<?php

namespace Sillynet\Adretto\Action;

abstract class WordpressHookAction implements HookAction
{
    public const ARGUMENT_COUNT = 1;
    public const PRIORITY = 10;

    /**
     * Specify the Wordpress (or custom) hook that you want to attach this
     * action to.
     */
    abstract public static function getWpHookName(): string;

    abstract public function getHandler(): callable;

    public static function getArgumentCount(): int
    {
        return static::ARGUMENT_COUNT;
    }

    public static function getPriority(): int
    {
        return static::PRIORITY;
    }
}

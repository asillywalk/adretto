<?php

namespace Sillynet\Adretto\Action;

interface HookAction extends Action
{
    public static function getWpHookName(): string;

    public static function getPriority(): int;

    public static function getArgumentCount(): int;

    public function getHandler(): callable;
}

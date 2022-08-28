<?php

namespace Sillynet\Adretto\Action;

abstract class InvokerWordpressHookAction extends WordpressHookAction
{
    /**
     * @param array<mixed> ...$args
     * @return mixed
     */
    abstract public function __invoke(...$args);

    public function getHandler(): callable
    {
        return $this;
    }
}

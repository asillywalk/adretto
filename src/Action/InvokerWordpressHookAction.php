<?php

namespace Sillynet\Adretto\Action;

abstract class InvokerWordpressHookAction extends WordpressHookAction
{
    public function getHandler(): self
    {
        return $this;
    }
}

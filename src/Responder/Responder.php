<?php

namespace Sillynet\Adretto\Responder;

use Sillynet\Adretto\Action\Action;

interface Responder
{
    public function respond(Action $action);
}

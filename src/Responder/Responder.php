<?php

namespace Sillynet\Adretto\Responder;

use Sillynet\Adretto\Action\Action;

interface Responder
{
    /**
     * @return void|null|mixed
     */
    public function respond(Action $action);
}

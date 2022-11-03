<?php

namespace Sillynet\Adretto\Responder;

use Sillynet\Adretto\Action\Action;

/**
 * @phpstan-template A of Action
 * @template A of Action
 */
interface Responder
{
    /**
     * @param A $action
     * @return void|null|mixed
     */
    public function respond(Action $action);
}

<?php

namespace Sillynet\Adretto\Modules\REST\Action;

use Sillynet\Adretto\Action\ActionHookAction;
use Sillynet\Adretto\Action\InvokerWordpressHookAction;
use Sillynet\Adretto\Modules\REST\RestRoute;
use WP_REST_Request;

abstract class RestAction extends InvokerWordpressHookAction implements
    ActionHookAction
{
    public static string $restNamespaceBase = 'sillynet';
    public static string $restApiVersion = '1';

    /**
     * This is where you set up your route: Create a new RestRoute object and
     * configure it according to your needs – just don't set a callback, we'll
     * handle that for you (it will be set to this object's "handle()" method).
     *
     * @return RestRoute
     */
    abstract protected function getRoute(): RestRoute;

    /**
     * This is the callback for your REST route.
     *
     * @return mixed
     */
    abstract public function handle(WP_REST_Request $request);

    public function __invoke(...$args): void
    {
        $route = $this->getRoute();
        $route->setCallback([$this, 'handle']);
        register_rest_route(
            self::getRestNamespace(),
            $route->getPath(),
            $route->getConfig(),
        );
    }

    /**
     * @inheritDoc
     */
    public static function getWpHookName(): string
    {
        return 'rest_api_init';
    }

    public static function getRestNamespace(): string
    {
        return static::$restNamespaceBase . '/v' . static::$restApiVersion;
    }
}

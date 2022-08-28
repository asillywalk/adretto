<?php

namespace Sillynet\Adretto;

use Psr\Container\ContainerInterface;

interface ContainerServiceFactoryFactory
{
    public static function getFactory(): callable;
}

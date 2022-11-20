<?php

namespace Sillynet\Adretto\Contracts;

interface AdrettoExtension
{
    /**
     * @return array<string, array<string, string>> The service definitions
     */
    public function getServices(): array;

    /**
     * @return array<class-string>
     */
    public function getActions(): array;
}

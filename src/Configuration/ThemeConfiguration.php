<?php

namespace Sillynet\Adretto\Configuration;

use Symfony\Component\Yaml\Yaml;

class ThemeConfiguration
{
    /** @var array<string, mixed> */
    protected array $config;

    protected string $configFilePath;

    public function __construct(string $configFilePath)
    {
        $this->configFilePath = $configFilePath;
    }

    public function get(string $key = null)
    {
        if (!isset($this->config)) {
            $this->config = Yaml::parseFile($this->configFilePath);
        }

        if ($key !== null) {
            return $this->config[$key] ?? [];
        }

        return $this->config;
    }
}

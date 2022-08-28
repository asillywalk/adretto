<?php

declare(strict_types=1);

namespace Sillynet\Adretto;

use DI\Container;
use DI\ContainerBuilder;
use Gebruederheitz\SimpleSingleton\Singleton;
use Psr\Container\ContainerInterface;
use Sillynet\Adretto\Action\Action;
use Sillynet\Adretto\Action\ActionHookAction;
use Sillynet\Adretto\Action\CustomAction;
use Sillynet\Adretto\Action\FilterHookAction;
use Sillynet\Adretto\Action\HookAction;
use Sillynet\Adretto\Configuration\ThemeConfiguration;
use Sillynet\Adretto\Exception\InvalidUsageException;

use Throwable;

use function DI\create;
use function DI\factory;

class Theme extends Singleton implements Application
{
    public const FILTER_ACTIONS = "sillynet-filter-actions";

    protected array $actions = [];

    protected ContainerInterface $container;

    protected string $configFilePath;

    protected ThemeConfiguration $config;

    /**
     * @throws InvalidUsageException|Throwable
     */
    public static function make(string $configFilePath): static
    {
        $theme = static::getInstance();
        $theme->setConfigFilePath($configFilePath);
        $theme->init();
        return $theme;
    }

    /**
     * Returns the current theme version as read from the style.css.
     *
     * @return string
     */
    public static function getThemeVersion(): string
    {
        return wp_get_theme()->get("Version");
    }

    /**
     * @param class-string $actionClass
     */
    public function addAction(string $actionClass): void
    {
        $this->actions[] = $actionClass;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    protected function setConfigFilePath(string $configFilePath): void
    {
        $this->configFilePath = $configFilePath;
    }

    /**
     * @throws InvalidUsageException|Throwable
     */
    protected function init()
    {
        $this->config = new ThemeConfiguration($this->configFilePath);
        $containerBuilder = new ContainerBuilder(Container::class);
        $definitions = $this->parseServiceDefinitions();
        $containerBuilder->addDefinitions($definitions);

        if (getenv("WORDPRESS_ENV") === "production") {
            /*
             * @TODO: post-install task to clear /var directory after deployments
             */
            $containerBuilder->enableCompilation(
                get_template_directory() . "/var/container/"
            );
            $containerBuilder->writeProxiesToFile(
                true,
                get_template_directory() . "/var/container/proxies"
            );
        }

        $this->container = $containerBuilder->build();
        $this->container->set(ThemeConfiguration::class, $this->config);
        $this->discoverActionHandlers();
        $this->autoload();
        add_action("after_setup_theme", [$this, "onAfterSetupTheme"]);
    }

    public function onAfterSetupTheme(): void
    {
        $this->addThemeSupports();
        $this->addTextDomain();
        $this->registerMenus();
    }

    protected function addTextDomain()
    {
        $textDomain = $this->config->get("themeTextDomain");

        if (!empty($textDomain) && is_string($textDomain)) {
            load_theme_textdomain(
                $textDomain,
                get_template_directory() . "/languages"
            );
        }
    }

    protected function addThemeSupports()
    {
        $supports = $this->config->get("themeSupports");
        foreach ($supports as $themeSupportDefinition) {
            $themeSupport = $themeSupportDefinition["name"];
            $args = $themeSupportDefinition["args"] ?? [];
            add_theme_support($themeSupport, ...$args);
        }
    }

    protected function autoload(): void
    {
        $autoloadClasses = $this->config->get("autoload");
        foreach ($autoloadClasses as $className) {
            $this->container->call([$className, "__construct"]);
        }
    }

    /**
     * @throws InvalidUsageException
     * @throws Throwable
     */
    protected function discoverActionHandlers(): void
    {
        /** @var Action[] $actions */
        $actions = $this->config->get("actions");
        $actions = array_merge($actions, $this->actions);
        $actions = apply_filters(self::FILTER_ACTIONS, $actions);

        foreach ($actions as $action) {
            $ref = new \ReflectionClass($action);
            $interfaceNames = $ref->getInterfaceNames();

            if (in_array(ActionHookAction::class, $interfaceNames)) {
                $this->initHookAction($action, "add_action");
            } elseif (in_array(FilterHookAction::class, $interfaceNames)) {
                $this->initHookAction($action, "add_filter");
            } elseif (in_array(CustomAction::class, $interfaceNames)) {
                $this->initCustomAction($action);
            } else {
                throw new InvalidUsageException(
                    "Do not implement ActionHandler directly: Use one of ActionHookActionHandler or FilterHookActionHandler."
                );
            }
        }
    }

    /**
     * @param class-string<HookAction> $action
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initHookAction(
        string $action,
        callable $registrationFunction
    ): void {
        $registrationFunction(
            $action::getWpHookName(),
            function (...$args) use ($action) {
                $action = $this->container->get($action);
                $handler = $action->getHandler();
                return $this->container->call($handler, $args);
            },
            $action::getPriority(),
            $action::getArgumentCount()
        );
    }

    /**
     * @param class-string<CustomAction> $action
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initCustomAction(string $actionClassName): void
    {
        $action = $this->container->call([$actionClassName, "__construct"]);
        $this->container->set($actionClassName, $action);
    }

    protected function parseServiceDefinitions(): array
    {
        // parse yaml for services into appropriate array
        $rawDefinitions = $this->config->get("services");
        $definitions = [];

        foreach ($rawDefinitions as $name => $definition) {
            switch ($definition["type"]) {
                case "parameter":
                    $definitions[$name] = $definition["value"];
                    break;
                case "factory":
                    $factory = $definition["value"]::getFactory();
                    $definitions[$name] = factory($factory);
                    break;
                case "class":
                default:
                    $definitions[$name] = $definition["value"]
                        ? create($definition["value"])
                        : create($name);
            }
        }

        return $definitions;
    }

    protected function registerMenus(): void
    {
        foreach ($this->config->get("menus") as $menuLocation => $description) {
            register_nav_menu($menuLocation, $description);
        }
    }
}

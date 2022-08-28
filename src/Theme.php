<?php

declare(strict_types=1);

namespace Sillynet\Adretto;

use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Helper\CreateDefinitionHelper;
use DI\Definition\Helper\FactoryDefinitionHelper;
use Gebruederheitz\SimpleSingleton\Singleton;
use Invoker\InvokerInterface;
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

/**
 * @phpstan-type ThemeSupportsDefinition array{name: string, args?: array<mixed>}
 */
class Theme extends Singleton implements Application
{
    public const FILTER_ACTIONS = 'sillynet-filter-actions';

    /** @var array<class-string>  */
    protected array $actions = [];

    protected Container $container;

    protected InvokerInterface $invoker;

    protected string $configFilePath;

    protected ThemeConfiguration $config;

    /**
     * @throws InvalidUsageException|Throwable
     */
    public static function make(string $configFilePath): Theme
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
        $version = wp_get_theme()->get('Version');
        return is_string($version) ? $version : '';
    }

    /**
     * @param class-string $actionClass
     */
    public function addAction(string $actionClass): void
    {
        $this->actions[] = $actionClass;
    }

    public function getContainer(): Container
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
    protected function init(): void
    {
        $this->config = new ThemeConfiguration($this->configFilePath);
        $containerBuilder = new ContainerBuilder(Container::class);
        $definitions = $this->parseServiceDefinitions();
        $containerBuilder->addDefinitions($definitions);

        if (getenv('WORDPRESS_ENV') === 'production') {
            /*
             * @TODO: post-install task to clear /var directory after deployments
             */
            $containerBuilder->enableCompilation(
                get_template_directory() . '/var/container/',
            );
            $containerBuilder->writeProxiesToFile(
                true,
                get_template_directory() . '/var/container/proxies',
            );
        }

        $this->container = $containerBuilder->build();
        $this->invoker = $this->container;
        $this->container->set(ThemeConfiguration::class, $this->config);
        $this->discoverActionHandlers();
        $this->autoload();
        add_action('after_setup_theme', [$this, 'onAfterSetupTheme']);
    }

    public function onAfterSetupTheme(): void
    {
        $this->addThemeSupports();
        $this->addTextDomain();
        $this->registerMenus();
    }

    protected function addTextDomain(): void
    {
        $textDomain = $this->config->get('themeTextDomain');

        if (is_string($textDomain) && !empty($textDomain)) {
            load_theme_textdomain(
                $textDomain,
                get_template_directory() . '/languages',
            );
        }
    }

    protected function addThemeSupports(): void
    {
        /** @var array<ThemeSupportsDefinition> $supports */
        $supports = $this->config->get('themeSupports');
        foreach ($supports as $themeSupportDefinition) {
            $themeSupport = $themeSupportDefinition['name'];
            $args = $themeSupportDefinition['args'] ?? [];
            add_theme_support($themeSupport, ...$args);
        }
    }

    /**
     * @throws \Invoker\Exception\NotCallableException
     * @throws \Invoker\Exception\InvocationException
     * @throws \Invoker\Exception\NotEnoughParametersException
     */
    protected function autoload(): void
    {
        /** @var array<class-string> $autoloadClasses */
        $autoloadClasses = $this->config->get('autoload');
        foreach ($autoloadClasses as $className) {
            $this->invoker->call([$className, '__construct']);
        }
    }

    /**
     * @throws InvalidUsageException
     * @throws Throwable
     */
    protected function discoverActionHandlers(): void
    {
        /** @var Action[] $actions */
        $actions = $this->config->get('actions');
        $actions = array_merge($actions, $this->actions);
        $actions = apply_filters(self::FILTER_ACTIONS, $actions);

        foreach ($actions as $action) {
            $ref = new \ReflectionClass($action);
            $interfaceNames = $ref->getInterfaceNames();

            if (in_array(ActionHookAction::class, $interfaceNames)) {
                $this->initHookAction($action, 'add_action');
            } elseif (in_array(FilterHookAction::class, $interfaceNames)) {
                $this->initHookAction($action, 'add_filter');
            } elseif (in_array(CustomAction::class, $interfaceNames)) {
                $this->initCustomAction($action);
            } else {
                throw new InvalidUsageException(
                    'Do not implement ActionHandler directly: Use one of ActionHookActionHandler or FilterHookActionHandler.',
                );
            }
        }
    }

    /**
     * @param class-string<HookAction> $actionClass
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initHookAction(
        string $actionClass,
        callable $registrationFunction
    ): void {
        $registrationFunction(
            $actionClass::getWpHookName(),
            function (...$args) use ($actionClass) {
                /** @var HookAction $action */
                $action = $this->container->get($actionClass);
                $handler = $action->getHandler();
                return $this->invoker->call($handler, $args);
            },
            $actionClass::getPriority(),
            $actionClass::getArgumentCount(),
        );
    }

    /**
     * @param class-string<CustomAction> $actionClassName
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initCustomAction(string $actionClassName): void
    {
        $action = $this->invoker->call([$actionClassName, '__construct']);
        $this->container->set($actionClassName, $action);
    }

    /**
     * @return array<string|class-string, string|class-string|FactoryDefinitionHelper|CreateDefinitionHelper>
     */
    protected function parseServiceDefinitions(): array
    {
        // parse yaml for services into appropriate array
        /** @var array<string, array<string, string>> $rawDefinitions */
        $rawDefinitions = $this->config->get('services');
        $definitions = [];

        foreach ($rawDefinitions as $name => $definition) {
            switch ($definition['type']) {
                case 'parameter':
                    $definitions[$name] = $definition['value'];
                    break;
                case 'factory':
                    $factory = $definition['value']::getFactory();
                    $definitions[$name] = factory($factory);
                    break;
                case 'class':
                default:
                    $definitions[$name] = $definition['value']
                        ? create($definition['value'])
                        : create($name);
            }
        }

        return $definitions;
    }

    protected function registerMenus(): void
    {
        /** @var array<string, string> $menuDefinitions */
        $menuDefinitions = $this->config->get('menus');
        foreach ($menuDefinitions as $menuLocation => $description) {
            register_nav_menu($menuLocation, $description);
        }
    }
}

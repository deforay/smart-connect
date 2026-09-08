<?php

declare(strict_types=1);

namespace App\Services;

use App\Timezone;
use Laminas\Db\Adapter\Adapter;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;

/**
 * Lazily boots the Laminas service container so the Slim handlers can reuse the
 * existing services (SampleService, CommonService, …) without a rewrite.
 *
 * Same boot as bin/console and bin/migrate: module configs are loaded and the
 * service manager is configured, but the MVC application is never bootstrapped
 * or dispatched — no routing, no view layer, no bootstrap listeners.
 *
 * Building it is deferred until a service is actually asked for, so unauthorised
 * requests and GET /api/v2/health never pay for it.
 */
final class LaminasBridge
{
    private ?ServiceManager $serviceManager = null;

    public function __construct(private readonly string $appRoot)
    {
    }

    public function serviceManager(): ServiceManager
    {
        if ($this->serviceManager !== null) {
            return $this->serviceManager;
        }

        $appConfig = require $this->appRoot . '/config/application.config.php';
        if (file_exists($this->appRoot . '/config/development.config.php')) {
            $appConfig = ArrayUtils::merge($appConfig, require $this->appRoot . '/config/development.config.php');
        }

        $serviceManager = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $appConfig);
        $serviceManager->get('ModuleManager')->loadModules();

        // Forces the config merge, which is also what defines APPLICATION_PATH,
        // UPLOAD_PATH and TEMP_UPLOAD_PATH (config/autoload/constants.global.php).
        $config = $serviceManager->get('config');

        // The v2 API forks in public/index.php before the MVC application
        // boots, so Module::onBootstrap never runs for these requests and PHP
        // keeps whatever php.ini left it on, which is UTC by default. Applied
        // here rather than in the Slim bootstrap because this is the first
        // point the merged config exists, and because a request that never
        // reaches Laminas -- /api/v2/health is most of them -- writes no
        // timestamp and should not pay for loading every module to set a clock.
        Timezone::applyFromConfig(is_array($config) ? $config : []);

        return $this->serviceManager = $serviceManager;
    }

    /** @param string $name A Laminas service manager key, e.g. 'SampleService'. */
    public function get(string $name): mixed
    {
        return $this->serviceManager()->get($name);
    }

    public function adapter(): Adapter
    {
        return $this->serviceManager()->get(Adapter::class);
    }

    /** @return array<string, mixed> The merged application config. */
    public function config(): array
    {
        return $this->serviceManager()->get('config');
    }

    /**
     * TEMP_UPLOAD_PATH and UPLOAD_PATH are defined as a side effect of the
     * config merge (config/autoload/constants.global.php), so they are reached
     * through the bridge rather than referenced directly — that way a handler
     * cannot read them before the container has been built.
     */
    public function tempUploadPath(string ...$segments): string
    {
        $this->serviceManager();

        return implode(DIRECTORY_SEPARATOR, [TEMP_UPLOAD_PATH, ...$segments]);
    }

    public function uploadPath(string ...$segments): string
    {
        $this->serviceManager();

        return implode(DIRECTORY_SEPARATOR, [UPLOAD_PATH, ...$segments]);
    }

    /**
     * Read a dotted key out of the merged config, e.g. 'api.enrollment_key'.
     */
    public function configValue(string $path, mixed $default = null): mixed
    {
        $node = $this->config();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node;
    }
}

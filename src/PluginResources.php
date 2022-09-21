<?php

namespace VmaInternal;

use Vma_Internal_Plugin;

class PluginResources {

    protected string $pluginPath;
    protected string $pluginDir;
    protected string $version;
    protected string $privatePath;
    protected array $config;
    private array $extensionFailures;

    public function __construct(Vma_Internal_Plugin $plugin) {
        $this->version = $plugin::VERSION;
        $pluginPath = $plugin->pluginPath();
        $this->pluginPath = $pluginPath;
        $this->pluginDir = dirname($pluginPath);
        $this->privatePath = wp_get_upload_dir()['basedir'] . '/private';
        $this->config = $this->readConfig();
        $this->extensionFailures = [];
    }

    public function pluginPath(): string
    {
        return $this->pluginPath;
    }

    public function pluginDir(): string
    {
        return $this->pluginDir;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function readConfig(): array
    {
        return json_decode(
            file_get_contents($this->privatePath . '/config.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function registerExtensionFailure($message): void
    {
        $this->extensionFailures[] = $message;
        $this->reportError($message);
    }

    public function reportError($exception): void
    {
        if (is_string($exception)) {
            $exception = new \Exception($exception);
        }
        if (! function_exists('wp_sentry_safe')) {
            return;
        }
        wp_sentry_safe(
            function(\Sentry\State\HubInterface $client) use ($exception) {
                $client->captureException($exception);
            }
        );
    }

    public function extensionFailures(): array
    {
        return $this->extensionFailures;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function optionEnvOverrides(): bool
    {
        return !get_option('vma_internal_env_overrides_disable', true);
    }

    public function optionEnvOverridesSet(bool $value): void
    {
        update_option('vma_internal_env_overrides_disable', $value, true);
    }
}
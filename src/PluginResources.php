<?php

namespace VmaInternal;

use Vma_Internal_Plugin;

class PluginResources {

    protected string $pluginPath;
    protected string $pluginDir;
    protected string $version;

    public function __construct(Vma_Internal_Plugin $plugin) {
        $this->version = $plugin::VERSION;
        $pluginPath = $plugin->pluginPath();
        $this->pluginPath = $pluginPath;
        $this->pluginDir = dirname($pluginPath);
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

}
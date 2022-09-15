<?php

declare( strict_types = 1 );

namespace VmaInternal;

use \WP_CLI;

use VmaInternal\PluginResources;

/**
 * VMA Interal site specific plugin commands
 * 
 * @when before_wp_load
 */
class WpCli {

    private PluginResources $pluginResources;

    public function __construct(PluginResources $pluginResources)
    {
        $this->pluginResources = $pluginResources;
    }

    /**
     * Enable or disable env overrides
     * 
     * When enabled overrides prevent the Authorize.net gateway from using
     * the Authorize.net API credentials stored in the database options table.
     * 
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to take <enable|disable> the overrides.
     *
     * ---
     * default: enable
     * ---
     *
     * ## EXAMPLES
     *
     *     wp vma overrides enable
     *
     */
    public function overrides(array $args)
    {
        $action = $args[0];

        $value = [
            'enable' => true,
            'disable' => false
        ][$action] ?? null;

        if ($value === null) {
            WP_CLI::error('Action must be one of <enable|disable>');
            WP_CLI::halt(2);
        }
        $this->setOverrides($value);
    }

    /**
     * Show the status of the plugin's functionality.
     */
    public function status(array $args)
    {
        if($this->extensionFailures()) {
            WP_CLI::line("Found missing plugins or themes:");
            foreach ($this->extensionFailures() as $failure) {
                WP_CLI::line( '* ' . $failure);
            }
        }
        else {
            WP_CLI::line("No missing plugins or themes found.");
        }
    }

    /**
     * Show Authorize.net Info
     */
    public function auth_net_info()
    {
        $dbValue = get_option('woocommerce_authorize_net_cim_credit_card_settings');
        $configValue = $this->authNetConfigSettings();
        if($this->usingOverrides()) {
            WP_CLI::line('* Using options from config file');
            $effectiveOptions = $configValue;
        }
        else {
            $effectiveOptions = $dbValue;
        }
        WP_CLI::line('Effective options:');
        WP_CLI::line(json_encode($effectiveOptions, JSON_PRETTY_PRINT));


        WP_CLI::line('settings from options table:');
        WP_CLI::line(json_encode($dbValue, JSON_PRETTY_PRINT));


        WP_CLI::line('settings from config file:');
        WP_CLI::line(json_encode($configValue, JSON_PRETTY_PRINT));
    }

    private function extensionFailures()
    {
        return $this->pluginResources->extensionFailures();
    }

    private function authNetConfigSettings()
    {
        return $this->pluginResources->config()['auth_net'] ?? [];
    }

    private function usingOverrides(): bool
    {
        return $this->pluginResources->optionEnvOverrides();
    }

    private function setOverrides(bool $value) {
        $this->pluginResources->optionEnvOverridesSet($value);
    }
}
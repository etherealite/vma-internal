<?php

namespace VmaInternal;

class AuthorizeDotNet {

    private PluginResources $pluginResources;

    public function register(PluginResources $pluginResources)
    {
        $this->pluginResources = $pluginResources;

        add_action('plugins_loaded', fn() => $this->boot());
    }

    public function boot(): void
    {
        if (!class_exists('WC_Authorize_Net_CIM_Loader')) {
            $this->pluginResources->registerExtensionFailure(
                'WooCommerce Authorize.Net Gateway plugin not found, see ' .
                '(https://woocommerce.com/products/authorize-net/)'
            );
            return;
        }
        if (wp_get_environment_type() !== 'production'){
            add_filter(
                'pre_option_woocommerce_authorize_net_cim_credit_card_settings',
                [$this, 'filter_pre_option_woocommerce_authorize_net_cim_credit_card_settings']
            );
        }
    }

    public function filter_pre_option_woocommerce_authorize_net_cim_credit_card_settings(
        $pre_option
    )
    {
        static $override = null;
        static $value = null;

        if ($override === null) {
            $override = $this->pluginResources->optionEnvOverrides();
            $value = $this->pluginResources->config()['auth_net'];
        }

        if ($override) {
            return $value;
        }
        return $pre_option;
    }
}
<?php

namespace VmaInternal;

use VmaInternal\PluginResources;

class KadenceTheme {

    private PluginResources $pluginResources;

    public function register(PluginResources $pluginResources)
    {
        $this->pluginResources = $pluginResources;

        add_action('init', fn() => $this->boot());
    }

    public function boot(): void
    {
        if (!defined('KADENCE_VERSION')) {
            $this->pluginResources->registerExtensionFailure(
                'Kadence theme not found, see ' .
                '(https://wordpress.org/themes/kadence/)'
            );
            return;
        }
        $this->template_hooks();
    }

    public function template_hooks(): void
    {
        add_action('vma_internal_404_content', function() {
            do_action('kadence_404_content');
        });

        add_action('vma_internal_content_error', function() {
            get_template_part( 'template-parts/content/error' );
        });
    }

}
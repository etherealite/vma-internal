<?php

use VmaInternal\WpEventManager;

/**
 * Plugin Name: VMA Internal Plugin
 * Description: General purpose plugin for site specific integrations.
 * Author: Evan Bangham
 * Version: 0.0.6
 * Author URI: https://github.com/etherealite
 *
 * Text Domain: VMA
 **/

use VmaInternal\PluginResources;

class Vma_Internal_Plugin {

    public const VERSION = '0.0.6';

    protected bool $booted = false;
    private string $pluginPath;
    private string $nonce;

    public function __construct($pluginPath)
    {
        $this->pluginPath = $pluginPath;
    }

    public static function bootstrap($pluginPath): self
    {
        require __DIR__ . '/src/PluginResources.php';
        require __DIR__ . '/src/WpEventManager.php';

        $instance = new static($pluginPath);
        $instance->register();

        return $instance;
    }

    public function register(): void
    {
        $pluginResources = new PluginResources($this);

        $eventManager = new WpEventManager();
        $eventManager->register($pluginResources);

        add_action('init', [$this, 'action_init']);
    }

    public function pluginPath(): string
    {
        return $this->pluginPath;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
    }

    public function action_init(): void
    {

        if (defined('KADENCE_VERSION')) {
            require __DIR__ . '/src/KadenceTheme.php';

            $kadence = new \VmaInternal\KadenceTheme();
            $kadence->register();
        };

        add_filter('wp_headers', [$this, 'action_wp_headers']);
    }


    public function action_wp_headers(): array
    {
        $headers['X-Frame-Options'] = 'DENY';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $headers['Strict-Transport-Security'] = 'max-age=63072000; includeSubDomains; preload';
        $headers['Access-Control-Allow-Origin'] = WP_SITEURL;
        $headers['Content-Security-Policy'] = preg_replace('/\n\s*/', " ", "
            default-src 'self' 'unsafe-inline' *.googleapis.com *.gstatic.com;
            object-src 'none';
            base-uri 'self';
            connect-src *;
            frame-src 'self';
            img-src * data:;
            manifest-src 'self';
            media-src 'self';
            worker-src 'none';
        ");
        $headers['Cross-Origin-Opener-Policy'] = 'same-origin';
        $headers['Cross-Origin-Resource-Policy'] = 'same-site';
        $headers['Cross-Origin-Embedder-Policy'] = 'require-corp';
        $headers['X-Powered-By'] = 'Ponies';

        return $headers;
    }

    public function asset_url($file): string
    {
        return plugin_dir_url($this->pluginPath) . 'assets/' . $file;
    }
}


function VmaInt(): Vma_Internal_Plugin {
    static $instance;
    if ($instance) {
        return $instance;
    }

    $instance = Vma_Internal_Plugin::bootstrap(__FILE__);
    $instance->boot();

    return $instance;
}

(function() {
    VmaInt();
    register_deactivation_hook(__FILE__, function() {
        delete_option('rewrite_rules');
    });

    register_activation_hook(__FILE__, function() {
        unregister_post_type('event_listing');
        WP_Event_Manager_Post_Types::instance()->register_post_types();
        flush_rewrite_rules();
    });
})();




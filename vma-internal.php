<?php
/**
 * Plugin Name: VMA Internal Plugin
 * Description: General purpose plugin for site specific integrations.
 * Author: Evan Bangham
 * Version: 0.0.8
 * Author URI: https://github.com/etherealite
 *
 * Text Domain: VMA
 **/
declare( strict_types = 1 );

use VmaInternal\WpEventManager;
use VmaInternal\PluginResources;
use VmaInternal\WpCli;

class Vma_Internal_Plugin {

    public const VERSION = '0.0.8';

    protected bool $booted = false;
    private string $pluginPath;
    private PluginResources $pluginResources;

    public function __construct($pluginPath)
    {
        $this->pluginPath = $pluginPath;
    }

    public static function bootstrap($pluginPath): self
    {
        require __DIR__ . '/src/PluginResources.php';
        require __DIR__ . '/src/KadenceTheme.php';
        require __DIR__ . '/src/WpEventManager.php';
        require __DIR__ . '/src/AuthorizeDotNet.php';
        require __DIR__ . '/src/WpCli.php';

        $instance = new static($pluginPath);
        $instance->register();

        return $instance;
    }

    public function register(): void
    {
        $pluginResources = new PluginResources($this);
        $this->pluginResources = $pluginResources;

        $eventManager = new WpEventManager();
        $eventManager->register($pluginResources);

        $kadence = new \VmaInternal\KadenceTheme();
        $kadence->register($pluginResources);

        $authorizeDotNet = new \VmaInternal\AuthorizeDotNet();
        $authorizeDotNet->register($pluginResources);


        add_action('plugins_loaded', [$this, 'action_plugins_loaded']);
        add_action('init', [$this, 'action_init']);
        add_action('admin_init', [$this, 'action_admin_init']);
        add_action('cli_init', function() use ($pluginResources) {
            $commands = new WpCli($pluginResources);
            WP_CLI::add_command('vma', $commands);
        });
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
    }

    public function action_plugins_loaded(): void
    {

    }

    public function action_init(): void
    {
        add_filter( 'x_redirect_by', '__return_false' );
        add_filter('wp_headers', [$this, 'action_wp_headers'], 0, 1);
        add_filter('wp_revisions_to_keep', fn() => 10);
    }
    
    public function action_admin_init(): void
    {
        add_action('admin_notices', [$this, 'action_admin_notices']);

    }

    public function action_admin_notices(): void
    {
        foreach($this->pluginResources->extensionFailures() as $failure) {
            ?>
                <div class="notice notice-error">
                    <p><strong>VMA Internal Plugin:</strong> <?php echo $failure ?></p>
                </div>';
            <?php
        }
    }

    public function action_wp_headers($headers): array
    {
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $headers['Access-Control-Allow-Origin'] = WP_SITEURL;
        $headers['Content-Security-Policy'] = preg_replace('/\n\s*/', " ", "
            default-src * 'unsafe-inline' 'unsafe-eval' data:  ;
            object-src 'none';
            base-uri 'self';
            frame-src 'self';
            manifest-src 'self';
            worker-src 'none';
        ");
        $headers['Permissions-Policy'] = preg_replace('/\n\s*/', " ", "
            accelerometer=(),
            autoplay=(),
            camera=(),
            cross-origin-isolated=(),
            document-domain=(),
            encrypted-media=(),
            fullscreen=*,
            geolocation=(self),
            gyroscope=(),
            keyboard-map=(),
            magnetometer=(),
            microphone=(),
            midi=(),
            payment=*,
            picture-in-picture=(),
            publickey-credentials-get=(),
            screen-wake-lock=(),
            sync-xhr=(),
            usb=(),
            xr-spatial-tracking=(),
            gamepad=(),
            serial=(),
            window-placement=()
        ");
        $headers['Cross-Origin-Opener-Policy'] = 'same-origin';
        $headers['Cross-Origin-Resource-Policy'] = 'same-origin';
        $headers['Cross-Origin-Embedder-Policy'] = 'require-corp';
        $headers['X-Powered-By'] = 'Ponies';

        return $headers;
    }

    public function pluginPath(): string
    {
        return $this->pluginPath;
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




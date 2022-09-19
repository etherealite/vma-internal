<?php

namespace VmaInternal;

use VmaInternal\PluginResources;

use WP_Event_Manager_Post_Types;

class WpEventManager {

    private string $pluginPath;
    private string $templatePath;
    private string $pluginVersion;
    private array $addons;
    private PluginResources $pluginResources;


    public function register(PluginResources $pluginResources): void
    {
        $this->pluginPath = $pluginResources->pluginPath();
        $this->templatePath = $pluginResources->pluginDir()  . '/src/event-templates';
        $this->pluginVersion = $pluginResources->version();

        $this->pluginResources = $pluginResources;

        add_action('plugins_loaded', fn() => $this->boot());  
    }

    public function boot(): void {

        if (! class_exists('WP_Event_Manager')) {
            $pluginResources = $this->pluginResources;
            $pluginResources->registerExtensionFailure(
                'WP Event Manager not found, see '. 
                '(https://wordpress.org/plugins/wp-event-manager/)'
            );
            return;
        }
        $this->bindAddons();

        $this->addons = [
            $this->attendee_info,
            $this->calendar,
            $this->colors,
            $this->emails,
            $this->tags,
            $this->export,
            $this->analytics,
            $this->maps,
            $this->recaptcha,
            $this->mailChimp,
            $this->registrations,
            $this->sellTickets,
            $this->zoom,
            $this->nameBadges,
        ];


        $envType = wp_get_environment_type();
        if ($envType !== 'production') {
            //$this->disableUpdates();
            $this->silenceUpdateNags();
        }

        $this->overrideTemplates();
        $this->patchZoomHooks();
    }

    public function bindAddons(): void
    {
        /** this is in load order */
        $this->attendee_info = $GLOBALS['event_manager_attendee_information'] ?? null;
        $this->calendar = $GLOBALS['event_manager_calendar'] ?? null;
        $this->colors = $GLOBALS['event_manager_colors'] ?? null;
        $this->emails = $GLOBALS['event_manager_emails'] ?? null;
        $this->tags = $GLOBALS['event_manager_tags'] ?? null;
        $this->export = $GLOBALS['event_manager_export'] ?? null;
        $this->analytics = $GLOBALS['event_manager_google_analytics'] ?? null;
        $this->maps = $GLOBALS['WP_Event_Manager_Google_Maps'] ?? null;
        $this->recaptcha = $GLOBALS['event_manager_google_recaptcha'] ?? null;
        $this->mailChimp = $GLOBALS['event_manager_mailchimp'] ?? null;
        $this->registrations = $GLOBALS['event_manager_registrations'] ?? null;
        $this->sellTickets = $GLOBALS['event_manager_sell_tickets'] ?? null;
        $this->zoom = $GLOBALS['event_manager_zoom'] ?? null;
        $this->nameBadges = $GLOBALS['wpem_name_badges'] ?? null;
    }

    /**
     * Stop Zoom addon from flushing rewrite rules on every init
     * 
     * Prevents the WP Event Manager - Zoom addon from flushing rewrite rules
     * on every request to wordpress.
     * 
     * Tested on Zoom addon version 1.0.8
     */
    public function patchZoomHooks(): void
    {
        global $wp_filter;

        if (!$this->zoom) {
            /** Addon not active */
            return;
        }
        elseif (!class_exists('WPEM_Zoom_WooCommerce')) {
            $this->pluginResources->reportError(
                "Zoom addon active but WPEM_Zoom_WooCommerce class not available"
            );
            return;
        }

        $hook = $wp_filter['woocommerce_account_zoom-meeting_endpoint'] ?? null;
        $callbacks = $hook->callbacks[10] ?? [];

        $wooInstance = null;
        foreach ($callbacks as $callback) {
            if ($callback['function'][0] instanceof \WPEM_Zoom_WooCommerce) {
                $wooInstance = $callback['function'][0];
            }
            break;
        }


        $removed = remove_action('init', [
            $wooInstance, 'add_zoom_meeting_endpoint'
        ]);

        if ($removed) {
            add_action('init',  function() {
                add_rewrite_endpoint('zoom-meeting', EP_ROOT | EP_PAGES);
            });
        }
        else {
            $this->pluginResources->reportError(
                "Unable able to remove WPEM_Zoom_WooCommerce init hook"
            );
        }
    }

    public function overrideTemplates(): void
    {
        remove_filter(
            'archive_template',
            [WP_Event_Manager_Post_Types::instance(), 'event_archive'],
        20);

        add_filter(
            'archive_template',
            [$this, 'filter_archive_template'],
        20, 3);

        add_filter(
            'single_template',
            [$this, 'filter_single_template'],
        20, 3);

        add_action(
            'wp_head', 
            [$this, 'action_wp_head'],
        PHP_INT_MAX);

        add_filter(
            'register_taxonomy_event_listing_category_args', 
            [$this, 'filter_register_taxonomy_event_listing_category_args'],
        10, 1);

        add_filter(
            'register_taxonomy_event_listing_type_args', 
            [$this, 'filter_register_taxonomy_event_listing_type_args'],
        10, 1);

        add_filter(
            'register_post_type_event_listing',
            [$this, 'filter_register_post_type_event_listing'],
        10, 1);

        add_filter(
            'event_manager_locate_template',
            [$this, 'filter_event_manager_locate_template'],
        10, 3);
    }

    public function silenceUpdateNags(): void
    {
        add_action('admin_init', function() {
            foreach ($this->addons as $addon) {
                remove_action('admin_notices', [$addon, 'key_notice']);
            }
        }, 20);
    }

    public function disableUpdates(): void
    {
        add_action('admin_init', function() {

            remove_action('admin_init', [$this->attendee_info, 'admin_init']);
            remove_action('admin_init', [$this->calendar, 'admin_init']);
            remove_action('admin_init', [$this->colors, 'admin_init']);
            remove_action('admin_init', [$this->emails, 'admin_init']);
            remove_action('admin_init', [$this->tags, 'admin_init']);
            remove_action('admin_init', [$this->export, 'admin_init']);
            remove_action('admin_init', [$this->analytics, 'admin_init']);
            remove_action('admin_init', [$this->maps, 'admin_init']);
            remove_action('admin_init', [$this->recaptcha, 'admin_init']);
            remove_action('admin_init', [$this->mailChimp, 'admin_init']);
            remove_action('admin_init', [$this->registrations, 'admin_init']);
            remove_action('admin_init', [$this->sellTickets, 'admin_init']);
            remove_action('admin_init', [$this->zoom, 'admin_init']);
            remove_action('admin_init', [$this->nameBadges, 'admin_init']);
        }, -1);
    }

    public function filter_single_template(
        string $template, string $type, array $templates
    ): string 
    {   
        if (is_singular('event_listing')) {
            $template = $this->templatePath . '/single-event_listing.php';
        }
        
        return $template;
    }

    public function filter_archive_template(
        string $template, string $type, array $templates
    ): string
    {
		if (is_tax('event_listing_category')) {

			$template = $this->templatePath . '/content-event_listing_category.php';
	    }
	    elseif (is_tax('event_listing_type')) {

			$template = $this->templatePath . '/content-event_listing_type.php';
	    }
        elseif($templates[0] === 'archive-event_listing.php') {
            $template = $this->templatePath . '/archive-event_listing.php';
        }

	    return $template;
    }

    public function action_wp_head(): void
    {
        wp_enqueue_style(
            'vma-internal-frontend',
            plugin_dir_url($this->pluginPath) . 'src/styles.css',
            [],
            $this->pluginVersion
        );
    }

    public function filter_register_taxonomy_event_listing_category_args(
        array $args
    ): array
    {
        $rewrite = [
            'slug' => 'events/category',
            'with_front'   => false,
            'hierarchical' => false,
        ];
        $args['rewrite'] = $rewrite;

        return $args;
    }

    public function filter_register_taxonomy_event_listing_type_args(
        array $args
    ): array 
    {
        $rewrite = [
            'slug' => 'events/type',
            'with_front'   => false,
            'hierarchical' => false
        ];
        $args['rewrite'] = $rewrite;
        return $args;
    }

    public function filter_register_post_type_event_listing(
        array $args
    ): array {
        $args['rewrite']['slug'] = 'events';
        $args['has_archive'] = true;
        return $args;
    }

    public function filter_event_manager_locate_template(
        $template, $template_name, $template_path
    ) {

        // Look for tempaltes in theme on the passed path

        $themeTemplate = locate_template([
                trailingslashit($template_path) . $template_name,
                $template_name
        ]);

        if($themeTemplate) {
            return $themeTemplate;
        }

        // Get the template from the VMA plugin
        $internal_path = $this->templatePath;

        $internalTemplate = trailingslashit($internal_path.'/') . $template_name;

        if (file_exists($internalTemplate)) {
            return $internalTemplate;
        }

        // return what event manager found.
        return $template;
    }
}
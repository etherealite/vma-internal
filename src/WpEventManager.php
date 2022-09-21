<?php

namespace VmaInternal;

use VmaInternal\PluginResources;
use VmaInternal\VmaInternalException;

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


        add_shortcode('vma_events_redirect', [$this, 'shortcode']);

        add_action('plugins_loaded', [$this, 'boot']);  
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
        $this->upcomingArchive();
        // $this->applyQueryLogic();


        try {
            $this->patchZoomHooks();
        }
        catch (VmaInternalException $e) {
            $this->pluginResources->reportError($e);
        }

        $this->disableGutenberg();

        $this->preventIndexingSEO();
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
            throw new VmaInternalException(
                'Zoom addon active but WPEM_Zoom_WooCommerce class not available'
            );
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
            throw new VmaInternalException(
                'Unable able to remove WPEM_Zoom_WooCommerce init hook'
            );
        }
    }

    public function disableGutenberg(): void
    {
        add_filter('use_block_editor_for_post_type', function($use_block_editor, $post_type) {
            return $post_type === 'event_listing' ? false: $use_block_editor;
        }, 10, 2);
    }

    public function upcomingArchive(): void
    {
        add_filter('query_vars', function($vars) {
            $vars[] = 'vma_event_upcoming';
            return $vars;
        });

        add_action('init', function() {
            add_rewrite_rule('events/upcoming/?$', (
                'index.php?'.
                'post_type=event_listing&' .
                'order=asc&' .
                'orderby=event_start_date'
            ),'top');
        });

        add_action('parse_query', function(\WP_QUERY $query) {
            if (! (
                // $query->is_main_query()
                $query->is_post_type_archive('event_listing') || 
                $query->is_tax('event_listing_category') ||
                $query->is_tax('event_listing_type')  
            )) {
                return;
            };
    
            $meta_query = array(
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_event_start_date',
                        'value'   => current_time('Y-m-d H:i:s'),
                        'type'    => 'DATETIME',
                        'compare' => '>='
                    ),
                    array(
                        'key'     => '_event_end_date',
                        'value'   => current_time('Y-m-d H:i:s'),
                        'type'    => 'DATETIME',
                        'compare' => '>='
                    )
                ),
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_event_start_date',
                        'type'    => 'DATETIME',
                        'compare' => 'EXISTS'                       
                    ],
                    [
                        'key'     => '_event_end_date',
                        'type'    => 'DATETIME',
                        'compare' => 'EXISTS'                       
                    ]
                ]
            );
    
            $hide_canceled = false;
            if ($hide_canceled) {
                $meta_query[] = array(
                    'key'     => '_cancelled',
                    'value'   => '1',
                    'compare' => '!='
                );
            }

    
            if ($query->get('orderby') === 'event_start_date') {
                $query->set('orderby', 'meta_value');
                $query->set('meta_type', 'DATETIME');
                $query->set('meta_key', '_event_start_date');
            }
            $query->set('meta_query', $meta_query);
            $query->set('posts_per_page', get_option('event_manager_per_page'));
            $query->set('post_status', ['publish', 'expired']);
        });
    }

    public function preventIndexingSEO(): void
    {
        add_filter('wp_sitemaps_taxonomies', function($taxonomies) {
            return $taxonomies;
        });
    }
    // public function applyQueryLogic(): void
    // {
    //     add_action('pre_get_posts', [$this, 'action_pre_get_posts']);
    // }

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

    public function shortcode($atts): void
    {
        if ($atts['page'] ?? null) {
            $redirect = get_post_type_archive_link('event_listing');
        }
        else {
            $redirect = null;
        }
        if (!$redirect) {
            return;
        }
        ob_start();
        ?>
        <meta http-equiv="refresh" content="0; url=<?php echo $redirect; ?>">
            Please wait while you are redirected...or <a href="<?php echo $redirect; ?>">Click Here</a> if you do not want to wait.
        <?php
        ob_get_clean();
    }

    /**
     * @todo unused cruft
     */
    public function action_pre_get_posts($query): void
    {
        if (! (
            $query->is_main_query()
            && $query->is_post_type_archive('event_listing')
            || $query->is_tax('event_listing_category')
            || $query->is_tax('event_listing_type')
        )) {
            return;
        };



        $meta_query = array(
			array(
				'relation' => 'OR',
				array(
					'key'     => '_event_start_date',
					'value'   => current_time('Y-m-d H:i:s'),
					'type'    => 'DATETIME',
					'compare' => '>='
				),
				array(
					'key'     => '_event_end_date',
					'value'   => current_time('Y-m-d H:i:s'),
					'type'    => 'DATETIME',
					'compare' => '>='
				)

			),

	    );

        $hide_canceled = false;
        if ($hide_canceled) {
            $meta_query[] = array(
				'key'     => '_cancelled',
				'value'   => '1',
				'compare' => '!='
			);
        }

        $query->set('meta_query', $meta_query);
        $query->set('posts_per_page', get_option('event_manager_per_page'));
        $query->set('order', 'DESC');
        $query->set('orderby', 'meta_value');
        $query->set('meta_key', '_event_start_date');
        $query->set('meta_type', 'DATETIME');
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
        $args['rewrite']['pages'] = true;
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
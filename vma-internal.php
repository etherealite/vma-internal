<?php
/**
 * Plugin Name: VMA Internal Plugin
 * Description: General purpose plugin for site specific integrations.
 * Author: Evan Bangham
 * Version: 0.0.2
 * Author URI: https://github.com/etherealite
 *
 * Text Domain: VMA
 **/


class Vma_Internal {

    public const VERSION = '0.0.2';

    private string $path;

    public function __construct($pluginPath) {
        $this->path = $pluginPath;
    }

    public static function bootstrap(): void
    {
        $instance = new static(__DIR__);
        $instance->register();
    }

    public function register(): void
    {
        add_filter(
            'register_taxonomy_event_listing_category_args', 
            [$this, 'filter_register_taxonomy_event_listing_category_args'],
        10, 1);

        add_filter(
            'register_taxonomy_event_listing_type_args', 
            [$this, 'filter_register_taxonomy_event_listing_type_args'],
        10, 1);

        add_filter(
            'event_manager_locate_template',
            [$this, 'filter_event_manager_locate_template'],
        10, 3);

        add_action(
            'plugins_loaded',
            [$this, 'action_plugins_loaded'],
        10, 0);
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
        $internal_path = $this->path . '/src/event-templates/';

        $internalTemplate = trailingslashit($internal_path) . $template_name;

        if (file_exists($internalTemplate)) {
            return $internalTemplate;
        }

        // return what event manager found.
        return $template;
    }

    public function action_plugins_loaded(): void
    {
        remove_filter(
            'archive_template',
            [WP_Event_Manager_Post_Types::instance(), 'event_archive'],
        20);

        add_filter(
            'archive_template',
            [$this, 'filter_archive_template']
        );

        add_action(
            'wp_enqueue_scripts', 
            [$this, 'action_wp_enqueue_scripts'],
        50);
    }

    public function filter_archive_template(): string
    {
		if (is_tax( 'event_listing_category')) {

			$template = $this->path . '/src/event-templates/content-event_listing_category.php';
	    }
	    elseif (is_tax( 'event_listing_type')) {

			$template = $this->path . '/src/event-templates/content-event_listing_type.php';
	    }

	    return $template;
    }

    public function action_wp_enqueue_scripts(): void
    {
        wp_enqueue_style(
            'vma-internal-frontend',
            plugin_dir_url(__FILE__) . 'src/styles.css',
            [],
            static::VERSION
        );
    }
}


(function() {
    Vma_Internal::bootstrap();

    register_deactivation_hook(__FILE__, function() {
        delete_option('rewrite_rules');
    });

    register_activation_hook(__FILE__, function() {
        unregister_post_type('event_listing');
        WP_Event_Manager_Post_Types::instance()->register_post_types();
        flush_rewrite_rules();
    });
})();




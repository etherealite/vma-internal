<?php

namespace VmaInternal;

class WpEventManager {

    private array $addons;

    public function register(): void
    {
        add_action('plugins_loaded', function() {
            if ($GLOBALS['event_manager'] ?? false) {
                $this->boot();
            }
        });   
    }

    public function boot(): void {
        /** this is in load order */
        $this->attendee_info ??= $GLOBALS['event_manager_attendee_information'];
        $this->calendar ??= $GLOBALS['event_manager_calendar'];
        $this->colors ??= $GLOBALS['event_manager_colors'];
        $this->emails ??= $GLOBALS['event_manager_emails'];
        $this->tags ??= $GLOBALS['event_manager_tags'];
        $this->export ??= $GLOBALS['event_manager_export'];
        $this->analytics ??= $GLOBALS['event_manager_google_analytics'];
        $this->maps ??= $GLOBALS['WP_Event_Manager_Google_Maps'];
        $this->recaptcha ??= $GLOBALS['event_manager_google_recaptcha'];
        $this->mailChimp ??= $GLOBALS['event_manager_mailchimp'];
        $this->registrations ??= $GLOBALS['event_manager_registrations'];
        $this->sellTickets ??= $GLOBALS['event_manager_sell_tickets'];
        $this->zoom ??= $GLOBALS['event_manager_zoom'];
        $this->nameBadges ??= $GLOBALS['wpem_name_badges'];

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
            // $this->disableUpdates();
            $this->silenceUpdateNags();
        }
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
}
<?php

namespace VmaInternal;

class KadenceTheme {
    public function register()
    {
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
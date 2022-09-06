<?php

namespace VmaInternal;

class KadenceTheme {
    public function register()
    {
        add_action('vma_internal_404_content', function() {
            do_action('kadence_404_content');
        });
    }

}
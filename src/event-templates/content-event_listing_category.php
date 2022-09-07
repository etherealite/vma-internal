<?php
/**
 * The template for displaying archive.
 */

get_header();

global $wp_query;
?>

<style>
    .vma-event--after_style_load {
        visibility: none;
    }
</style>
<div class="vma-event-hero vma-event--after_style_load">
    <?php echo do_shortcode('[elementor-template id="30803"]'); ?>
</div>
<div class="wpem-container vma-event--after_style_load">
<div class="wpem-main wpem-event-listing-type-page">
<div class="wpem-row">

<div class="wpem-col wpem-col-12 wpem-col-md-4 wpem-col-lg-3 wpem-col-xl-3">
        <div class="vma-event-sidebar">
            <?php echo do_shortcode('[elementor-template id="30800"]'); ?>
        </div>
</div>
<div class="wpem-col-12 wpem-col-md-8 wpem-col-lg-9 wpem-col-xl-9 wpem-event-listing-type-page-wrapper">

<div class="wpem-event-listing-type-page-title">
    <h2 class="vma-event-archive-title__text"><?php echo get_the_archive_title(); ?></h2>
    <h4 class="vma-event-archive-title__description"><?php echo wp_kses_post(term_description()); ?></h4>
</div>

        <?php
        // remove calender view
        remove_action('end_event_listing_layout_icon', 'add_event_listing_calendar_layout_icon');
        ?>
            <div class="event_listings">
                <?php if ( have_posts() ) : ?>

                    <?php get_event_manager_template( 'event-listings-start.php' ,array('layout_type'=>'all')); ?>           

                    <?php while ( have_posts() ) : the_post(); ?>

                        <?php  get_event_manager_template_part( 'content', 'event_listing' ); ?>
                        
                    <?php endwhile; ?>

                    <?php get_event_manager_template( 'event-listings-end.php' ); ?>

                    <?php get_event_manager_template( 'pagination.php', array( 'max_num_pages' => $wp_query->max_num_pages ) ); ?>

                <?php else :

                    do_action( 'event_manager_output_events_no_results' );

                endif;

                wp_reset_postdata(); ?>                
            </div>
</div>
</div>
</div>
</div>


<?php get_footer(); ?>
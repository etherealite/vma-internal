<?php
/**
 * The template for displaying archive.
 */

get_header();

global $wp_query;
?>

<style>
    .vma-event--after_style_load {
        visibility: hidden;
    }
</style>
<div class="vma-event-hero vma-event--after_style_load">
    <?php echo do_shortcode('[elementor-template id="30803"]'); ?>
</div>
<div class="vma-event-full-width-wrap vma-event--after_style_load" style="background-image: url(<?php echo VmaInt()->asset_url('orange-circle-pattern.png'); ?>)">
<div class="wpem-container">
<div class="wpem-main wpem-event-listing-type-page">
<div class="wpem-row">

<div class="wpem-col wpem-col-12 wpem-col-md-4 wpem-col-lg-3 wpem-col-xl-3">
        <div class="vma-event-sidebar">
            <?php echo do_shortcode('[elementor-template id="30800"]'); ?>
        </div>
</div>
<div class="wpem-col-12 wpem-col-md-8 wpem-col-lg-9 wpem-col-xl-9 wpem-event-listing-type-page-wrapper">

<div class="wpem-event-listing-type-page-title">
    <h2 class="vma-event-archive-title__text"><?php echo get_the_title(); ?></h2>
</div>

<div>
<?php
    if ( is_404() ) {
        do_action('vma_internal_404_content');
    } elseif ( have_posts() ) {
        while ( have_posts() ) {
            the_post();
            /**
             * Hook in content single entry template.
             */
            // do_action( 'kadence_single_content' );
            the_content();
        }
    } else {
        do_action('vma_internal_content_error');
    }
?>
</div>


</div>
</div>
</div>
</div>
</div>


<?php get_footer(); ?>
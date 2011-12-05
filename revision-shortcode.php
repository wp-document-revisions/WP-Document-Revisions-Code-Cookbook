<?php
/*
Plugin Name: WP Document Revisions - Revision Shortcode
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate short code to list revisions
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

/**
 *
 * NOTE: An updated version of this shortcode is now included with WP Document Revisions by default
 * As a result, this file is no longer maintained
 *
 */

/**
 * Callback to display revisions
 * @param $atts array attributes passed via short code
 * @returns string a UL with the revisions
 */
function wpdr_shotcode( $atts ) {

	//extract args
	extract( shortcode_atts( array(
		'id' => null,
	), $atts ) );
	
	//get WPDR object	
	$wpdr = Document_Revisions::$instance;
	$revisions = $wpdr->get_revisions( $id );
	
	//buffer output to return rather than echo directly
	ob_start();
	?>
	<ul class="revisions">
	<?php 
	//loop through each revision
	foreach ( $revisions as $revision ) { ?>
		<li>
			<a href="<?php echo get_permalink( $revision->ID ); ?>" title="<?php echo $revision->post_date; ?>" class="timestamp" id="<?php echo strtotime( $revision->post_date ); ?>">
			<?php echo human_time_diff( strtotime( $revision->post_date ), current_time('timestamp') ); ?>
			</a> by <?php echo get_the_author_meta( 'display_name', $revision->post_author ); ?>
		</li>
	<?php } ?>
	</ul>
	<?php
	//grab buffer contents and clear
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

add_shortcode( 'revisions', 'wpdr_shortcode' );
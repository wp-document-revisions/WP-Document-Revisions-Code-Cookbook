<?php
/*
Plugin Name: WP Document Revisions - Recently Revised Widget
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate a widget of recently revised files
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

class wpdr_recently_revised_widget extends WP_Widget class wpdr_recently_revised_documents extends WP_Widget {

	function __construct() {
		parent::WP_Widget( 'wpdr_recently_revised_documents', $name = 'Recently Revised Documents' );
		add_action( 'widgets_init', create_function( '', 'return register_widget("wpdr_recently_revised_documents");' ) );
	}
	
	function widget( $args, $instance ) {
		
		extract( $args );
 		
 		echo $before_widget; 
 		
		echo $before_title . 'Recently Revised Documents' . $after_title;	
		
		$query = array( 
				'post_type' => 'document',
				'orderby' => 'post_date',
				'order' => 'DESC',
				'numberposts' => '5',
				'post_status' => array( 'private', 'publish', 'draft' ),
		);
		
		$documents = get_posts( $query );

		echo "<ul>\n";
		foreach ( $documents as $document ) { ?>
			<li><a href="<?php echo get_permalink( $document->ID ); ?>"><?php echo $document->post_title; ?></a><br />
			Revised <?php echo human_time_diff( strtotime( $document->post_modified ) ); ?> ago by <?php echo  get_the_author_meta( 'display_name', $document->post_author ); ?>
			</li>
		<?php }
		
		echo "</ul>\n";
		
		echo $after_widget;
		
	}

}

new wpdr_recently_revised_documents;


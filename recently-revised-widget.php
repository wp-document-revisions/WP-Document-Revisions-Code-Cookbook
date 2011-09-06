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

class wpdr_recently_revised_widget extends WP_Widget {

	function __construct() {
		parent::WP_Widget( false, $name = 'Recently Revised Documents' );
	}
	
	function widget( $args, $instance ) {
		
		extract( $args );
 		
 		echo $before_widget; 
 		
		echo $before_title . 'Recently Revised Documents' . $after_title;	
		
		$query = array( 
				'post_type' => 'document',
				'orderby' => 'modified',
				'order' => 'DESC',
				'posts_per_page' = '5',
		);
		
		$documents = get_post( $query );
		
		echo "<ul>\n";
		
		foreach ( $documents as $document ) { ?>
			<li><a href="<?php get_permalink( $document->ID ); ?>"><?php echo $document->title; ?></a></li>
		<?php }
		
		echo "</ul>\n";
		
		echo $after_widget;
		
	}

}
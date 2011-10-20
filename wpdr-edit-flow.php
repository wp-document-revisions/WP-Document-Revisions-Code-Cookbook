<?php
/*
Plugin Name: WP Document Revisions - Edit Flow Compatability
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook/
Description: Makes WP Document Revisions work with the Edit Flow plugin
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/


function wpdr_ef_cpt_filter( $cpt ) {

	$cpt['supports']  = 	array_merge( 
	
					$cpt['supports'], 
							
					array( 
						'ef_custom_statuses', 
						'ef_editorial_comments', 
						'ef_notifications', 
						'ef_editorial_metadata' 
					) 
							
				);
	
	return $cpt;
	
}

add_filter( 'document_revisions_cpt', 'wpdr_ef_cpt_filter', 10, 1 );
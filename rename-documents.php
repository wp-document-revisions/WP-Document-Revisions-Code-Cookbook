<?php
/*
Plugin Name: WP Document Revisions - Rename Documents Label
Plugin URI: 
Description: Example code to change the "Documents" labels used throughout the plugin to something else such as "articles" or "reports"
Version: 0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/
	
/** 
 * Changes all references to "Documents" in the interface to "Articles"
 * @params array $args the default args for the custom post type
 * @returns array $args the CPT args with our modified labels
 */
function bb_filter_document_cpt( $args ) {
	
	$args['labels'] = array(
		'name' => _x( 'Articles', 'post type general name', 'wp_document_revisions' ),
		'singular_name' => _x( 'Article', 'post type singular name', 'wp_document_revisions' ),
		'add_new' => _x( 'Add Article', 'article', 'wp_document_revisions' ),
		'add_new_item' => __( 'Add New Article', 'wp_document_revisions' ),
		'edit_item' => __( 'Edit Article', 'wp_document_revisions' ),
		'new_item' => __( 'New Article', 'wp_document_revisions' ),
		'view_item' => __( 'View Article', 'wp_document_revisions' ),
		'search_items' => __( 'Search Articles', 'wp_document_revisions' ),
		'not_found' =>__( 'No articles found', 'wp_document_revisions' ),
		'not_found_in_trash' => __( 'No articles found in Trash', 'wp_document_revisions' ), 
		'parent_item_colon' => '',
		'menu_name' => __( 'Articles', 'wp_document_revisions' ),
		);
	
	return $args;
	
}

add_filter( 'document_revisions_cpt', 'bb_filter_document_cpt' );
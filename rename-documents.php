<?php
/**
Plugin Name: WP Document Revisions - Rename Documents Label
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Example code to change the "Documents" labels used throughout the plugin to something else such as "articles" or "reports"
Version: 0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Changes all references to "Documents" in the interface to "Articles"
 *
 * @param array $args the default args for the custom post type.
 * @return array $args the CPT args with our modified labels
 */
function bb_filter_document_cpt( $args ) {

	$args['labels'] = array(
		'name'                  => _x( 'Articles', 'post type general name', 'wp-document-revisions' ),
		'singular_name'         => _x( 'Article', 'post type singular name', 'wp-document-revisions' ),
		'add_new'               => _x( 'Add Article', 'article', 'wp-document-revisions' ),
		'add_new_item'          => __( 'Add New Article', 'wp-document-revisions' ),
		'edit_item'             => __( 'Edit Article', 'wp-document-revisions' ),
		'new_item'              => __( 'New Article', 'wp-document-revisions' ),
		'view_item'             => __( 'View Article', 'wp-document-revisions' ),
		'view_items'            => __( 'View Articles', 'wp-document-revisions' ),
		'search_items'          => __( 'Search Articles', 'wp-document-revisions' ),
		'not_found'             => __( 'No articles found', 'wp-document-revisions' ),
		'not_found_in_trash'    => __( 'No articles found in Trash', 'wp-document-revisions' ),
		'parent_item_colon'     => '',
		'menu_name'             => __( 'Articles', 'wp-document-revisions' ),
		'all_items'             => __( 'All Articles', 'wp-document-revisions' ),
		'featured_image'        => __( 'Article Image', 'wp-document-revisions' ),
		'set_featured_image'    => __( 'Set Article Image', 'wp-document-revisions' ),
		'remove_featured_image' => __( 'Remove Article Image', 'wp-document-revisions' ),
		'use_featured_image'    => __( 'Use as Article Image', 'wp-document-revisions' ),
	);

	return $args;
}

add_filter( 'document_revisions_cpt', 'bb_filter_document_cpt' );

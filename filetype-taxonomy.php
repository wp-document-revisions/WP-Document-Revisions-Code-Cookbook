<?php
/*
Plugin Name: WP Document Revisions - Filtering by Filetype
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate how to build a filter by filetype taxonomy
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

function wpdr_register_filetype_taxonomy() {
	
	// better way to do this would be with WP Document Revisions Custom Taxonomy Generator
	// http://wordpress.org/extend/plugins/wp-document-revisions-custom-taxonomy-and-field-generator/

	register_taxonomy( 'filetype', 'document', array(
		'label' => __('Filetypes'),
		'show_ui' => true,
		'public' => false,
	));

}

add_action( 'init', 'wpdr_register_filetype_taxonomy' );

function wpdr_update_type( $postID ) {

	$wpdr = Document_Revisions::$instance;
	
	if ( !$wpdr->verify_post_type( $postID ) )
		return;
		
	$post = get_post( $postID );
	$attachment = get_post( $post->post_content );
	$extensions = array( $wpdr->get_extension( get_attached_file( $attachment->ID ) ) ) ;
	
	wp_set_post_terms( $postID, $extensions, 'filetype', false );
}

add_action( 'save_post', 'wpdr_update_type', 10, 1 );

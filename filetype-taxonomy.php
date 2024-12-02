<?php
/**
Plugin Name: WP Document Revisions - Filtering by Filetype
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate how to build a filter by filetype taxonomy
Version: 2.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Registers the filetype taxonomy
 *
 * Better way to do this would be with a Taxonomy generator such as Simple Taxonomy Refreshed
 * https://wordpress.org/plugins/simple-taxonomy-refreshed/
 */
function wpdr_register_filetype_taxonomy() {

	// does it exist already?
	if ( false === get_taxonomy( 'filetype' ) ) {
		register_taxonomy(
			'filetype',
			'document',
			array(
				'label'   => __( 'Filetypes', 'wp-document-revisions' ),
				'show_ui' => true,
				'public'  => false,
			)
		);
	}
}

add_action( 'init', 'wpdr_register_filetype_taxonomy', 99 );

/**
 * Saves the filetype terms from the post.
 *
 * @param int $post_ID the ID of the document being saved.
 */
function wpdr_update_type( $post_ID ) {

	if ( ! class_exists( 'WP_Document_Revisions' ) ) {
		return;
	}

	$wpdr = WP_Document_Revisions::$instance;

	if ( ! $wpdr->verify_post_type( $post_ID ) ) {
		return;
	}

	$post = get_post( $post_ID );
	// is there an attachment (new post).
	$attach = $wpdr->extract_document_id( $post->post_content )
	if ( ! $attach ) {
		return;
	}

	// find the attachment filetype.
	$extensions = array( $wpdr->get_extension( get_attached_file( $attach ) ) );

	wp_set_post_terms( $post_ID, $extensions, 'filetype', false );
}

add_action( 'save_post_document', 'wpdr_update_type', 10, 1 );

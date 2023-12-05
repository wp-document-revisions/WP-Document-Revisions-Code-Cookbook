<?php
/**
Plugin Name: WP Document Revisions - File Encryption Code Sample
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate third-party resting encryption on file upload / download
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Helper function to encrypt the file.
 *
 * @param string $file          The file name to be encrypted.
 * @param string $attachment_id The attachment post.
 * @return string $file The encrypted file name to be .
 */
function wpdr_encrypt( $file, $attachment_id ) {

	// ensure WP Document Revisions is loaded.
	if ( ! class_exists( 'WP_Document_Revisions' ) ) {
		return $file;
	}

	// find parent.
	$attachment = get_post( $attachment_id );
	$parent     = $attachment->post_parent;

	// not attached to a post.
	if ( 0 === $parent ) {
		return $file;
	}

	// is it for a document?
	$wpdr = WP_Document_Revisions::$instance;
	if ( ! $wpdr->verify_post_type( $parent ) ) {
		return $file;
	}

	// make sure not a featured image. Cannot look for _thumbnail_id as not written yet.
	if ( $wpdr->is_doc_image() ) {
		return $file;
	}

	// this is it, has it already been encrypted.
	$encrypted = get_post_meta( $attachment_id, '_wpdr_encrypt', true );
	if ( 1 === $encrypted ) {
		return $file;
	}

	// pass path to file to third party encryption.
	// third_party_encryption_function( $file );
	// .

	// alternately, can be done directly via shell.
	// `crypt PASS < $file`
	// .

	// note it as encrypted.
	add_post_meta( $attachment_id, '_wpdr_encrypt', 1, true );

	// pass back the name of the encrypted file. Could be different.
	return $file;
}

add_filter( 'update_attached_file', 'wpdr_encrypt', 10, 2 );

/**
 * Helper function to decrypt the file.
 *
 * @param string  $file        File name to be served.
 * @param integer $doc_id      Post id of the document.
 * @param integer $attach_id Post id of the attachment.
 */
function wpdr_decrypt( $file, $doc_id, $attach_id ) {

	// has it already been encrypted.
	$encrypted = get_post_meta( $attach_id, '_wpdr_encrypt', true );
	if ( 1 !== $encrypted ) {
		return $file;
	}
	// pass path to file to third party decryption
	// third_party_decryption_function( $file );
	// .

	// alternately, can be done directly via shell
	// `decrypt PASS < $file`
	// .

	// pass back the name of the decrypted file. Should be different.
	return $file;
}

add_filter( 'document_serve', 'wpdr_decrypt', 10, 3 );

/**
 * Helper function to delete the decrypted file after serving.
 *
 * @param string  $file      File name that was served.
 * @param integer $attach_id Post id of the attachment.
 */
function wpdr_delete_temp( $file, $attach_id ) {

	// was it already been encrypted.
	$encrypted = get_post_meta( $attach_id, '_wpdr_encrypt', true );
	if ( 1 === $encrypted ) {
		// was not encrypted, generally nothing to do.
		return;
	}
	// delete temporary $file;
	// .
}

add_action( 'document_serve_done', 'wpdr_delete_temp', 10, 2 );

<?php
/*
Plugin Name: WP Document Revisions - State Permission Code Sample
Plugin URI: http://
Description: Code sample to demonstrate third-party resting encryption on file upload / download
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

function wpdr_encrypt( $attachment ) {
	
	//get path to attached file
	$file =  get_attached_file( $attachment->ID );
	
	//pass path to file to third party encryption
	//third_party_encryption_function( $file );
	
	//alternately, can be done directly via shell
	//`crypt PASS < $file`
	
}

add_action( 'document_upload', 'wpdr_encrypt' );

function wpdr_decrypt( $postID, $file ) {

	//pass path to file to third party encryption
	//third_party_decryption_function( $file );
	
	//alternately, can be done directly via shell
	//`decrypt PASS < $file`
	

}

add_action( 'serve_file', 'wpdr_decrypt' );

?>
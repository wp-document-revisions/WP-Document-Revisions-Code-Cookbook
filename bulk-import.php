<?php
/**
Description: Example code to show how to bulk import a directory of files into WP Document Revisions
Version: 0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2

 * @package WP Document Revisions Code Cookbook

 * * NOT A PLUGIN **
In this example, you would configure the below and manually run it once by accessing the file directly
This example will import all files of specified filetype in a given directory as WP Document Revision Documents
 */

// relative or absolute path to wp-load.php (WordPress root).
$wp_load_php = '/var/www/public_html/wp-load.php';

// relative or absolute path of directory to parse for files.
$import_directory = '/var/www/public_html/documents/';

// type of file to import.
$extension = 'pdf';

// initial revision log message (optional).
$revision_message = 'Automated import';

// id of author to associate with documents, must be valid.
$author = '1';

// Initial workflow state ID (optional).
$workflow_state = false;

// bootstrap WP.
require_once $wp_load_php;

// require additional WP files.
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 *  Helper function to parse directory for files.
 *
 * @param string $directory The directory containing files to be loaded.
 * @param string $extension The extension of the files to be loaded.
 */
function wpdr_get_files( $directory, $extension ) {

	return glob( $directory . '*.' . $extension );

}

/**
 *  Helper function to rewrites uploaded revisions filename with secure hash to mask true location.
 *
 * @param array $file file data from WP.
 * @return array $file file with new filename
 */
function filename_rewrite( $file ) {
	$extension = pathinfo( $file['name'], PATHINFO_EXTENSION );

	$file['name'] = md5( $file['name'] . microtime() ) . '.' . $extension;

	return $file;
}

// MD5 output file name.
add_filter( 'bulk_import_prefilter', 'filename_rewrite' );

// set current user.
wp_set_current_user( $author );

// $wpdr is a global reference to the class.
global $wpdr;
if ( ! $wpdr ) {
	require_once WP_PLUGIN_DIR . '/wp-document_revisions/includes/class-wp-document-revisions.php';
	$wpdr = new WP_Document_Revisions();
}

// rename images.
add_filter( 'wp_generate_attachment_metadata', array( $wpdr, 'hide_doc_attach_slug' ), 10, 3 );

/**
 * Modifies location of uploaded document revisions.
 *
 * @param array $dir defaults passed from WP.
 * @return array $dir modified directory
 */
function document_upload_dir_filter( $dir ) {
	global $wpdr;
	$wpdr_dir = $wpdr->document_upload_dir();
	$wpdr_url = '/' . $wpdr->document_slug();

	$dir['path']    = $wpdr_dir . $dir['subdir'];
	$dir['url']     = home_url( $wpdr_url ) . $dir['subdir'];
	$dir['basedir'] = $wpdr_dir;
	$dir['baseurl'] = home_url( $wpdr_url );
	return $dir;
}

// set document library path.
add_filter( 'upload_dir', 'document_upload_dir_filter' );

// set a global for setting the URL within the upload process.
global $doc_id;

/**
 * Hide the name in the URL.
 *
 * @param array $file file object from WP.
 * @return array modified file array
 */
function upload_rewrite_url( $file ) {

	global $doc_id;
	$file['url'] = get_permalink( $doc_id );

	return $file;
}

add_filter( 'wp_handle_upload', 'upload_rewrite_url', 10, 2 );

// array of files, here, a directory dump.
$files = wpdr_get_files( $import_directory, $extension );

// loop through.
foreach ( $files as $file ) {
	// cleanup filename to title.
	$doc_name = str_replace( '-', ' ', basename( $file ) );
	$doc_name = str_replace( '.' . $extension, '', $doc_name );
	$doc_name = ucwords( $doc_name );

	// build post array and insert post.
	$doc    = array(
		'post_title'   => $doc_name,
		'post_status'  => 'private',
		'post_author'  => $author,
		'post_content' => '',
		'post_excerpt' => $revision_message,
		'post_type'    => 'document',
	);
	$doc_id = wp_insert_post( $doc );

	// if initial workflow state is set, set it in post.
	if ( $workflow_state ) {
		wp_set_post_terms( $doc_id, array( $workflow_state ), 'workflow_state' );
	}

	// build attachment array and insert.
	$wp_filetype = wp_check_filetype( basename( $file ), null );
	$_FILES[0]   = array(
		'name'     => basename( $file ),
		'type'     => $wp_filetype['type'],
		'tmp_name' => $file,
		'size'     => filesize( $file ),
	);

	// attachment overrides.
	$attachment = array();

	$overrides = array(
		'test_form' => false,
		'action'    => 'bulk_import',
	);

	$attach_id = media_handle_upload( 0, $doc_id, $attachment, $overrides );

	if ( $attach_id instanceof WP_Error ) {
		wp_die( 'Error to load Attachment' );
	}

	// store attachment ID as post content.
	$doc = array(
		'ID'           => $doc_id,
		'post_content' => $wpdr->format_doc_id( $attach_id ),
	);
	wp_update_post( $doc );

	// debug info.
	echo '<p>' . esc_html( "$file added as $doc_name" ) . '</p>';
}


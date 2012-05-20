<?php
/*
Description: Example code to show how to bulk import a directory of files into WP Document Revisions
Version: 0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2

** NOT A PLUGIN **
In this example, you would configure the below and manually run it once by accessing the file directly
This example will import all files of specified filetype in a given directory as WP Document Revision Documents

*/

//relative or absolute path to wp-load.php (WordPress root)
$wp_load_php = '../../wp-load.php';

//relative or absolute path of directory to parse for files
$import_directory = '/var/www/public_html/documents/';

//type of file to import
$extension = 'pdf';

//initial revision log message (optional)
$revision_message = 'Automated import';

//id of author to associate with documents, must be valid
$author = '1';

//Initial workflow state ID (optional)
$workflow_state = false;

//Helper function to parse directory for files
function wpdr_get_files( $directory, $extension ) {
	
	return glob( $directory . "*." . $extension );
		
}

//bootstrap WP
require_once( $wp_load_php );

//array of files, here, a directory dump
$files = wpdr_get_files( $import_directory, $extension );

//loop through
foreach ( $files as $file ) {

	//cleanup filename to title
	$post_name = str_replace('-', ' ', basename( $file ) );
	$post_name = str_replace('.' . $extension, '', $post_name);
	$post_name = ucwords( $post_name );

	//build post array and insert post
	$post = array(  'post_title' => $post_name,
					'post_status' => 'private',
					'post_author' => $author,
					'post_content' => '',
					'post_excerpt' => $revision_message,
					'post_type' => 'document',
				);
	$postID = wp_insert_post( $post );
	
	//if initial workflow state is set, set it
	if ( $workflow_state ) 
		wp_set_post_terms( $postID, array( $workflow_state ), 'workflow_state' );
	
	//build attachment array and insert
	$wp_filetype = wp_check_filetype(basename($file), null );
	
 	$attachment = array(
 	    'post_mime_type' => $wp_filetype['type'],
 	    'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
 	    'post_content' => '',
 	    'post_status' => 'inherit'
 	 );
 	 
 	 $attach_id = wp_insert_attachment( $attachment, $file, $postID );
 	 
 	 //store attachment ID as post content
 	 $post = array( 'ID' => $postID, 'post_content' => $attach_id); 
 	 wp_update_post( $post );
 	 
 	 //debug info
 	 echo "<p>$file added as $post_name</p>";
 	 					
}


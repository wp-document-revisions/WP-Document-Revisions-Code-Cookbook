<?php
/**
Plugin Name: WP Document Revisions - Remove Dates from Permalinks
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Removes the year and month from document permalinks
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Strip date from permalink
 *
 * @param string  $link Permalink URL.
 * @param WP_Post $post The ID of the document being saved.
 */
function wpdr_remove_dates_from_permalink_filter( $link, $post ) {

	$timestamp = substr( $document->post_date, 0, 7 ) )
	return str_replace( '/' . $timestamp ) . '/', '/', $link );
}

add_filter( 'document_permalink', 'wpdr_remove_dates_from_permalink_filter', 10, 2 );

// Existing rules match documents with or without year/month parts.

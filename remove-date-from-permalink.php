<?php
/*
Plugin Name: WP Document Revisions - Remove Dates from Permalinks
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Removes the year and month from document permalinks
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

/**
 * Strip date from permalink
 */
function wpdr_remove_dates_from_permalink_filter( $link, $post ) {

	$timestamp = strtotime( $post->post_date );
	return str_replace( '/' . date( 'Y', $timestamp ) . '/' . date( 'm', $timestamp ) . '/', '/', $link );
	
}

add_filter( 'document_permalink', 'wpdr_remove_dates_from_permalink_filter', 10, 2 );

/**
 * Strip date from rewrite rules
 */
function wpdr_remove_date_from_rewrite_rules( $rules ) {
	global $wpdr;

	$slug = $wpdr->document_slug();

    $rules = array();

    //documents/foo-revision-1.bar
    $rules[ $slug . '/([^.]+)-' . __( 'revision', 'wp-document-revisions' ) . '-([0-9]+)\.[A-Za-z0-9]{3,4}/?$'] = 'index.php?&document=$matches[1]&revision=$matches[2]';

    //documents/foo.bar/feed/
    $rules[ $slug . '/([^.]+)(\.[A-Za-z0-9]{3,4})?/feed/?$'] = 'index.php?document=$matches[1]&feed=feed';

    //documents/foo.bar
    $rules[ $slug . '/([^.]+)\.[A-Za-z0-9]{3,4}/?$'] = 'index.php?document=$matches[1]';

    // site.com/documents/ should list all documents that user has access to (private, public)
    $rules[ $slug . '/?$'] = 'index.php?post_type=document';
    $rules[ $slug . '/page/?([0-9]{1,})/?$'] = 'index.php?post_type=document&paged=$matches[1]';
    
    return $rules;

}

add_filter( 'document_rewrite_rules', 'wpdr_remove_date_from_rewrite_rules' );

//flush rewrite rules on activation
register_activation_hook( __FILE__, 'flush_rewrite_rules' );
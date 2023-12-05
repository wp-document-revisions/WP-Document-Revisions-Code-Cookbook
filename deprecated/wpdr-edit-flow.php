<?php
/**
Plugin Name: WP Document Revisions - Edit Flow Compatability
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook/
Description: Makes WP Document Revisions work with the Edit Flow plugin
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2

@package WP Document Revisions Code Cookbook
 */

/**
 * NOTE: An updated version of this functionality is now included with WP Document Revisions by default
 * As a result, this file is no longer maintained
 */

/**
 * Filters the delivered document type definition prior to registering it.
 *
 * @param array $cpt delivered document type definition.
 */
function wpdr_ef_cpt_filter( $cpt ) {

	$cpt['supports'] = array_merge(
		$cpt['supports'],
		array(
			'ef_custom_statuses',
			'ef_editorial_comments',
			'ef_notifications',
			'ef_editorial_metadata',
			'ef_calendar',
		)
	);

	return $cpt;
}

add_filter( 'document_revisions_cpt', 'wpdr_ef_cpt_filter', 10, 1 );

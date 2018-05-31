<?php
/*
Plugin Name: WP Document Revisions - Remove Workflow State
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Removes Workflow State Taxonomy from Documents
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/


function wpdr_remove_workflow_init_hooks() {
	if ( !class_exists( 'WP_Document_Revisions' ) )
		return;

	$wpdr = WP_Document_Revisions::$instance;
	remove_action( 'admin_init', array( &$wpdr, 'initialize_workflow_states' ) );
	remove_action( 'init', array( &$wpdr, 'register_ct' ), 15 );
}

function wpdr_remove_workflow_admin_hooks() {
	if ( !class_exists( 'WP_Document_Revisions_Admin' ) )
		return;

	$wpdra = WP_Document_Revisions_Admin::$instance;
	remove_filter( 'manage_edit-document_columns', array( &$wpdra, 'add_workflow_state_column' ) );
	remove_action( 'manage_document_posts_custom_column', array( &$wpdra, 'workflow_state_column_cb' ) );
	remove_action( 'save_post', array( &$wpdra, 'workflow_state_save' ) );

}
 
function wpdr_remove_workflow_metabox() {
	remove_meta_box('workflow-state', 'document','side');
}

add_action( 'plugins_loaded', 'wpdr_remove_workflow_init_hooks', 99 );
add_action( 'admin_init', 'wpdr_remove_workflow_admin_hooks', 99 );
add_action( 'document_edit', 'wpdr_remove_workflow_metabox' );

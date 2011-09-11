<?php
/*
Plugin Name: WP Document Revisions - State Permission Code Sample
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate state-level permissions based on a custom taxonomy
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

class WPDR_State_Permissions {
	
	//taxonomy upon which permissions are based
	public $taxonomy = 'workflow_state';
	
	/**
	 * Add hooks to WP API
	 */
	function __construct() {
		add_action( 'init', array( &$this, 'add_caps' ), 20 );
		add_action( 'serve_document', array( &$this, 'serve_file_perm_check' ), 1, 1 );
		add_action( 'pre_post_update', array( &$this, 'save_post_perm_check' ), 1, 1 );
		add_action( 'admin_head', array( &$this, 'hide_upload_button') );
		add_filter( 'document_lock_check', array( &$this, 'edit_document_perm_check' ), 1, 2 );
	}
	
	/**
	 * Adds capabilities to each role
	 * Suggest using Members or similar plugin to then manage each permission
	 */
	function add_caps()  {
	
		//get the WP roles object
		$wp_roles = new WP_Roles();

		//get terms in the selected taxonomy
		$terms = get_terms( $this->taxonomy, array( 'hide_empty'=> false ) );

		//array of capabilities to build on
		//can be as many or as few as you would like
		//this example code lets users edit no files, but read all files regardless of state
		$caps = array( 
			'edit_documents' => false,
			'read_documents' => true, 
		);
	
		//loop through each role
		foreach (  $wp_roles->role_names as $role=>$label ) { 

			//loop each term
			foreach ( $terms as $term ) {

				//loop through each cap and assign
				foreach ( $caps as $cap=>$grant )				
					$wp_roles->add_cap( $role, $cap . '_in_' . $term->slug, $grant );
		
			}
			
		}

	}
	
	/**
	 * Checks user permissions when files are servers
	 */
	function serve_file_perm_check( $postID ) {
		
		if ( !$this->check_permission( $postID, 'read' ) )		
			wp_die( 'You do not have sufficient permissions to do that' );
		
	}
	
	/**
	 * Permission check for when documents are edited
	 */
	function save_post_perm_check( $postID ) {
		
		//verify post type
		$post = get_post( $postID );
		if ( $post->post_type != 'document' )
			return;
	
		if ( !$this->check_permission( $postID, 'edit' ) )		
			wp_die( 'You do not have sufficient permissions to do that' );

	}
	
	/** 
	 * Checks permission on document save
	 */
	function edit_document_perm_check( $user, $post ) {

		if ( !$this->check_permission( $post->ID, 'edit' ) )		
			return false;
		
		return $user;
					
	}
	
	/** 
	 * Hides upload button, publish, etc. if user does not have proper permissions
	 */
	function hide_upload_button( ) {
		global $post;
		
		if ( !$post || $post->post_type != 'document' )
			return;
		
		if ( !$this->check_permission( $post->ID, 'edit' )  )
			echo "<style>#publish, #add_media, #lock-notice {display: none;}</style>";		
		
	}
	
	/**
	 *  Helper function to check permissions
	 */
	function check_permission( $postID, $action ) {
	
		//get the terms in the taxonomy
		$terms = wp_get_post_terms( $postID, $this->taxonomy );
		
		//if no terms, assume they can
		if ( sizeof( $terms ) == 0)
			return true;
		
		//check permission and die if necessary
		if ( !current_user_can( $action . '_documents_in_' . $terms[0]->slug ) )
			return false;
		
		return true;

	}
		
}

new WPDR_State_Permissions;
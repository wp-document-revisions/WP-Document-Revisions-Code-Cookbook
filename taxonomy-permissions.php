<?php
/*
Plugin Name: WP Document Revisions - Taxonomy-Bassed Permissions
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Extends the Members (or other permissions plugins) to allow taxonomy (category, tag, etc.) based permissions
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL3
*/

/*  WP Document Revisions - Taxonomy-bassed Permissions
 *
 *  Extends the Members (or other permissions plugins) to allow
 *  taxonomy (category, tag, etc.) based permissions
 *
 *  =================================================================
 *
 *  USAGE:
 *
 *  (1) (Optionally) Change the target taxonomy, listed below, and
 *      if creating a new taxonomy, update the taxonomy labels
 *
 *  (2) Install and activate the Members (or another capabilities plugin)
 *
 *  (3) Create user roles as necessary, and assign the taxonomy based
 *      permissions using the easy-to-use interface Members provides.
 *
 *  =================================================================
 *
 *  Copyright (C) 2011-2012  Benjamin J. Balter  ( ben@balter.com -- http://ben.balter.com )
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @copyright 2011-2012
 *  @license GPL v3
 *  @version 1.0
 *  @package WP_Document_Revisions
 *  @subpackage Taxonomy_Permissions
 *  @author Benjamin J. Balter <ben@balter.com>
 */

/**
 * Taxonomy upon which permissions are based
 *
 * This can be an existing taxonomy, such as `category` or `workflow_state`
 * or can be a new taxonomy. If creating a taxonomy, be sure to update
 * the "labels" immediately below to match your taxonomy name.
 *
 * To based permissions off of the native `workflow_state` taxonomy, for example,
 * simply change the word `department` on the following line to `workflow_state`.
 * No other changes are needed.
 */
$wpdr_permissions_taxonomy = 'department';

/**
 * Taxonomy Labels:
 *
 * These should be updated if you would like to create a new taxonomy
 * that does not exist and is not `department`.
 *
 * Replace the words "Department" and "Departments" with the appropriate
 * human-readible label for your custom taxonomy.
 */
$wpdr_permissions_labels = array(
	'name'                       => _x( 'Departments', 'wp-document-revisions' ),
	'singular_name'              => _x( 'Department', 'wp-document-revisions' ),
	'search_items'               => _x( 'Search Departments', 'wp-document-revisions' ),
	'popular_items'              => _x( 'Popular Departments', 'wp-document-revisions' ),
	'all_items'                  => _x( 'All Departments', 'wp-document-revisions' ),
	'parent_item'                => _x( 'Parent Department', 'wp-document-revisions' ),
	'parent_item_colon'          => _x( 'Parent Department:', 'wp-document-revisions' ),
	'edit_item'                  => _x( 'Edit Department', 'wp-document-revisions' ),
	'update_item'                => _x( 'Update Department', 'wp-document-revisions' ),
	'add_new_item'               => _x( 'Add New Department', 'wp-document-revisions' ),
	'new_item_name'              => _x( 'New Department', 'wp-document-revisions' ),
	'separate_items_with_commas' => _x( 'Separate departments with commas', 'wp-document-revisions' ),
	'add_or_remove_items'        => _x( 'Add or remove departments', 'wp-document-revisions' ),
	'choose_from_most_used'      => _x( 'Choose from the most used departments', 'wp-document-revisions' ),
	'menu_name'                  => _x( 'Departments', 'wp-document-revisions' ),
);


/**
 * Taxonomy settings:
 *  These can be freely edited, but there should be no need to out of the box
 *  For more information see: http://codex.wordpress.org/Function_Reference/register_taxonomy
 */
$wpdr_permissions_taxonomy_args = array(
	'labels'            => $wpdr_permissions_labels,
	'public'            => false,
	'show_in_nav_menus' => false,
	'show_ui'           => true,
	'show_tagcloud'     => false,
	'hierarchical'      => true,
	'rewrite'           => false,
	'query_var'         => true,
	'capabilities'      => array(
		'manage_terms'  => 'manage_departments',
		'edit_terms'    => 'edit_departments',
		'delete_terms'  => 'delete_departments',
		'assign_terms'  => 'assign_departments',
	)
);

/**
 * ======================================================
 * Note: There should be no need to edit below this point
 * ======================================================
 */

class WPDR_Taxonomy_Permissions {

	public $taxonomy = '';
	public $flag = 'wpdr_init_taxonomy_permissions';
	public $base_caps = array(
		'edit_documents',
		'edit_others_documents',
		'edit_private_documents',
		'edit_published_documents',
		'read_documents',
		'read_document_revisions',
		'read_private_documents',
		'delete_documents',
		'delete_others_documents',
		'delete_private_documents',
		'delete_published_documents',
		'publish_documents',
		'override_document_lock',
	);

	/**
	 * Add hooks to WP API
	 */
	function __construct() {

		//set defaults
		global $wpdr_permissions_taxonomy;
		$this->taxonomy = ( $wpdr_permissions_taxonomy ) ? $wpdr_permissions_taxonomy : 'department';

		//register with necessary capabilitiy APIs
		add_filter( 'map_meta_cap', array( &$this, 'map_meta_cap' ), 15, 4 );
		add_filter( 'document_caps', array( &$this, 'default_caps_filter' ), 10, 2 );

		//init hooks
		add_action( 'init', array( &$this, 'maybe_register_taxonomy' ), 15 );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		register_activation_hook( __FILE__, array( &$this, 'activation' ) );

		//re-init caps on taxonomy change
		add_action( 'delete_' . $this->taxonomy, array( &$this, 'remove_caps' ) );
		add_action( 'created_' . $this->taxonomy, array( &$this, 'add_caps' ) );
		add_action( 'edited_' . $this->taxonomy, array( &$this, 'add_caps' ) );

	}


	/**
	 * Fires on admin init to register capabilities
	 * Can't add initial caps on activation, because taxonomy isn't yet registered
	 */
	function admin_init() {

		if ( !get_option( $this->flag ) )
			return;

		$this->add_caps();

	}


	/**
	 * Add our capabilities using the global WPDR object's native capabilities function
	 */
	function add_caps() {

		global $wpdr;

		if ( !$wpdr )
			$wpdr = new Document_Revisions;

		$wpdr->add_caps();

		delete_option( $this->flag );

	}


	/**
	 * Remove capabilities when a term is removed
	 * @param unknown $term
	 */
	function remove_caps( $term ) {

		$term_obj = get_term( $term, $this->taxonomy );

		global $wp_roles;
		if ( ! isset( $wp_roles ) )
			$wp_roles = new WP_Roles;

		foreach ( $wp_roles->role_names as $role => $label )
			foreach ( $this->base_caps as $base_cap )
				$wp_roles->remove_cap( $role, $base_cap . '_in_' . $term_obj->slug );

	}


	/**
	 * Checks that WP Document Revisisons is activated when this plugin is activated
	 * If so, toggle flag to add initial capabilities
	 */
	function activation() {

		if ( !class_exists( 'Document_Revisions' ) )
			wp_die( __( 'WP Document Revisions must be activated to use Taxonomy Permissions', 'wp-document-revisions' ) );

		update_option( $this->flag, true );

	}


	/**
	 * Conditionally registers the target taxonomy
	 */
	function maybe_register_taxonomy() {

		global $wpdr_permissions_taxonomy_args;

		//taxonomy exists, no need to register
		if ( taxonomy_exists( $this->taxonomy ) )
			return;

		//Register the taxonomy
		register_taxonomy( $this->taxonomy, array('document'), $wpdr_permissions_taxonomy_args );

	}


	/**
	 * Adds capabilities to each role
	 * Suggest using Members or similar plugin to then manage each permission
	 * @uses document_caps filter
	 * @param unknown $caps
	 * @param unknown $role
	 * @return unknown
	 */
	function default_caps_filter( $caps, $role ) {

		//get terms in the selected taxonomy
		$terms = get_terms( $this->taxonomy, array( 'hide_empty'=> false ) );

		//build out term specific caps
		foreach ( $caps as $cap => $grant )
			foreach ( $terms as $term )
				$caps[ $cap . '_in_' . $term->slug ] = $grant;

		//build out taxonomy capabilities
		$taxonomy = get_taxonomy( $this->taxonomy );
		
		foreach ( get_object_vars( $taxonomy->cap ) as $cap )
			$caps[ $cap ] = $grant;

		return $caps;

	}


	/**
	 * Maps caps from e.g., `edit_document` to `edit_document_in_accounting`
	 * @param unknown $caps
	 * @param unknown $cap
	 * @param unknown $user_id
	 * @param unknown $args
	 * @return unknown
	 */
	function map_meta_cap( $caps, $cap, $user_id, $args ) {

		global $wpdr;

		//attempt to grab the postID
		//note: will default to global $post if none passde
		$postID = ( !empty( $args ) ) ? $args[0] : null;

		//array of primative caps that all document caps are based on
		$primitive_caps = array( 'read_post', 'edit_post', 'delete_post', 'publish_post', 'override_document_lock' );

		//current cap being checked is not a post-specific cap, kick to save effort
		if ( !in_array( $cap, $primitive_caps ) )
			return $caps;

		//cap being checked is not related to a document, kick
		if ( !$wpdr->verify_post_type( $postID ) )
			return $caps;

		//get the terms in the taxonomy
		$terms = wp_get_post_terms( $postID, $this->taxonomy );

		//if no terms, assume primative roles
		if ( sizeof( $terms ) == 0 )
			return $caps;

		//add taxonomy specific caps
		foreach ( $caps as $cap )
			foreach ( $terms as $term )
				$caps[] = $cap . '_in_' . $term->slug;
		
		return $caps;

	}


}


new WPDR_Taxonomy_Permissions;
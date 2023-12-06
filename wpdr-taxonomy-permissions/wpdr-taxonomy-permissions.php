<?php
/**
Plugin Name: WP Document Revisions - Taxonomy-Based Permissions
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Extends the Members (or other permissions plugins) to allow taxonomy (category, tag, etc.) based permissions
Version: 1.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL3

 *  WP Document Revisions - Taxonomy-based Permissions
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
 *  Copyright (C) 2011-2020  Benjamin J. Balter  ( ben@balter.com -- http://ben.balter.com )
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
 *  @copyright 2011-2020
 *  @license GPL v3
 *  @version 1.1
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
	'name'                       => __( 'Departments', 'wp-document-revisions' ),
	'singular_name'              => __( 'Department', 'wp-document-revisions' ),
	'search_items'               => __( 'Search Departments', 'wp-document-revisions' ),
	'popular_items'              => __( 'Popular Departments', 'wp-document-revisions' ),
	'all_items'                  => __( 'All Departments', 'wp-document-revisions' ),
	'parent_item'                => __( 'Parent Department', 'wp-document-revisions' ),
	'parent_item_colon'          => __( 'Parent Department:', 'wp-document-revisions' ),
	'edit_item'                  => __( 'Edit Department', 'wp-document-revisions' ),
	'view_item'                  => __( 'View Department', 'wp-document-revisions' ),
	'update_item'                => __( 'Update Department', 'wp-document-revisions' ),
	'add_new_item'               => __( 'Add New Department', 'wp-document-revisions' ),
	'new_item_name'              => __( 'New Department', 'wp-document-revisions' ),
	'separate_items_with_commas' => __( 'Separate departments with commas', 'wp-document-revisions' ),
	'add_or_remove_items'        => __( 'Add or remove departments', 'wp-document-revisions' ),
	'choose_from_most_used'      => __( 'Choose from the most used departments', 'wp-document-revisions' ),
	'menu_name'                  => __( 'Departments', 'wp-document-revisions' ),
	'not_found'                  => __( 'No Departments found', 'wp-document-revisions' ),
	'no_terms'                   => __( 'No Departments', 'wp-document-revisions' ),
	'items_list_navigation'      => __( 'Departments list navigation', 'wp-document-revisions' ),
	'items_list'                 => __( 'Departments list', 'wp-document-revisions' ),
	/* translators: Tab heading when selecting from the most used terms. */
	'most_used'                  => __( 'Most Used', 'wp-document-revisions' ),
	'back_to_items'              => __( '&#8592; Back to Departments', 'wp-document-revisions' ),
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
		'manage_terms' => 'manage_departments',
		'edit_terms'   => 'edit_departments',
		'delete_terms' => 'delete_departments',
		'assign_terms' => 'assign_departments',
	),
);

require_once __DIR__ . '/includes/class-wpdr-taxonomy-permissions.php';

$wpdr_tp = new WPDR_Taxonomy_Permissions();

/**
 * Filters to set specific processing options.
 */

// disable phpcs from here.
// phpcs:disable

/**
 * By default, users require the term-related capability generated that correspond to the terms held on the documents.
 *
 * Setting this filter to return true will allow documents with no terms of this taxonomy to be accessed using the base document capability.
 *
 * Note. Administrators can always access documents.
 */
// add_filter( 'document_access_with_no_term', '__return_true' );

/**
 * By default, Documents can have several terms attached to the documents, however this plugin allows only one.
 *
 * Setting this filter to return false will allow documents to have several terms of this taxonomy attached.
 */
// add_filter( 'document_only_one_term_allowed', '__return_false' );

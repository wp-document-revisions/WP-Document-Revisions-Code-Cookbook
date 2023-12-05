<?php
/**
Plugin Name: WP Document Revisions - Remove Workflow State
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Removes Workflow State Taxonomy from Documents
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

add_filter( 'document_use_workflow_states', '__return_false' );

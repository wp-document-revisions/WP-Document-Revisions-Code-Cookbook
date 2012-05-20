<?php 
/*
Plugin Name: WP Document Revisions Network Administration
Plugin URI: http://ben.balter.com/2011/08/29/wp-document-revisions-document-management-version-control-wordpress/
Description: Provides interface to set network-wide options for WP Document Revisions when plugin is *not* network-activated.
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL3
*/

/*  WP Document Revisions Network Administration
 *
 *  Provides interface to set network-wide options for WP Document Revisions.
 *  Used only when plugin is *not* network-activated.
 *
 *  USAGE: Place this file in standard plugin directory and network activate.
 *  		Will only affect network administrative settings screen (not individual sites).
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
 *  @author Benjamin J. Balter <ben@balter.com>
 */
 
class Document_Revisions_Network_Admin {

	public $slug = 'wp-document-revisions';

	/**
	 * Load WP Document Revisions class, if necessary
	 * (note: this plugin fires on *all* page loads b/c it's a mu)
	 */
	function __construct() {

		//not on network admin, nothing to do here
		if ( !is_network_admin() )
			return;
						
		add_action( 'plugins_loaded', array( &$this, 'maybe_load_main_class' ) );
	
	}
	
	/**
	 * Atempts to load the main WP Document Revisions class
	 * Only fires on network admin pages, so that network settings properly appear
	 */
	function maybe_load_main_class() {
		
		//already loaded
		if ( class_exists( 'Document_Revisions') )
			return;
		
		$file = WP_PLUGIN_DIR . "/{$this->slug}/{$this->slug}.php";
		
		//couldn't find file, throw an error
		if ( !file_exists( $file ) ) {
			trigger_error( "WP Document Revisions Network Admin is properly installed in the 'wp-content/mu-plugins/' folder, but cannot properly find the main Document Revisions class. Looking in '$file'." );
			return;
		}
		
		//load the plugin as if it were network activated 
		require_once( $file );	
			
	}
	
}

new Document_Revisions_Network_Admin();
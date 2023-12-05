<?php
/**
 * Plugin Name: WP Document Revisions - Role-Based Permissions
 * Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
 * Description: Integrates the Members plugin Content Permissions functionality into WP Document Revisions to provide role based document access.
 * Version: 1.0
 * Author: Neil W. James
 * License: GPL3
 *
 *  WP Document Revisions - Role-based Permissions
 *
 *  Extends the Members plugin to allow role based permissions
 *
 *  @copyright 2023
 *  @license GPL v3
 *  @version 1.0
 *  @package WP_Document_Revisions
 *  @subpackage Role_Permissions
 *  @author Neil W. James <neil@familyjames.com>
 */

/*
 *  =================================================================
 *
 *  USAGE:
 *
 *  (1) Install and activate the Members plugin
 *
 *  (2) Create user roles as necessary.
 *
 *  (3) Ensure that they are allocated to the Documents
 *
 *  =================================================================
 *
 *  Copyright (C) 2023  Neil W. James  ( neil@familyjames.com )
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
 */

require_once __DIR__ . '/includes/class-wpdr-role-permissions.php';

$wpdr_rp = new WPDR_Role_Permissions();

// Activation hooks must be relative to the main plugin file.
register_activation_hook( __FILE__, array( &$wpdr_rp, 'activation' ) );

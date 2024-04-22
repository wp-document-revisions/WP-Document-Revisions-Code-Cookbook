<?php
/**
 * Plugin Name:       WP Document Revisions - WPML Support
 * Plugin URI:        https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
 * Description:       Add-on plugin to WP Document Revisions to support WPML. | <a href="https://github.com/NeilWJames/WP-Document-Revisions-Code-Cookbook/tree/master/wpdr-wpml-support">Documentation</a>
 * Version:           0.5
 * Author:            Neil James
 * Author URI:        http://github.com/NeilWJames
 * License:           GPLv3 or later
 * Requires at least: 4.9
 * Requires PHP:      7.4
 * Text Domain:       wpdr-wpml-support
 * Domain Path:       /languages
 *
 * @package WPML Support for WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this file directly.' );
}

// Check that WP Document Revisions is active.
if ( ! in_array( 'wp-document-revisions/wp-document-revisions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	if ( is_admin() ) {
		echo wp_kses_post( '<div class="notice notice-warning is-dismissible"><p>' );
		// translators: Do not translate WPML Support for WP Document Revisions or WP Document Revisions.
		esc_html_e( 'Plugin WPML Support for WP Document Revisions is activated but its required plugin WP Document Revisions is not.', 'wpdr-wpml-debug' );
		echo wp_kses_post( '</p><p>' );
		// translators: Do not translate WPML Support for WP Document Revisions.
		esc_html_e( 'Plugin WPML Support for WP Document Revisions will not activate its functionality.', 'wpdr-wpml-debug' );
		echo wp_kses_post( '</p></div>' );
	}
	return;
}

/**
 * Initialise classes.
 *
 * @since 0.5
 */
global $wpdr_pil;
require_once __DIR__ . '/includes/class-process-item-list.php';
$wpdr_pil = new Process_Item_List( 'wpdr_wpml' );
require_once __DIR__ . '/includes/class-wpdr-wpml-support.php';
$wpdr_ml = new WPDR_WPML_Support();

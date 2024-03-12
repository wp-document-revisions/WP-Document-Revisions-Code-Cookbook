<?php
/**
 * Plugin Name:       WP Document Revisions - WPML Support
 * Plugin URI:        https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
 * Description:       Add-on plugin to WP Document Revisions to support WPML. | <a href="https://github.com/NeilWJames/WP-Document-Revisions-Code-Cookbook/blob/master/wpdr-wpml-support/readme.md">Documentation</a>
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
		esc_html_e( 'Plugin WPML Support for WP Document Revisions is activated but its required plugin WP Document Revisions is not.', 'wp-document-revisions' );
		echo wp_kses_post( '</p><p>' );
		// translators: Do not translate WPML Support for WP Document Revisions.
		esc_html_e( 'Plugin WPML Support for WP Document Revisions will not activate its functionality.', 'wp-document-revisions' );
		echo wp_kses_post( '</p></div>' );
	}
	return;
}

/**
 * Email Notice WP Document Revisions.
 */
add_action( 'plugins_loaded', 'wpdr_wpml_support_init' );

/**
 * Initialise classes.
 *
 * @since 1.0
 */
function wpdr_wpml_support_init() {
	// Admin (Load when needed).
	if ( is_admin() ) {
		global $wpdr_pil;
		require_once __DIR__ . '/includes/class-process-item-list.php';
		$wpdr_pil = new Process_Item_List( 'wpdr_wpml' );
		require_once __DIR__ . '/includes/class-wpdr-wpml-support.php';
		$wpdr_en = new WPDR_WPML_Support();
	}
}

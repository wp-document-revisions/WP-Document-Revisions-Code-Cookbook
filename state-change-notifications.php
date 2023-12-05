<?php
/**
Plugin Name: WP Document Revisions - State Change Notifications
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate automated notifications on document workflow state change
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Sends an email on a change of workflow_state.
 *
 * @param integer $post_ID            The post ID.
 * @param string  $new_workflow_state The new state.
 * @param string  $old_workflow_state The old state.
 */
function wpdr_state_change( $post_ID, $new_workflow_state, $old_workflow_state ) {
	// get the post.
	$post = get_post( $post_ID );

	// get the author of the post, as an example, could be a specific user or group of users as well.
	$author = get_user_by( 'id', $post->post_author );
	if ( false === $author ) {
		$email = get_option( 'admin_email' );
	} else {
		$email = $author->user_email;
	}

	// get the term name.
	$new = ( '' === $new_workflow_state ? '' : get_term( $new_workflow_state, 'workflow_state' )->name );
	$old = ( '' === $old_workflow_state ? '' : get_term( $old_workflow_state, 'workflow_state' )->name );

	// format message.
	$subject = 'State Change for Document: ' . $post->post_title;
	$message = '"' . $post->post_title . '" has been transitioned from "' . $old . '" to "' . $new . '".';

	// send the e-mail.
	wp_mail( $email, $subject, $message );
}

add_action( 'document_change_workflow_state', 'wpdr_state_change', 10, 3 );

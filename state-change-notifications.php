<?php
/*
Plugin Name: WP Document Revisions - State Change Notifications
Plugin URI: http://
Description: Code sample to demonstrate automated notifications on document workflow state change
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

function wpdr_state_change( $postID, $new_workflow_state ) {
	
	//get the post
	$post = get_post( $postID );
	
	//get the author of the post, as an example, could be a specific user or group of users as well
	$author = get_user_by( 'id', $post->post_author );
	
	//get the term
	$term = get_term( $new_workflow_state, 'workflow_state' );
	 
	//format message
	$subject = "State Change for Document: " . $post->post_title;
	$message = $post->post_title . " has been transitioned to " . $state->name;
	
	//send the e-mail
	wp_mail( $author->user_email, $subject, $message );
	
} 

add_action( 'change_document_workflow_state', 'wpdr_state_change', 10, 2 );
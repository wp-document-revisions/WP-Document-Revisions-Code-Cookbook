<?php
/**
Plugin Name: WP Document Revisions - Audit Trail Code Sample
Plugin URI: https://github.com/wp-document-revisions/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate check-in/check-out audit trail functionality
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Helper function to return audit trail as an array
 * array has elements array( 'timestamp' = YYYY-MM-DD H:i:s, 'user' => {userID}, 'action' => {Check in|Check Out}
 *
 * @param integer $post_ID The post ID.
 */
function wpdr_get_audit_trail( $post_ID ) {

	$downloads = wpdr_get_downloads( $post_ID );
	$uploads   = wpdr_get_uploads( $post_ID );

	// merge uploads and downloads into a single trail.
	$trail = array_merge( $downloads, $uploads );

	// sort by timestamp.
	wpdr_sort( $trail, 'timestamp' );

	return $trail;
}

/**
 * Returns array of downloads from post meta
 *
 * @param integer $post_ID The post ID.
 */
function wpdr_get_downloads( $post_ID ) {
	$downloads = get_post_meta( $post_ID, 'document_audit', false );

	// get_post_meta returns false if there are no results, but we use array_merge later, so force array.
	if ( ! is_array( $downloads ) ) {
		return array();
	}

	// sort by timestamp.
	wpdr_sort( $downloads, 'timestamp' );

	return $downloads;
}

/**
 * Revisions normally in uploads - but not if past limit, so store in downloads.
 *
 * @param int          $revision_id Post revision ID.
 * @param object|array $revision    Post revision object or array.
 */
function wpdr_delete_revision( $revision_id, $revision ) {
	// ensure WP Document Revisions is loaded.
	if ( ! class_exists( 'WP_Document_Revisions' ) ) {
		return;
	}

	// identify parent.
	$post_ID = $revision->parent;

	// check that it is a document revision.
	$wpdr = WP_Document_Revisions::$instance;
	if ( ! $wpdr->verify_post_type( $post_ID ) ) {
		return;
	}

	// format data array.
	$data = array(
		'timestamp' => $revision->post_date_gmt,
		'user'      => $revision->post_author,
		'action'    => 'Check In',
	);

	// store the meta.
	add_post_meta( $post_ID, 'document_audit', $data );
}

add_action( 'wp_delete_post_revision', 'wpdr_delete_revision', 10, 2 );

/**
 * Parses standard WP revision history into an array for the audit trail
 *
 * @param integer $post_ID The post ID.
 */
function wpdr_get_uploads( $post_ID ) {
	$uploads = array();

	// get revisions using our internal function.
	$wpdr      = WP_Document_Revisions::$instance;
	$revisions = $wpdr->get_revisions( $post_ID );

	// loop through and build an array.
	foreach ( $revisions as $revision ) {
		$uploads[] = array(
			'timestamp' => $revision->post_date_gmt,
			'user'      => $revision->post_author,
			'action'    => __( 'Check In', 'wp-document-revisions' ),
		);
	}

	// sort by timestamp.
	wpdr_sort( $uploads, 'timestamp' );

	return $uploads;
}

/**
 * Logs file downloads by user and time
 *
 * @param integer $post_ID The post ID.
 */
function wpdr_log_download( $post_ID ) {
	// make sure we have the post parent not the revision.
	$parent = wp_is_post_revision( $post_ID );
	if ( false !== $parent ) {
		$post_ID = $parent;
	}

	// format data array.
	$data = array(
		'timestamp' => gmdate( 'Y-m-d H:i:s' ),
		'user'      => get_current_user_id(),
		'action'    => __( 'Check Out', 'wp-document-revisions' ),
	);

	// store the meta.
	add_post_meta( $post_ID, 'document_audit', $data );
}

add_action( 'serve_document', 'wpdr_log_download', 10, 1 );

/**
 * Sorts an array of arrays by a specific key
 * From: http://stackoverflow.com/questions/2699086/php-sort-multidimensional-array-by-value
 *
 * @param array   $arr The array to be sorted.
 * @param string  $col The column of the array to be the sort key.
 * @param integer $dir Direction of sort SORT_ASC or SORT_DESC.
 */
function wpdr_sort( &$arr, $col, $dir = SORT_ASC ) {
	$sort_col = array();
	foreach ( $arr as $key => $row ) {
		$sort_col[ $key ] = $row[ $col ];
	}

	array_multisort( $sort_col, $dir, $arr );
}

/**
 * Formats and outputs audit trail metabox
 *
 * Convert title to local time
 *
 * @param WP_Post $post The post object.
 */
function wpdr_audit_metabox( $post ) {
	// get the trail.
	$trail = wpdr_get_audit_trail( $post->ID );

	// if there is no trail, kick.
	if ( 0 === count( $trail ) ) {
		return;
	}
	?>
	<table width="100%">
		<tr>
			<th style="text-align: left;"><?php esc_html_e( 'Time', 'wp-document-revisions' ); ?></th>
			<th style="text-align: left;"><?php esc_html_e( 'User', 'wp-document-revisions' ); ?></th>
			<th style="text-align: left;"><?php esc_html_e( 'Event', 'wp-document-revisions' ); ?></th>
		</tr>
	<?php
	foreach ( $trail as $event ) {
		$user = get_user_by( 'id', $event['user'] );
		if ( is_object( $user ) ) {
			$user_name = $user->display_name;
		} else {
			$user_name = 'Deleted - ' . $event['user'];
		}
		?>
		<tr>
			<td><abbr class="timestamp" title="<?php echo esc_html( get_date_from_gmt( $event['timestamp'] ) ); ?>" id="<?php echo esc_html( strtotime( $event['timestamp'] ) ); ?>"><?php echo esc_html( human_time_diff( strtotime( $event['timestamp'] ) ) ); ?></abbr> ago</td>
			<td><?php echo esc_html( $user_name ); ?></td>
			<td><?php echo esc_html( $event['action'] ); ?></td>
		</tr>
	<?php } ?>
	</table>
	<?php
}

/**
 * Registers the audit trail metabox with the WordPress metabox API
 */
function wpdr_add_audit_metabox() {
	add_meta_box( 'document_audit_trail', 'Audit Trail', 'wpdr_audit_metabox', 'document', 'normal', 'low' );
}

add_action( 'add_meta_boxes_document', 'wpdr_add_audit_metabox' );

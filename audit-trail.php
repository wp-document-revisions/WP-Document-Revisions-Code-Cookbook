<?php
/*
Plugin Name: WP Document Revisions - Audit Trail Code Sample
Plugin URI: https://github.com/benbalter/WP-Document-Revisions-Code-Cookbook
Description: Code sample to demonstrate check-in/check-out audit trail functionality
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

/** 
 * Helper function to return audit trail as an array
 * array is the form array( 'timestamp' = YYYY-MM-DD H:i:s, 'user' => {userID}, 'action' => {Check in|Check Out}
 */
function wpdr_get_audit_trail( $postID ) {
	
	$downloads = wpdr_get_downloads( $postID );
	$uploads = wpdr_get_uploads( $postID );
	
	//merge uploads and downloads into a single trail
	$trail = array_merge( $downloads, $uploads );

	//sort by timestamp
	wpdr_sort( $trail, 'timestamp' );
	
	return $trail;
}

/**
 * Returns array of downloads from post meta
 */
function wpdr_get_downloads( $postID ) {

	$downloads = get_post_meta( $postID,  'document_audit' );
	
	//get_post_meta returns false if there are no results, but we use array_merge later, so force array
	if ( !is_array( $downloads ) )
		return array();
	
	//sort by timestamp
	wpdr_sort( $downloads, 'timestamp' );
	
	return $downloads;
	
}

/**
 * Parses standard WP revision history into an array for the audit trail
 */
function wpdr_get_uploads( $postID ) {
	global $post;
	$uploads = array();

	//get revisions using our internal function
	$wpdr = Document_Revisions::$instance;
	$revisions = $wpdr->get_revisions( $post->ID );
	
	//loop through and build an array	
	foreach ( $revisions as $revision )
		$uploads[] = array( 'timestamp' =>  $revision->post_date, 
							'user' => $revision->post_author, 
							'action' => 'Check In' 
							);

	//sort by timestamp
	wpdr_sort( $uploads, 'timestamp' );

	return $uploads;
	
}

/**
 * Logs file downloads by user and time
 */
function wpdr_log_download( $postID ) {

	//make sure we have the post parent not the revision
	if ( $parent = wp_is_post_revision( $postID ) )
		$postID = $parent;
	
	//format data array
	$data = array( 	'timestamp' => current_time( 'mysql' ), 
					'user' => get_current_user_id(),
					'action' => 'Check Out' 
					);
	
	//store the meta
	add_post_meta( $postID, 'document_audit', $data );

}

add_action( 'serve_document', 'wpdr_log_download', 10, 1 );

/**
 * Sorts an array of arrays by a specific key
 * From: http://stackoverflow.com/questions/2699086/php-sort-multidimensional-array-by-value
 */
function wpdr_sort(&$arr, $col, $dir = SORT_ASC) {
    $sort_col = array();
    foreach ($arr as $key=> $row) {
        $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
}

/**
 * Formats and outputs audit trail metabox
 */
function wpdr_audit_metabox( $post ) {
	
	//get the trail
	$trail = wpdr_get_audit_trail( $post->ID );

	//if there is no trail, kick
	if ( sizeof( $trail ) == 0 )
		return;
	?>
	<table width="100%">
		<tr>
			<th style="text-align: left;">Time</th>
			<th style="text-align: left;">User</th>
			<th style="text-align: left;">Event</th>
		</tr>
	<?php
	foreach ( $trail as $event ) { 
		$user = get_user_by( 'id', $event['user'] );
		?>
		<tr>
			<td><abbr class="timestamp" title="<?php echo $event['timestamp']; ?>" id="<?php echo strtotime( $event['timestamp'] ); ?>"><?php echo human_time_diff( strtotime( $event['timestamp'] ), current_time( 'timestamp' ) ); ?></abbr> ago</td>
			<td><?php echo esc_html( $user->display_name ); ?></td>
			<td><?php echo $event['action']; ?></td>
		</tr>
	<?php } ?>
	</table>
	<?php
}

/**
 * Registers the audit trail metabox with the wordpress metabox API
 */
function wpdr_add_audit_metabox() {
	add_meta_box( 'document_audit_trail', 'Audit Trail', 'wpdr_audit_metabox', 'document', 'normal', 'low' );
}

add_action( 'add_meta_boxes', 'wpdr_add_audit_metabox');

?>
<?php
/*
Plugin Name: Document Parent UI
Description: Creates UI for selecting document parent
Author: Benjamin Balter
Version: 1.0
Author URI: http://ben.balter.com/
*/

class Document_Post_Parent {

	private $post_type = 'document'; 
	private $parent_post_type = 'post'; //post_type to query for

	/**
	 * Register hooks with WP
	 */
	function __construct() {
	
		add_action( 'add_meta_boxes', array( &$this, 'add_metabox' ) );
		add_action( 'admin_init', array( &$this, 'enqueue_autocomplete' ) );
		add_action( 'wp_ajax_document_parent_lookup', array( &$this, 'lookup' ) );
		add_action( 'wp_insert_post_parent', array( &$this, 'save_parent' ) );
		
	}
	
	/**
	 * Add metabox to document pages
	 */
	function add_metabox() {
	
	add_meta_box( 'document-parent', 'Post Parent', array( &$this, 'metabox' ), $this->post_type, 'normal', 'low' );

	}
	
	/**
	 * Metabox callback
	 */
	function metabox( $post ) { 
		wp_nonce_field( 'document_parent', '_parent_nonce' );
		$post_parent = get_post( $post->post_parent );
	?>
	<p><input type="text" class="widefat" name="post_parent" id="post_parent" value="<?php echo ( $post_parent ) ? $post_parent->post_title : 0; ?>" />
	<input type="hidden" id="post_parent_id" name="post_parent_id" value="<?php echo $post->post_parent; ?>" />
	</p>
	<script>jQuery(document).ready(function($) {
		$('#post_parent').autocomplete( ajaxurl + '?action=document_parent_lookup&_wpnonce=<?php echo wp_create_nonce('document_parent_lookup'); ?>',{autoFill: true,});
		$('#post_parent').result(function(event,data,formatted) {
			if (data) $('#post_parent_id').val(data[1]);
		});
	});</script>
	<?php }
	
	/**
	 * Loads autocomplete jquery plugin on document edit page
	 */
	function enqueue_autocomplete() {
		global $pagenow;		
		
		//verify either new or existing document
		if ( $pagenow != 'post-new.php' && $pagenow != 'post.php' )
			return;
		
		if ( $pagenow == 'post-new.php' && ( !isset( $_GET['post_type'] ) || $_GET['post_type'] != 'document' ) )
			return;
		
		if ( $pagenow == 'post.php' && get_post( $_GET['post'] )->post_type != $this->post_type )
			return;
		
		//js
		$suffix = ( WP_DEBUG ) ? '.min' : '';
		wp_enqueue_script( 'jquery.autocomplete', plugins_url( '/js/jquery.autocomplete' . $suffix . '.js', __FILE__ ), array( 'jquery' ), null, true );
		
		//css
		wp_enqueue_style( 'jquery.autocomplete', plugins_url( '/js/jquery.autocomplete.css', __FILE__ ) );
		
	}
	
	/**
	 * Callback to lookup products for autocomplete
	 */
	function lookup() {
		global $wpdb;
				
		if ( !isset( $_GET['q'] ) )
			die( -1 );
		
		check_admin_referer( 'document_parent_lookup' );
							
		$posts = $wpdb->get_results( $wpdb->prepare( "Select ID, post_title FROM $wpdb->posts WHERE post_type = '{$this->parent_post_type}' AND post_status = 'publish' AND post_title LIKE '%%%s%%' ORDER BY post_title ASC", $_GET['q'] ) );
						
		foreach ( $posts as $post ) {
			echo $post->post_title . '|' . $post->ID . "\n";
		}
		
		exit();
	}
	
	/**
	 * Filter to save post_parent when document is updated
	 */
	function save_parent( $parent, $postID = null , $keys = null, $post = null) {
		
		//filter fires on new and non-document posts, if so kick
		if ( !isset( $_POST['post_parent_id'] ) )
			return $parent;	
		
		//nonce check also verifies this is a document
		check_admin_referer( 'document_parent', '_parent_nonce' );
					
		if ( !current_user_can( 'edit_post', $postID ) )
			return;
		
		return (int) $_POST['post_parent_id'];
				 				
	}
	
}

new Document_Post_Parent();
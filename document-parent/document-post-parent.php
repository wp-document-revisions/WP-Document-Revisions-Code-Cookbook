<?php
/**
Plugin Name: Document Parent UI
Description: Creates UI for selecting document parent
Author: Benjamin Balter
Version: 1.0
Author URI: http://ben.balter.com/

@package WP-Document-Revisions-Code-Cookbook
 */

/**
 * Class definition.
 */
class Document_Post_Parent {

	/**
	 * Identify document post type.
	 *
	 * @var string $post_type The document post type.
	 */
	private $post_type = 'document';
	/**
	 * Document type parent.
	 *
	 * @var string $parent_post_type parent The document parent post type.
	 */
	private $parent_post_type = 'post'; // post_type to query for.

	/**
	 * Register hooks with WP
	 */
	public function __construct() {

		add_action( 'add_meta_boxes', array( &$this, 'add_metabox' ) );
		add_action( 'admin_init', array( &$this, 'enqueue_autocomplete' ) );
		add_action( 'wp_ajax_document_parent_lookup', array( &$this, 'lookup' ) );
		add_action( 'wp_insert_post_parent', array( &$this, 'save_parent' ) );
	}

	/**
	 * Add metabox to document pages
	 */
	public function add_metabox() {

		add_meta_box( 'document-parent', 'Post Parent', array( &$this, 'metabox' ), $this->post_type, 'normal', 'low' );
	}

	/**
	 * Metabox callback
	 *
	 * @param WP_Post $post The post object.
	 */
	public function metabox( $post ) {
		wp_nonce_field( 'document_parent', '_parent_nonce' );
		$token       = wp_create_nonce( 'document_parent_lookup' );
		$post_parent = get_post( $post->post_parent );
		?>
	<p><input type="text" class="widefat" name="post_parent" id="post_parent" value="<?php echo esc_html( ( $post_parent ) ? $post_parent->post_title : 0 ); ?>" />
	<input type="hidden" id="post_parent_id" name="post_parent_id" value="<?php echo esc_html( $post->post_parent ); ?>" />
	</p>
	<script>jQuery(document).ready(function($) {
		$('#post_parent').autocomplete( ajaxurl + '?action=document_parent_lookup&_wpnonce=<?php echo esc_html( $token ); ?>',{autoFill: true,});
		$('#post_parent').result(function(event,data,formatted) {
			if (data) $('#post_parent_id').val(data[1]);
		});
	});</script>
		<?php
	}

	/**
	 * Loads autocomplete jquery plugin on document edit page
	 */
	public function enqueue_autocomplete() {
		check_admin_referer();
		global $pagenow;

		// verify either new or existing document.
		if ( 'post-new.php' !== $pagenow && 'post.php' !== $pagenow ) {
			return;
		}

		if ( 'post-new.php' === $pagenow && ( ! isset( $_GET['post_type'] ) || 'document' !== sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ) ) {
			return;
		}

		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) && get_post( sanitize_text_field( wp_unslash( $_GET['post'] ) ) )->post_type !== $this->post_type ) {
			return;
		}

		// js.
		$suffix  = ( WP_DEBUG ) ? '' : '.min';
		$js_file = '/js/jquery.autocomplete' . $suffix . '.js';
		wp_enqueue_script( 'jquery.autocomplete', plugins_url( $js_file, __DIR__ ), array( 'jquery' ), filemtime( __DIR__ . $js_file ), true );

		// css.
		$css_file = '/js/jquery.autocomplete.css';
		wp_enqueue_style( 'jquery.autocomplete', plugins_url( $css_file, __DIR__ ), array(), filemtime( __DIR__ . $css_file ) );
	}

	/**
	 * Callback to lookup products for autocomplete
	 */
	public function lookup() {
		global $wpdb;

		if ( ! isset( $_GET['q'] ) ) {
			die( -1 );
		}

		check_admin_referer( 'document_parent_lookup' );

		// phpcs:ignore
		$posts = $wpdb->get_results( $wpdb->prepare( "Select ID, post_title FROM $wpdb->posts WHERE post_type = '{$this->parent_post_type}' AND post_status = 'publish' AND post_title LIKE '%%%s%%' ORDER BY post_title ASC", wp_unslash( $_GET['q'] ) ) );

		foreach ( $posts as $post ) {
			echo esc_html( $post->post_title . '|' . $post->ID . "\n" );
		}

		exit();
	}

	/**
	 * Filter to save post_parent when document is updated
	 *
	 * @param string  $parent_ID Post parent ID.
	 * @param integer $post_ID   Post ID.
	 * @param array   $keys      Array of parsed post data.
	 * @param array   $post      Array of sanitized, but otherwise unmodified post data.
	 */
	public function save_parent( $parent_ID, $post_ID = null, $keys = null, $post = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// filter fires on new and non-document posts, if so kick.
		if ( ! isset( $_POST['post_parent_id'] ) ) {
			return $parent_ID;
		}

		// nonce check also verifies this is a document.
		check_admin_referer( 'document_parent', '_parent_nonce' );

		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}

		return (int) $_POST['post_parent_id'];
	}
}

new Document_Post_Parent();

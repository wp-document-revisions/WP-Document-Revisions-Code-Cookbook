<?php
/**
Plugin Name: WP Document Revisions - Track Changes to Document Metadata
Plugin URI:
Description: Auto-generates and appends revision summaries for changes to taxonomies, title, and visibility
Version: 0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
 *
 * @package WP Document Revisions Code Cookbook
 */

/**
 * Main WP_Document_Revisions Track class.
 */
class WPDR_Track_Meta_Changes {
	/**
	 * Change List.
	 *
	 * @var array $document_change_list
	 */
	public $document_change_list = array();

	/**
	 * WPDR Class.
	 *
	 * @var object Class
	 */
	public $wpdr;

	/**
	 * Construct
	 */
	public function __construct() {

		// set up class.
		add_action( 'plugins_loaded', array( &$this, 'setup_wpdr' ) );

		// taxs.
		add_action( 'set_object_terms', array( &$this, 'build_taxonomy_change_list' ), 10, 6 );

		// status.
		add_action( 'transition_post_status', array( &$this, 'track_status_changes' ), 10, 3 );

		// title.
		add_action( 'save_post_document', array( &$this, 'track_title_changes' ), 10, 1 );

		// appending.
		add_action( 'save_post_document', array( &$this, 'append_changes_to_revision_summary' ), 20, 1 );
	}

	/**
	 * Makes all WPDR functions accessible as $this->wpdr->{function}
	 * Call here so that Doc Revs is loaded
	 */
	public function setup_wpdr() {
		// ensure WP Document Revisions is loaded.
		if ( ! class_exists( 'WP_Document_Revisions' ) ) {
			return;
		}
		$this->wpdr = WP_Document_Revisions::$instance;
	}

	/**
	 * Compares post title to previous revisions post title and adds to internal array if changed
	 *
	 * @param int $post_ID the id of the post to check.
	 */
	public function track_title_changes( $post_ID ) {

		if ( $this->dont_track( $post_ID ) ) {
			return false;
		}

		$new       = get_post( $post_ID );
		$revisions = $this->wpdr->get_revisions( $post_ID );

		// because we've already saved, [0] = this one, [1] = the previous one.
		$old = $revisions[1];

		if ( $new->post_title === $old->post_title ) {
			return;
		}

		do_action( 'document_title_changed', $post_ID, $old, $new );

		// translators: %1$s is the old title,  %2$s is the new title.
		$this->document_change_list[] = sprintf( __( 'Title changed from "%1$s" to "%2$s"', 'wp-document-revisions' ), $old->post_title, $new->post_title );
	}

	/**
	 * Tracks when a post status changes
	 *
	 * @param string $new_s the new status.
	 * @param string $old_s the old status.
	 * @param object $post  the post object.
	 */
	public function track_status_changes( $new_s, $old_s, $post ) {

		if ( $this->dont_track( $post->ID ) ) {
			return false;
		}

		if ( 'new' === $old_s || 'auto_draft' === $old_s ) {
			return false;
		}

		if ( $new_s === $old_s ) {
			return false;
		}

		do_action( 'document_visibility_changed', $post->ID, $old_s, $new_s );

		// translators: %1$s is the old status,  %2$s is the new status.
		$this->document_change_list[] = sprintf( __( 'Visibility changed from "%1$s" to "%2$s"', 'wp-document-revisions' ), $old_s, $new_s );
	}

	/**
	 * Tracks changes to taxonomies
	 *
	 * @param int    $object_id the document ID.
	 * @param array  $terms the new terms.
	 * @param array  $tt_ids the new term IDs.
	 * @param string $taxonomy the taxonomy being changed.
	 * @param bool   $append whether it is being appended or replaced.
	 * @param array  $old_tt_ids term taxonomy ID array before the change.
	 */
	public function build_taxonomy_change_list( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

		if ( $this->dont_track( $object_id ) ) {
			return false;
		}

		// Added terms are specified terms, that did not already exist.
		$added_tt_ids = array_diff( $tt_ids, $old_tt_ids );

		if ( $append ) {
			// If appending terms - nothing was removed.
			$removed_tt_ids = array();
		} else {
			// Removed terms will be old terms, that were not specified in $tt_ids.
			$removed_tt_ids = array_diff( $old_tt_ids, $tt_ids );
		}

		$taxonomy = get_taxonomy( $taxonomy );

		// grab the proper taxonomy label.
		$taxonomy_formatted = ( count( $added_tt_ids ) + count( $removed_tt_ids ) === 1 ) ? $taxonomy->labels->singular_name : $taxonomy->labels->name;
		$add                = '';
		$sep                = '';
		$rem                = '';
		// Deal with added ones.
		if ( ! empty( $added_tt_ids ) ) {
			// These are taxonomy term IDs so need to get names from term_ids.
			$terms_fmt = array();
			foreach ( $added_tt_ids as $term ) {
				$term_obj    = get_term_by( 'term_taxonomy_id', $term, $taxonomy );
				$terms_fmt[] = '"' . $term_obj->name . '"';
			}

			// human format the string by adding an "and" before the last term.
			$last = array_pop( $terms_fmt );
			if ( ! count( $terms_fmt ) ) {
				$terms_formatted = $last;
			} else {
				$terms_formatted = implode( ', ', $terms_fmt ) . __( ' and ', 'wp-document-revisions' ) . $last;
			}

			// translators: %1$s is the list of terms added.
			$add = sprintf( __( ' %1$s added', 'wp-document-revisions' ), $terms_formatted );

			if ( ! empty( $removed_tt_ids ) ) {
				// translators: separator between added and removed..
				$sep = __( ',', 'wp-document-revisions' );
			}
		}

		// Deal with removed ones.
		if ( ! empty( $removed_tt_ids ) ) {
			// These are taxonomy term IDs so need to get names from term_ids.
			$terms_fmt = array();
			foreach ( $removed_tt_ids as $term ) {
				$term_obj    = get_term_by( 'term_taxonomy_id', $term, $taxonomy );
				$terms_fmt[] = '"' . $term_obj->name . '"';
			}

			// human format the string by adding an "and" before the last term.
			$last = array_pop( $terms_fmt );
			if ( ! count( $terms_fmt ) ) {
				$terms_formatted = $last;
			} else {
				$terms_formatted = implode( ', ', $terms_fmt ) . __( ' and ', 'wp-document-revisions' ) . $last;
			}

			// translators: %1$s is the list of terms removed.
			$rem = sprintf( __( ' %1$s removed', 'wp-document-revisions' ), $terms_formatted );
		}

		if ( '' !== $add || '' !== $rem ) {
			// translators: %1$s is the taxonomy's name,  %2$s is the list of terms added,  %3$s is the separator,  %3$s is the list of terms removed.
			$message = sprintf( __( '%1$s:%2$s%3$s%4$s', 'wp-document-revisions' ), $taxonomy_formatted, $add, $sep, $rem );

			$this->document_change_list[] = $message;
		}

		do_action( 'document_taxonomy_changed', $object_id, $taxonomy->name, $tt_ids, $old_tt_ids );
	}

	/**
	 * Loops through document change list and appends to latest revisions's log message
	 *
	 * @param int $post_ID the ID of the document being changed.
	 */
	public function append_changes_to_revision_summary( $post_ID ) {
		global $wpdb;

		if ( $this->dont_track( $post_ID ) ) {
			return false;
		}

		if ( empty( $this->document_change_list ) ) {
			return false;
		}

		$post    = get_post( $post_ID );
		$message = trim( $post->post_excerpt );

		if ( ! empty( $message ) && ' ' !== substr( $message, -1, 1 ) ) {
			$message .= ' ';
		}

		// escape HTML and implode list on semi-colons.
		$change_list = esc_html( stripslashes( implode( '; ', $this->document_change_list ) ) );
		$message    .= '(' . $change_list . ')';
		$message     = apply_filters( 'document_revision_log_auto_append_message', $message, $post_ID );

		// manually update the DB here so that we don't create another revision.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $message ), array( 'ID' => $post_ID ), '%s', '%d' );

		// need to clean the cache.
		clean_post_cache( $post_ID );

		$r = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(ID) FROM ' . $wpdb->posts . " WHERE post_parent=%d AND post_type='revision';",
				$post_ID
			)
		);

		if ( ! is_null( $r ) ) {
			$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $message ), array( 'ID' => $r ), '%s', '%d' );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// clear WPDR revision cache.
		wp_cache_delete( $post_ID, 'document_revisions' );

		do_action( 'document_meta_change', $post_ID, $message );

		// reset in case another post is also being saved for some reason.
		$this->document_change_list = array();
	}

	/**
	 * Determines whether changes should be tracked for a given post
	 *
	 * @param int $post_ID the ID of the post.
	 * @returns bool true if shouldn't track, otherwise false
	 */
	private function dont_track( $post_ID ) {
		if ( ! apply_filters( 'track_document_meta_changes', true, $post_ID ) ) {
			return true;
		}

		if ( wp_is_post_revision( $post_ID ) ) {
			return true;
		}

		if ( ! $this->wpdr->verify_post_type( $post_ID ) ) {
			return true;
		}

		$revisions = $this->wpdr->get_revisions( $post_ID );

		if ( count( $revisions ) <= 1 ) {
			return true;
		}

		return false;
	}
}

new WPDR_Track_Meta_Changes();

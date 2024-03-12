<?php
/**
 * WPML Support for WP Document Revisions Main Functionality
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package WPML Support for WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( esc_html__( 'You are not allowed to call this file directly.', 'wpdr-wpml-support' ) );
}

/**
 * WPML Debug for WP Document Revisions class.
 */
class WPDR_WPML_Support {

	/* Initialisation */

	/**
	 * File version.
	 *
	 * @since 0.5
	 *
	 * @var string $version
	 */
	public static $version = '0.5';

	/**
	 * JS has been loaded.
	 *
	 * @since 0.5
	 *
	 * @var boolean $js_loaded
	 */
	private static $js_loaded = false;

	/**
	 * Constructor
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	public function __construct() {
		// Remove media settings.
		add_action( 'init', array( $this, 'init' ) );

		// Initialize settings.
		add_action( 'admin_init', array( $this, 'admin_init' ), 2000 );
	}

	/**
	 * Remove WPDR media library pocessing.
	 *
	 * @since 0.5
	 * @global $wpdr
	 * @return void
	 */
	public function init() {
		// remove media library processing from WPDR.
		global $wpdr;
		remove_action( 'admin_init', array( $wpdr->admin, 'filter_from_media' ) );
		remove_filter( 'ajax_query_attachments_args', array( $wpdr->admin, 'filter_from_media_grid' ) );
	}

	/**
	 * Set up Settings on admin_init.
	 *
	 * @since 0.5
	 * @global $wpdr_pil
	 * @return void
	 */
	public function admin_init() {
		// sort the permalink for translated pages.
		add_filter( 'document_home_url', array( &$this, 'set_wpml_home_url' ), 10, 3 );

		// after post save.
		add_action( 'save_post_document', array( &$this, 'save_post_document' ), 99 );

		// after translation post save.
		add_action( 'wpml_after_save_post', array( &$this, 'translation_saved' ), 10, 4 );

		// after translation action.
		add_action( 'wpml_translation_update', array( &$this, 'translation_update' ), 20 );

		// delete post. Make sure for shared attachment documents the original is deleted last.
		add_action( 'pre_delete_post', array( &$this, 'pre_delete_post' ), 5, 3 );

		// delete post. Make sure translated attachments without parents are deleted (but not files).
		add_action( 'before_delete_post', array( &$this, 'before_delete_post' ), 5, 2 );

		// support languages.
		load_plugin_textdomain( 'wpdr-wpml-support', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// help and messages.
		add_action( 'admin_head', array( $this, 'add_help_tab' ) );

		// Add a modified WPDR filters to block Document attachments.
		add_action( 'admin_init', array( &$this, 'filter_from_media' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_from_media_grid' ) );

		global $wpdr_pil;
		// process any outstanding transactions.
		$queues = $wpdr_pil->extract_items();
		foreach ( $queues as $queue ) {
			$this->process_item( $queue );
		}

		// review of translated documents.
		$this->review_translations();

		// do we need to block upload here.
		$this->review_metabox();
	}

	/*
			FUNCTIONS ADDED FOR REMOVING ZERO PARENT TRANSLATIONS FROM MEDIA QUERIES.
			=========================================================================
	*/

	/**
	 * Filters documents from media galleries.
	 *
	 * @uses filter_media_where()
	 * @uses filter_media_join()
	 */
	public function filter_from_media() {
		global $pagenow;

		// verify the page.
		if ( 'upload.php' !== $pagenow && 'media-upload.php' !== $pagenow ) {
			return;
		}

		// note: hook late so that unattached filter can hook in, if necessary.
		add_filter( 'posts_join_paged', array( &$this, 'filter_media_join' ) );
		add_filter( 'posts_where_paged', array( &$this, 'filter_media_where' ), 20 );
	}

	/**
	 * Filters documents from the media grid view when queried via Ajax. This uses
	 * the same filters from the list view applied in `filter_from_media()`.
	 *
	 * @param Object $query the WP_Query object.
	 * @return mixed
	 */
	public function filter_from_media_grid( $query ) {
		// note: hook late so that unattached filter can hook in, if necessary.
		add_filter( 'posts_join_paged', array( $this, 'filter_media_join' ) );
		add_filter( 'posts_where_paged', array( $this, 'filter_media_where' ), 20 );

		return $query;
	}

	/**
	 * Joins wp_posts on itself so posts can be filter by post_parent's type.
	 *
	 * @param string $join the original join statement.
	 * @return string the modified join statement
	 */
	public function filter_media_join( $join ) {
		global $wpdb;

		$join .= " LEFT OUTER JOIN $wpdb->posts wpdr_post_parent ON wpdr_post_parent.ID = $wpdb->posts.post_parent";

		return $join;
	}

	/**
	 * Exclude children of documents from query.
	 *
	 * WPML can have copy attachments (waiting to be attached to a translated document record).
	 * Need to be removed from Media queries.
	 *
	 * @param string $where the original where statement.
	 * @return string the modified where statement
	 */
	public function filter_media_where( $where ) {
		global $wpdb;

		$where .= ' AND ( wpdr_post_parent.post_type IS NULL OR' .
			" wpdr_post_parent.post_type != 'document' OR" .
			" ( wp_posts.post_parent = 0 AND wp_posts.post_type = 'attachment' AND NOT EXISTS " .
			" ( SELECT NULL FROM {$wpdb->prefix}icl_translations a" .
			" JOIN {$wpdb->prefix}icl_translations b ON b.trid = a.trid AND b.element_id != a.element_id" .
			" JOIN $wpdb->posts t ON t.ID = b.element_id" .
			" JOIN {$wpdb->prefix}icl_translations c ON c.element_id = t.post_parent AND c.element_type = 'post_document'" .
			" WHERE a.element_id = wp_posts.ID AND a.element_type = 'post_attachment' )	) )";

		return $where;
	}

	/*
					FUNCTIONS ADDED FOR PROCESSING CHANGES.
					=======================================
	*/

	/**
	 * Filters the home_url() for WPML and translated documents.
	 *
	 * @since 0.5
	 * @param string  $home_url generated permalink.
	 * @param WP_Post $document document object.
	 */
	public function set_wpml_home_url( $home_url, $document ) {
		$current_language = apply_filters( 'wpml_current_language', null );

		if ( 'all' === $current_language ) {
			$args               = array(
				'element_id'   => $document->ID,
				'element_type' => $document->post_type,
			);
			$post_language_code = apply_filters( 'wpml_element_language_code', null, $args );

			do_action( 'wpml_switch_language', $post_language_code );
			$home_url = apply_filters( 'wpml_home_url', $home_url );
			do_action( 'wpml_switch_language', $current_language );
		}

		return $home_url;
	}

	/**
	 * Try to process an outstanding item.
	 *
	 * @since 0.5
	 * @global $wpdr_pil, $wpdb
	 * @param string [] $queue An item to be processed..
	 */
	public function process_item( $queue ) {
		global $wpdr_pil, $wpdb;
		$processed = false;
		// take a lock.
		$number = $queue['number'];
		if ( $wpdr_pil->lock_item( $number ) ) {
			$data = $queue['data'];
			// decode data.
			$this->check_fix_document( $tran );
		}
	}

	/**
	 * Save Document, check/modify linkages.
	 *
	 * @since 0.5
	 * @global $wpdb, $wpdr
	 * @param int  $doc_id the ID of the post being saved.
	 * @param int  $attach the ID of the attachment post if known.
	 * @param bool $sql    whether to update via sql (i.e. avoid revision).
	 * @return void
	 */
	public function check_fix_document( $doc_id, $attach = null, $sql = true ) {
		// autosave check.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// check permissions.
		if ( is_null( $doc_id ) || ! current_user_can( 'edit_document', $doc_id ) ) {
			return;
		}

		global $wpdr;
		// find the latest attachment.
		if ( is_null( $attach ) ) {
			// link to the latest document attachment.
			$latest = $wpdr->admin->get_latest_attachment( $doc_id );
			if ( empty( $latest ) ) {
				$attach = 0;
			} else {
				$attach = $latest->ID;
			}
		}

		// what is attached post.
		$document = get_post( $doc_id );
		$content  = $wpdr->extract_document_id( $document->post_content );
		if ( is_numeric( $content ) ) {
			// if attachment is given. then can be tested directly.
			if ( $content === $attach ) {
				return;
			}
		}

		$content = $wpdr->format_doc_id( $attach ) . preg_replace( '/<!-- WPDR \s*\d+ -->/', '', $document->post_content );

		if ( $sql ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$post_table = "{$wpdb->prefix}posts";
			$sql        = $wpdb->prepare(
				"UPDATE `$post_table` SET `post_content` = %s WHERE `id` = %d ",
				$content,
				$doc_id,
			);
			$res        = $wpdb->query( $sql );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			wp_cache_delete( $doc_id, 'posts' );
			clean_post_cache( $doc_id );
		} else {
			// create revision.
			$update = array(
				'ID'           => $doc_id,
				'post_content' => $content,
			);
			wp_update_post( $update );
		}
	}

	/**
	 * Translation Save Document, check/modify linkages.
	 *
	 * @since 0.5
	 * @global $wpdb, $wpdr
	 * @param int    $doc_id          the ID of the post being saved.
	 * @param int    $trid            the wpml translation ID of the post.
	 * @param string $language_code   the language of the post.
	 * @param string $source_language the source language being translated.
	 * @return void
	 */
	public function translation_saved( $doc_id, $trid, $language_code, $source_language ) {
		if ( is_null( $source_language ) || $language_code === $source_language ) {
			return;
		}
		global $wpdb, $wpdr;
		if ( ! $wpdr->verify_post_type( $doc_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s ",
				$trid,
				$language_code
			),
		);
		$this->check_fix_document( $post_id );
	}

	/**
	 * Translation update..
	 *
	 * @since 0.5
	 * @global $wpdb, $wpdr
	 * @param mixed[] $parms Update parameters.
	 * @return void
	 */
	public function translation_update( $parms ) {
		// only interested in posts.
		if ( ! isset( $parms['context'] ) || 'post' !== $parms['context'] ) {
			return;
		}

		// set element_type if not present.
		if ( ! isset( $parms['element_type'] ) && isset( $parms['element_id'] ) ) {
			$id                    = get_post( $parms['element_id'] );
			$parms['element_type'] = 'post_' . $id->post_type;
		}

		// only interested in post_type of document or attachment.
		if ( ! in_array( $parms['element_type'], array( 'post_document', 'post_attachment' ), true ) ) {
			return;
		}

		// need an element_id.
		if ( ! array_key_exists( 'element_id', $parms ) ) {
			return;
		}

		global $wpdb, $wpdr;
		if ( empty( $parms['element_id'] ) ) {
			// can we get it from translation_id.
			if ( isset( $parms['translation_id'] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$post_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d ",
						$parms['translation_id']
					),
				);
				if ( ! $post_id ) {
					// transaction record not found, write details for later processing.
					$data = array(
						'No Translation Record',
						$parms['type'],
						$parms['element_type'],
						$parms['translation_id'],
					);
					$data = implode( '/', $data );
					global $wpdr_pil;
					$wpdr_pil->set_item( $data );
					return;
				}
				// found it.
				$parms['element_id'] = $post_id;
			} else {
				global $post;
				if ( ! is_object( $post ) || is_null( $post ) || ! isset( $post->ID ) ) {
					return;
				}
				$parms['element_id'] = $post->ID;
			}
		}

		$type = $parms['type'];
		$eltp = $parms['element_type'];
		$elid = $parms['element_id'];
		switch ( true ) {
			case ( 'post_document' === $eltp && 'insert' === $type ):
				// find if it is a translation.
				$orig = $this->get_original_translation( $elid );
				if ( $orig !== $elid && ! $this->post_in_shared_mode( $elid ) ) {
					// Translated post, remove link in original content.
					$id      = get_post( $elid );
					$content = preg_replace( '/<!-- WPDR \s*\d+ -->/', '', $id->post_content );
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
					$sql = $wpdb->prepare(
						"UPDATE `{$wpdb->prefix}posts` SET `post_content` = %s WHERE `id` = %d ",
						$content,
						$elid
					);
					$res = $wpdb->query( $sql );
					// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
					wp_cache_delete( $elid, 'posts' );
					break;
				}
				$this->check_fix_document( $elid );
				break;

			case ( 'post_document' === $eltp && 'update' === $type ):
				// we have a pointer to a document {but probably belt and braces}.
				$this->check_fix_document( $elid );
				break;

			case ( 'post_document' === $eltp && in_array( $type, array( 'insert', 'update' ), true ) ):
				// got to go looking for the document id.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$trans = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND source_language_code IS NOT NULL",
						$parms['trid'],
					),
					ARRAY_A,
				);
				foreach ( $trans as $tran ) {
					$this->check_fix_document( $tran['element_id'] );
				}
				break;

			case ( 'post_attachment' === $eltp && 'insert' === $type ):
				// got to go looking for whether a content update is needed.
				$attach = get_post( $elid );
				$parent = $attach->post_parent;
				global $wpdr;
				if ( 0 === $parent || ! $wpdr->verify_post_type( $parent ) ) {
					// not a document or not yet linked.
					return;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$recs   = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_id != %d ",
						$parms['trid'],
						$elid,
					),
				);
				$shared = $this->post_in_shared_mode( $parent );
				if ( $recs > 0 && ! $shared ) {
					// remove parent link.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$res  = $wpdb->query(
						$wpdb->prepare(
							"UPDATE `{$wpdb->prefix}posts` SET `post_parent` = 0 WHERE `id` = %d ",
							$elid,
						)
					);
					$elid = null;
				}
				// create a revision record if shared.
				$this->check_fix_document( $parent, $elid, ( ! $shared ) );
				break;

			case ( 'post_attachment' === $eltp && 'update' === $type ):
				break;

			case ( 'post_attachment' === $eltp && 'before_delete' === $type ):
				// got to go looking for whether it can be deleted.
				$attach = get_post( $elid );
				$parent = $attach->post_parent;
				if ( 0 === $parent || ! $wpdr->verify_post_type( $parent ) ) {
					// not a document or not yet linked.
					// note. Don't expect to be here with an unlinked attachment.
					return;
				}

				global $wpdb;
				// is the document in shared mode.
				$orig = $this->get_original_translation( $parent );
				if ( $this->post_in_shared_mode( $orig ) ) {
					// are there other posts sharing the attachment. if so, don't delete any other attachment.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$multi = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations " .
							'WHERE element_id != %d AND ' .
							"trid = ( SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d ) " .
							'LIMIT 1',
							$parent,
							$parent,
						),
					);
					if ( ! $multi ) {
						return;
					}
				}

				// delete any unlinked copy attachment.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$trans = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT element_id FROM {$wpdb->prefix}icl_translations " .
						"INNER JOIN {$wpdb->prefix}posts ON ID = element_id " .
						'WHERE element_id != %d AND post_parent = 0 AND ' .
						"trid = ( SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d ) ",
						$elid,
						$elid,
					),
					ARRAY_A,
				);

				// delete attachment will delete the files but we don't want this process to do that.
				// so mangle the file directory.
				add_filter( 'upload_dir', array( $this, 'mangle_upload_dir' ), 1000, 1 );
				foreach ( $trans as $tran ) {
					wp_delete_attachment( $tran['element_id'] );
				}
				remove_filter( 'upload_dir', array( $this, 'mangle_upload_dir' ), 1000, 1 );
				break;
		}
	}

	/**
	 * Mangle the document directory so that the files are not deleted (by this process).
	 *
	 * Delete zero parent attachments where we are about to delete the actual file.
	 *
	 * @since 0.5
	 * @global $wpdr_pil
	 * @param false|null $check        Whether to go forward with deletion.
	 * @param WP_Post    $post         Post object.
	 * @param bool       $force_delete Whether to bypass the Trash.
	 * @return false|null
	 */
	public function pre_delete_post( $check, $post, $force_delete ) { // phpcs:ignore
		// only documents.
		if ( 'document' !== $post->post_type ) {
			return $check;
		}

		// Only concerned with original post in shared mode.
		$orig = $this->get_original_translation( $post->ID );
		if ( $orig !== $post->ID || ! $this->post_in_shared_mode( $orig ) ) {
			return $check;
		}

		global $wpdr_pil;
		// Are there any outstanding transactions to process.
		$queues = $wpdr_pil->extract_items();
		if ( ! empty( $queues ) ) {
			return false;
		}

		// Are there translations still.
		if ( $this->do_translations_exist( $orig ) ) {
			return false;
		}
		return $check;
	}

	/**
	 * Make sure that a shared attachment document has the original one as last deleted.
	 *
	 * @since 0.5
	 * @param int     $postid post ID.
	 * @param WP_Post $post   post object.
	 */
	public function before_delete_post( $postid, $post ) {
		// only documents.
		if ( 'document' !== $post->post_type ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$zeros = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT x.ID FROM {$wpdb->prefix}posts AS p " .
				"INNER JOIN {$wpdb->prefix}icl_translations t ON t.element_type = 'post_attachment' AND t.element_id = p.ID " .
				"INNER JOIN {$wpdb->prefix}icl_translations z ON z.trid = t.trid " .
				"INNER JOIN {$wpdb->prefix}posts x ON x.ID = z.element_id " .
				"WHERE p.post_parent = %d AND p.post_type = 'attachment' AND t.source_language_code IS NULL AND x.post_parent = 0 ",
				$postid,
			),
			ARRAY_A,
		);

		if ( ! is_array( $zeros ) || empty( $zeros ) ) {
			// no zero parent records.
			return;
		}

		// delete attachment will delete the files but we don't want this process to do that.
		// so mangle the file directory.
		add_filter( 'upload_dir', array( $this, 'mangle_upload_dir' ), 1000, 1 );
		foreach ( $zeros as $zero ) {
			wp_delete_attachment( $zero['post_id'] );
		}
		remove_filter( 'upload_dir', array( $this, 'mangle_upload_dir' ), 1000, 1 );
	}

	/**
	 * Mangle the document directory so that the files are not deleted (by this process).
	 *
	 * Called after the original has been deleted..
	 *
	 * @since 0.5
	 * @param array $dir defaults passed from WP.
	 * @return array $dir modified directory
	 */
	public function mangle_upload_dir( $dir ) {
		// Even though this is the standard directory, we need to make it non-existant.
		$dir['basedir'] = $dir['basedir'] . '/notexist';
		$dir['path']    = $dir['basedir'] . $dir['subdir'];
		return $dir;
	}

	/**
	 * Reviews the metabox.
	 *
	 * @since 0.5
	 * @return void
	 */
	public function review_metabox() {
		// find post id.
		$post_id = $this->find_post_id();
		if ( is_null( $post_id ) ) {
			return;
		}

		// is the document not original and not in shared mode.
		$orig = $this->get_original_translation( $post_id );
		if ( $orig !== $post_id ) {
			// block update of media update.
			$script = 'function wpdr_wpml() {' .
				'let attr = document.getElementsByName("wpml_duplicate_media");' .
				'if ( attr != null ) {' .
					'attr[0].setAttribute( "disabled", "true" );' .
				'} ';
			if ( $this->post_in_shared_mode( $orig ) ) {
				// block upload access.
				$script .= 'attr = document.getElementById("content-add_media");' .
					'if ( attr != null ) {' .
						'attr.setAttribute( "style", "pointer-events: none" );' .
						'attr.insertAdjacentHTML( "beforebegin", "' . __( 'Document uploads are synchronized<br/>Use original document post', 'wpdr-wpml-support' ) . '<br/>");' .
					'} ';
			}
			$script .= '} ' .
				'document.addEventListener("DOMContentLoaded", wpdr_wpml() );';
			wp_add_inline_script( 'media-upload', $script );
		} elseif ( $this->do_translations_exist( $post_id ) ) {
			// does a translation exist?
			// block upload access.
			$script = 'function wpdr_wpml() {' .
				'let attr = document.getElementsByName("wpml_duplicate_media");' .
				'if ( attr != null ) {' .
					'attr[0].setAttribute( "disabled", "true" );' .
				'}' .
				'}' .
				'document.addEventListener("DOMContentLoaded", wpdr_wpml() );';
			wp_add_inline_script( 'media-upload', $script );
		}
	}

	/**
	 * Find the post id from globals/input.
	 *
	 * @since 0.5
	 * @global $wpdr, $post
	 * @return int | null
	 */
	private function find_post_id() {
		global $wpdr, $post;
		if ( ! $wpdr->verify_post_type() ) {
			return null;
		}

		if ( ! is_object( $post ) || is_null( $post ) || ! isset( $post->ID ) ) {
			// if post isn't set, try get vars (edit post).
			// else look for post_id via post or get (media upload).
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['post'] ) ) {
				$post_id = intval( $_GET['post'] );
			} elseif ( isset( $_REQUEST['post_id'] ) ) {
				$post_id = intval( $_REQUEST['post_id'] );
			} else {
				return null;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		} else {
			$post_id = $post->ID;
		}
		return $post_id;
	}

	/**
	 * Find the original document.
	 *
	 * @since 0.5
	 * @global $wpdb
	 * @param int $post_id the ID of the post being tested.
	 * @return int
	 */
	private function get_original_translation( $post_id ) {
		// look up WPML data.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tran = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT element_id AS orig FROM {$wpdb->prefix}icl_translations " .
				'WHERE element_id = %d AND source_language_code IS NULL ' .
				'UNION ALL ' . // actually distinct sets, so no need to try to remove duplicates.
				"SELECT o.element_id FROM {$wpdb->prefix}icl_translations AS o " .
				'WHERE o.source_language_code IS NULL ' .
				"AND o.trid = (SELECT m.trid FROM {$wpdb->prefix}icl_translations AS m WHERE m.element_id = %d AND m.source_language_code IS NOT NULL )",
				$post_id,
				$post_id,
			),
			ARRAY_A,
		);

		if ( ! is_array( $tran ) ) {
			// no translation data so it is the original.
			return $post_id;
		}
		return (int) $tran['orig'];
	}

	/**
	 * Find whether the post is in shared document mode.
	 *
	 * @since 0.5
	 * @param int $post_id the ID of the post being tested.
	 * @return bool
	 */
	private function post_in_shared_mode( $post_id ) {
		// find the original transaction.
		$orig = $this->get_original_translation( $post_id );

		// look up metadata.
		$mode = get_post_meta( $orig, '_wpml_media_duplicate', true );
		return (bool) $mode;
	}

	/**
	 * Find if there are translations of original document.
	 *
	 * @since 0.5
	 * @global $wpdb
	 * @param int $post_id the ID of the post being tested.
	 * @return bool
	 */
	private function do_translations_exist( $post_id ) {
		// look up WPML data (join to post to make sure post not deleted).
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tran = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT element_id, source_language_code, IFNULL(source_language_code,'null') as lang, (SELECT ID FROM {$wpdb->prefix}posts as p where p.ID = o.element_id) as ID FROM {$wpdb->prefix}icl_translations AS o " .
				"WHERE o.trid = (SELECT m.trid FROM {$wpdb->prefix}icl_translations AS m WHERE m.element_id = %d )",
				$post_id,
			),
			ARRAY_A,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tran = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(source_language_code) FROM {$wpdb->prefix}icl_translations AS o " .
				"WHERE o.trid = (SELECT m.trid FROM {$wpdb->prefix}icl_translations AS m WHERE m.element_id = %d )" .
				"AND EXISTS (SELECT NULL FROM {$wpdb->prefix}posts AS p WHERE p.ID = o.element_id) ",
				$post_id,
			),
		);

		if ( 0 === (int) $tran ) {
			// no translation data.
			return false;
		}
		return true;
	}

	/**
	 * Find translations of the original document.
	 *
	 * @since 0.5
	 * @global $wpdb
	 * @param int $orig_id the ID of the post being tested.
	 * @return int[]
	 */
	private function get_orig_translations( $orig_id ) {
		// look up WPML data.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tran = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.language_code, o.element_id FROM {$wpdb->prefix}icl_translations AS o " .
				'WHERE o.source_language_code IS NOT NULL ' .
				"AND o.trid = (SELECT m.trid FROM {$wpdb->prefix}icl_translations AS m WHERE m.element_id = %d AND m.source_language_code IS NULL )",
				$orig_id,
			),
			ARRAY_A,
		);

		if ( ! is_array( $tran ) ) {
			// no translation data so it is the original.
			return array();
		}
		return wp_list_pluck( $tran, 'element_id', 'language_code' );
	}

	/*
					FUNCTIONS ADDED FOR HELP TEXT DEBUG PROCESSING.
					===============================================
	*/

	/**
	 * Adds help tabs to help tab API.
	 *
	 * @since 0.5
	 * @uses get_help_text()
	 * @return void
	 */
	public function add_help_tab() {
		$screen = get_current_screen();

		// only interested in document post_types.
		if ( 'document' !== $screen->post_type ) {
			return;
		}

		// loop through each tab in the help array and add.
		foreach ( $this->get_help_text( $screen ) as $title => $content ) {
			$screen->add_help_tab(
				array(
					'title'   => $title,
					'id'      => str_replace( ' ', '_', $title ),
					'content' => $content,
				)
			);
		}
	}

	/**
	 * Helper function to provide help text as an array.
	 *
.5
	 * @global $wpdb
	 * @param int $orig_id the ID of the post being tested.
	 * @return int[]
	 */
	private function get_orig_translations( $orig_id ) {
		// look up WPML data.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tran = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.language_code, o.element_id FROM {$wpdb->prefix}icl_translations AS o " .
				'WHERE o.source_language_code IS NOT NULL ' .
				"AND o.trid = (SELECT m.trid FROM {$wpdb->prefix}icl_translations AS m WHERE m.element_id = %d AND m.source_language_code IS NULL )",
				$orig_id,
			),
			ARRAY_A,
		);

		if ( ! is_array( $tran ) ) {
			// no translation data so it is the original.
			return array();
		}
		return wp_list_pluck( $tran, 'element_id', 'language_code' );
	}

	/*
					FUNCTIONS ADDED FOR HELP TEXT DEBUG PROCESSING.
					===============================================
	*/

	/**
	 * Adds help tabs to help tab API.
	 *
	 * @since 0.5
	 * @uses get_help_text()
	 * @return void
	 */
	public function add_help_tab() {
		$screen = get_current_screen();

		// only interested in document post_types.
		if ( 'document' !== $screen->post_type ) {
			return;
		}

		// loop through each tab in the help array and add.
		foreach ( $this->get_help_text( $screen ) as $title => $content ) {
			$screen->add_help_tab(
				array(
					'title'   => $title,
					'id'      => str_replace( ' ', '_', $title ),
					'content' => $content,
				)
			);
		}
	}

	/**
	 * Helper function to provide help text as an array.
	 *
                       	 * @since 0.5
	 * @param WP_Screen $screen (optional) the current screen.
	 * @returns string[] the help text
	 */
	public function get_help_text( $screen = null ) {
		if ( is_null( $screen ) ) {
			$screen = get_current_screen();
		}

		$post_id = $this->find_post_id();
		if ( is_null( $post_id ) ) {
			return array();
		}

		$post = get_post( $post_id );
		// parent key is the id of the current screen
		// child key is the title of the tab
		// value is the help text (as HTML).
		$help = array(
			'document' => array(
				__( 'WPML Calls', 'wpdr-wpml-support' ) => $this->get_wpml_data( $post ),
				__( 'WPDR Data', 'wpdr-wpml-support' )  => $this->get_document_data( $post ),
			),
		);

		// if we don't have any help text for this screen, just kick.
		if ( ! isset( $help[ $screen->id ] ) ) {
			return array();
		}

		/**
		 * Filters the default help text for current screen.
		 *
		 * @param string[]  $help   default help text for current screen.
		 * @param WP_Screen $screen current screen name.
		 */
		return apply_filters( 'wpdr_wpml_help_array', $help[ $screen->id ], $screen );
	}

	/**
	 * Function to retrieve/display WPML data for post.
	 *
	 * @since 0.5
	 * @param WP_Post $post Post for WPML Calls.
	 * @returns string
	 */
	private function get_wpml_data( $post ) {
		if ( ! is_object( $post ) || 'document' !== $post->post_type ) {
			return '';
		}

		$output =
		'<div style="line-height:1.0;"><table class="form-table" style="clear:none;">' .
		'<tr>' .
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-doc">' . __( 'Document ID', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $post->ID . '</td></tr>';

		$orig   = $this->get_original_translation( $post->ID );
		$master = apply_filters( 'wpml_master_post_from_duplicate', $post->ID );

		$output .=
		'<tr>' .
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-master">' . __( 'Master', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' .
		$orig . '  [Uses translation table]<br />' .
		$master . "  [Uses apply_filters( 'wpml_master_post_from_duplicate', $post->ID )]" . '</td></tr>';

		$trans = $this->get_orig_translations( $orig );
		if ( empty( $trans ) ) {
			$list_a = __( 'No translations', 'wpdr-wpml-support' );
		} else {
			$list_a = '';
			foreach ( $trans as $lang => $tran ) {
				$list_a .= $lang . ': ' . $tran . ',';
			}
			$list_a = substr( $list_a, 0, -1 );
		}

		$trans = apply_filters( 'wpml_post_duplicates', $orig );
		if ( empty( $trans ) ) {
			$list_b = __( 'No translations', 'wpdr-wpml-support' );
		} else {
			$list_b = '';
			foreach ( $trans as $lang => $tran ) {
				$list_b .= $lang . ': ' . $tran . ',';
			}
			$list_b = substr( $list_b, 0, -1 );
		}

		$output .=
		'<tr>' .
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-trans">' . __( 'Translated Posts', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $list_a . '<br />' . $list_b .
		'<br />' . "  [Uses translation table / apply_filters( 'wpml_post_duplicates', $orig )]" . '</td></tr>';

		$share = ( $this->post_in_shared_mode( $post->ID ) ? __( 'Documents Shared', 'wpdr-wpml-support' ) : __( 'Documents Unique', 'wpdr-wpml-support' ) );

		$output .=
		'<tr>' .
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-share">' . __( 'Share Mode', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $share . '</td></tr>';

		$output .= '</table></div>';
		return $output;
	}

	/**
	 * Function to retrieve/display Document data for post.
	 *
	 * @since 0.5
	 * @global $wpdb, $wpdr
	 * @param WP_Post $post Post for WPML Calls.
	 * @returns string
	 */
	private function get_document_data( $post ) {
		global $wpdb, $wpdr;
		if ( ! is_object( $post ) || 'document' !== $post->post_type ) {
			return '';
		}

		$output =
		'<div style="line-height:1.0;"><table class="form-table" style="clear:none;">' .
		'<tr>' .
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-post">' . __( 'Post ID', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $post->ID . '</td></tr>';

		$output .=
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-content">' . __( 'Content', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . esc_html( $post->post_content ) . '</td></tr>';

		$revns = get_document_revisions( $post->ID );
		// remove the document itself.
		unset( $revns[0] );
		if ( empty( $revns ) ) {
			$list = __( 'No revisions', 'wpdr-wpml-support' );
		} else {
			$list = '';
			foreach ( $revns as $revn ) {
				$rev_doc = $wpdr->extract_document_id( $revn->post_content );
				$list   .= $revn->ID . '&nbsp;&nbsp;' . __( 'Attachment Document: ', 'wpdr-wpml-support' ) . $rev_doc . '<br />';
			}
			$list = substr( $list, 0, -6 );
		}

		$output .=
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-revisions">' . __( 'Revisions', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $list . '</td></tr>';

		$attachs = $wpdr->get_attachments( $post->ID );
		if ( empty( $attachs ) ) {
			$list = __( 'No attachments', 'wpdr-wpml-support' );
		} else {
			$list = '';
			foreach ( $attachs as $attach ) {
				// get any copies.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$copies = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT CONCAT( p.ID, ' (', p.post_parent, ')' ) AS col FROM {$wpdb->posts} as p " .
						"INNER JOIN {$wpdb->prefix}icl_translations AS o ON p.ID = o.element_id " .
						"WHERE ID != %d AND o.trid = (SELECT m.trid FROM {$wpdb->prefix}icl_translations AS m WHERE m.element_id = %d )",
						$attach->ID,
						$attach->ID,
					),
					ARRAY_A,
				);
				if ( ! is_array( $copies ) || empty( $copies ) ) {
					$sublist = __( 'No copies', 'wpdr-wpml-support' );
				} else {
					$sublist = implode( ', ', array_column( $copies, 'col' ) );
				}
				$meta = $this->get_meta_data( $attach );
				if ( ! empty( $meta ) ) {
					$meta = '<br />' . $meta;
				}
				$list .= $attach->ID . '&nbsp;&nbsp;' . __( 'Copies (with parent): ', 'wpdr-wpml-support' ) . $sublist . $meta . '<br />';
			}
			$list = substr( $list, 0, -6 );
		}

		$output .=
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-attach">' . __( 'Attachments', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $list . '</td></tr>';

		$output .=
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-metadata">' . __( 'Meta data', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $this->get_meta_data( $post ) . '</td></tr>';

		$taxes = get_object_taxonomies( $post->post_type, 'objects' );
		$list  = '';
		foreach ( $taxes as $key => $tax ) {
			$list     .= '<strong>' . $tax->labels->name . ':</strong>&nbsp;';
			$tax_terms = get_the_terms( $post, $tax->name );
			if ( ! empty( $tax_terms ) ) {
				foreach ( $tax_terms as $term ) {
					$list .= $term->name . ', ';
				}
				$list = substr( $list, 0, -2 );
			}
			$list .= '<br />';
		}
		$list = substr( $list, 0, -6 );

		$output .=
		'<th scope="row" style="line-height:1.0; padding:5px 10px;"><label for="labels-terms">' . __( 'Terms', 'wpdr-wpml-support' ) . '</label></th>' .
		'<td style="line-height:1.0; padding:5px 10px;margin-bottom:0;">' . $list . '</td></tr>';

		$output .= '</table></div>';
		return $output;
	}

	/**
	 * Function to retrieve/display meta data for post.
	 *
	 * @since 0.5
	 * @param WP_Post $post Post for meta data.
	 * @returns string
	 */
	private function get_meta_data( $post ) {
		$metas = get_post_meta( $post->ID );
		if ( ! $metas ) {
			return '';
		}
		$lead = '';
		// reduce _wp_attachment_metadata.
		if ( isset( $metas['_wp_attachment_metadata'] ) ) {
			$meta   = $metas['_wp_attachment_metadata'][0];
			$redact = '..redacted..';
			if ( strpos( $meta, 'wpdr_hidden' ) ) {
				$redact .= ' (hidden)';
			}
			$metas['_wp_attachment_metadata'] = array( $redact );
			$lead                             = '&nbsp;&nbsp;';
		}
		ksort( $metas );
		$list = '';
		foreach ( $metas as $key => $meta ) {
			if ( 1 === count( $meta ) ) {
				$list .= $lead . $key . '&nbsp;&nbsp;' . wp_kses_post( $meta[0] ) . '<br />';
				unset( $metas[ $key ] );
			}
		}
		if ( empty( $metas ) ) {
			$list = substr( $list, 0, -6 );
		} else {
			// Have processed the single value meta items, so shouldn't be any left.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$list .= nl2br( wp_kses_post( print_r( $metas, true ) ) );
		}
		return $list;
	}
}

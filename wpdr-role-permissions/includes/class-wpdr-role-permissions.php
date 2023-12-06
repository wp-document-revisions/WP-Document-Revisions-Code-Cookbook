<?php
/**
 * Main class for WP Document Revisions Role Permissions.
 *
 * @since 3.0.0
 * @package WP_Document_Revisions
 */

/**
 * Main WPDR_Role_Permissions class.
 */
class WPDR_Role_Permissions {
	/**
	 * Whether to bypass filter functions.
	 *
	 * @var bool $bypass
	 */
	private static $bypass = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Determine whether to add functionality.
		add_action( 'init', array( &$this, 'init' ) );
	}

	/**
	 * Add hooks to WP API
	 */
	public function init() {
		// Detect if WPDR and Members are active. If not found then bail with message.
		if ( class_exists( 'WP_Document_Revisions' ) && function_exists( 'members_can_user_view_post' ) ) {
			// WPDR and Members Plugins are activated.
			// register with necessary capabilitiy APIs.
			add_filter( 'map_meta_cap', array( &$this, 'map_meta_cap' ), 15, 4 );
			add_filter( 'user_has_cap', array( &$this, 'user_has_cap' ), 15, 4 );

			// belt and braces.
			add_filter( 'posts_results', array( &$this, 'posts_results' ), 30, 2 );
		}
	}

	/**
	 * Checks that WP Document Revisisons is activated when this plugin is activated
	 * If so, toggle flag to add initial capabilities
	 */
	public function activation() {
		if ( ! class_exists( 'WP_Document_Revisions' ) ) {
			wp_die( esc_html__( 'WP Document Revisions must be activated to use Role Permissions', 'wp-document-revisions' ) );
		}

		if ( ! function_exists( 'members_can_user_view_post' ) ) {
			wp_die( esc_html__( 'Members must be activated to use Role Permissions', 'wp-document-revisions' ) );
		}
	}

	/**
	 * Maps caps from e.g., `edit_document` to `edit_document_in_accounting`
	 *
	 * @param array   $caps    Array of the user's capabilities.
	 * @param string  $cap     Capability name.
	 * @param integer $user_id The user ID.
	 * @param array   $args    Adds the context to the cap. Typically the object ID.
	 * @return array  $caps    Array of the user's capabilities.
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( self::$bypass ) {
			return $caps;
		}
		// array of primitive caps that all document caps are based on.
		$primitive_caps = array( 'read_post', 'edit_post', 'delete_post', 'publish_post', 'override_document_lock' );

		// current cap being checked is not a post-specific cap, kick to save effort.
		if ( ! in_array( $cap, $primitive_caps, true ) ) {
			return $caps;
		}

		// attempt to grab the post_ID.
		// note: will default to global $post if none passed.
		$post_ID = ( ! empty( $args ) ) ? $args[0] : null;

		global $wpdr;

		// cap being checked is not related to a document, kick.
		if ( ! $wpdr->verify_post_type( $post_ID ) ) {
			return $caps;
		}

		// members function includes user_can that will use map_meta_cap and user_has_cap. Stop recursion.
		self::$bypass = true;

		if ( ! members_can_user_view_post( $user_id, $post_ID ) ) {
			// cannot access document.
			$caps[] = 'do_not_allow';
		}
		// reinstate filter function.
		self::$bypass = false;

		return $caps;
	}

	/**
	 * Dynamically filter a user's capabilities.
	 *
	 * @param bool[]   $allcaps Array of key/value pairs where keys represent a capability name and boolean values
	 *                          represent whether the user has that capability.
	 * @param string[] $caps    Required primitive capabilities for the requested capability.
	 * @param array    $args {
	 *     Arguments that accompany the requested capability check.
	 *
	 *     @type string    $0 Requested capability.
	 *     @type int       $1 Concerned user ID.
	 *     @type mixed  ...$2 Optional second and further parameters, typically object ID.
	 * }
	 * @param WP_User  $user    The user object.
	 * @return bool[]  $allcaps Array of key/value pairs.
	 */
	public function user_has_cap( $allcaps, $caps, $args, $user ) {
		if ( self::$bypass ) {
			return $allcaps;
		}
		// Is user an administrator. If so, bail.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return $allcaps;
		}

		// must have a base object.
		if ( ! isset( $args[2] ) ) {
			return $allcaps;
		}

		$post_ID = $args[2];

		global $wpdr;

		// cap being checked is not related to a document, kick.
		if ( ! $wpdr->verify_post_type( $post_ID ) ) {
			return $allcaps;
		}

		// members function includes user_can that will use map_meta_cap and user_has_cap. Stop recursion.
		self::$bypass = true;

		if ( ! members_can_user_view_post( $user->ID, $post_ID ) ) {
			// cannot access document.
			$allcaps[] = 'do_not_allow';
		}
		// reinstate filter function.
		self::$bypass = false;

		return $allcaps;
	}

	/**
	 * Review WP_Query SQL results.
	 *
	 * @param WP_Post[] $results Array of post objects.
	 * @param WP_Query  $query   Query object.
	 * @return WP_Post[] Array of post objects.
	 */
	public function posts_results( $results, $query ) {
		// not for administrator.
		$user = wp_get_current_user();
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return $results;
		}

		// check that a post_type query involves documents.
		if ( isset( $query->query['post_type'] ) ) {
			$type = (array) $query->query['post_type'];
			if ( ! in_array( 'document', $type, true ) ) {
				// query not including documents.
				return $results;
			}
		}

		global $wpdr;

		// review documents, removing those not accessible.
		$match = false;
		foreach ( $results as $key => $result ) {
			// confirm a document.
			if ( ! in_array( $result->post_type, array( 'document', 'revision' ), true ) ) {
				continue;
			}
			if ( ! $wpdr->verify_post_type( $result ) ) {
				continue;
			}

			// Only check documents.
			if ( ! members_can_user_view_post( $user->ID, $result->ID ) ) {
				// not allowed, so remove.
				unset( $results[ $key ] );
				$match = true;
			}
		}

		// re-evaluate count.
		if ( $match ) {
			// reindex array.
			$results = array_values( $results );

			if ( is_array( $results ) ) {
				$query->found_posts = count( $results );
			} elseif ( null === $results ) {
				$query->found_posts = 0;
			} else {
				$query->found_posts = 1;
			}
		}
		return $results;
	}
}

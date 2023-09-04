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
	 * Add hooks to WP API
	 */
	public function __construct() {

		// register with necessary capabilitiy APIs.
		add_filter( 'map_meta_cap', array( &$this, 'map_meta_cap' ), 15, 4 );
		add_filter( 'user_has_cap', array( &$this, 'user_has_cap' ), 14, 4 );

		// belt and braces.
		add_filter( 'posts_results', array( &$this, 'posts_results' ), 30, 2 );
	}

	/**
	 * Checks that WP Document Revisisons is activated when this plugin is activated
	 * If so, toggle flag to add initial capabilities
	 */
	public function activation() {
		if ( ! class_exists( 'WP_Document_Revisions' ) ) {
			wp_die( esc_html__( 'WP Document Revisions must be activated to use Role Permissions', 'wp-document-revisions' ) );
		}

		if ( ! function_exists( 'members_get_post_roles' ) ) {
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
		// attempt to grab the post_ID.
		// note: will default to global $post if none passed.
		$post_ID = ( ! empty( $args ) ) ? $args[0] : null;

		// array of primitive caps that all document caps are based on.
		$primitive_caps = array( 'read_post', 'edit_post', 'delete_post', 'publish_post', 'override_document_lock' );

		// current cap being checked is not a post-specific cap, kick to save effort.
		if ( ! in_array( $cap, $primitive_caps, true ) ) {
			return $caps;
		}

		global $wpdr;

		// cap being checked is not related to a document, kick.
		if ( ! $wpdr->verify_post_type( $post_ID ) ) {
			return $caps;
		}

		// get user object.
		$user = get_user_by( 'id', $user_id );

		if ( ! $this->user_can_access( $user, $post_ID ) ) {
			// cannot access document.
			$caps[] = 'do_not_allow';
		}

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

		if ( ! $this->user_can_access( $user, $post_ID ) ) {
			// cannot access document.
			$allcaps[] = 'do_not_allow';
		}

		return $allcaps;
	}

	/**
	 * Determine if the post can be accessed.
	 *
	 * @param WP_User $user    The user object.
	 * @param int     $post_ID The post object.
	 * @return bool   Whether the post is accessible.
	 */
	private function user_can_access( $user, $post_ID = null ) {
		// Is user an administrator. If so, bail.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return true;
		}

		// must have a base object.
		if ( is_null( $post_ID ) ) {
			return true;
		}

		// we have already checked that it is a document.

		// does the user have access to the document.
		// Get the roles selected by the user.
		$roles = members_get_post_roles( $post_ID );

		// Check if there are any old roles with the '_role' meta key.
		if ( empty( $roles ) ) {
			$roles = members_convert_old_post_meta( $post_ID );
		}

		// If we have an array of roles, let's get to work.
		if ( ! empty( $roles ) && is_array( $roles ) ) {
			// get post.
			$post = get_post( $post_ID );

			// Since specific roles were given, let's assume the user can't view
			// the post at this point.  The rest of this functionality should try
			// to disprove this.
			$can_view = false;

			// If viewing a feed or if the user's not logged in, assume it's blocked at this point.
			if ( is_feed() || ! is_user_logged_in() ) {
				$can_view = false;
			} elseif ( $post->post_author === $user->ID || user_can( $user, 'restrict_content' ) ) {
				// If the post author, or the current user can 'restrict_content', return true.
				$can_view = true;
			} else {
				// Else, let's check the user's role against the selected roles.

				// Loop through each role and set $can_view to true if the user has one of the roles.
				foreach ( $roles as $role ) {
					if ( members_user_has_role( $user->ID, $role ) ) {
						$can_view = true;
						break;
					}
				}
			}
			return $can_view;
		}
		return true;
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

		if ( isset( $query->query['post_type'] ) ) {
			$type = $query->query['post_type'];
			if ( ! is_array( $type ) && 'document' === $type ) {
				// single type query not for documents.
				return $results;
			}
			if ( is_array( $type ) && ! in_array( 'document', $type, true ) ) {
				// multiple type query not including documents.
				return $results;
			}
		}

		global $wpdr;

		// review documents, removing those not accessible.
		$match = false;
		foreach ( $results as $key => $result ) {
			// confirm a document.
			if ( ! $wpdr->verify_post_type( $result ) ) {
				continue;
			}

			if ( ! $this->user_can_access( $user, $result->ID ) ) {
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
			} else {
				if ( null === $results ) {
					$query->found_posts = 0;
				} else {
					$query->found_posts = 1;
				}
			}
		}
		return $results;
	}
}

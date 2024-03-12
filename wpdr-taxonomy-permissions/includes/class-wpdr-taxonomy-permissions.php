<?php
/**
 * Main class for WP Document Revisions Taxonomy Permissions.
 *
 * @since 3.0.0
 * @package WP_Document_Revisions
 */

/**
 * Main WPDR_Taxonomy_Permissions class.
 */
class WPDR_Taxonomy_Permissions {
	/**
	 * Taxonomy to use to base permissions
	 *
	 * @var $taxonomy taxonomy name used to base permissions.
	 */
	public $taxonomy = '';

	/**
	 * Flag to set whether to initialise permissions
	 *
	 * @var string $flag flag whether to initialise permissions.
	 */
	public $flag = 'wpdr_init_taxonomy_permissions';

	/**
	 * Base capabilities to provide
	 *
	 * @var array $base_caps base document capabilities.
	 */
	public $base_caps = array(
		'edit_documents',
		'edit_others_documents',
		'edit_private_documents',
		'edit_published_documents',
		'read_documents',
		'read_document_revisions',
		'read_private_documents',
		'delete_documents',
		'delete_others_documents',
		'delete_private_documents',
		'delete_published_documents',
		'publish_documents',
		'override_document_lock',
	);

	/**
	 * Add hooks to WP API
	 */
	public function __construct() {

		// set defaults.
		global $wpdr_permissions_taxonomy;
		$this->taxonomy = ( $wpdr_permissions_taxonomy ) ? $wpdr_permissions_taxonomy : 'department';

		// register with necessary capabilitiy APIs.
		add_filter( 'map_meta_cap', array( &$this, 'map_meta_cap' ), 15, 4 );
		add_filter( 'user_has_cap', array( &$this, 'user_has_cap' ), 14, 4 );
		add_filter( 'document_caps', array( &$this, 'default_caps_filter' ), 10, 2 );

		// init hooks.
		add_action( 'init', array( &$this, 'maybe_register_taxonomy' ), 15 );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		register_activation_hook( __FILE__, array( &$this, 'activation' ) );

		// re-init caps on taxonomy change.
		add_action( 'delete_' . $this->taxonomy, array( &$this, 'remove_caps' ) );
		add_action( 'created_' . $this->taxonomy, array( &$this, 'add_caps' ) );
		add_action( 'edited_' . $this->taxonomy, array( &$this, 'add_caps' ) );

		// filter the queries.
		add_action( 'pre_get_posts', array( &$this, 'retrieve_user_documents' ) );

		// belt and braces.
		add_filter( 'posts_results', array( &$this, 'posts_results' ), 30, 2 );

		// make sure that the taxonomy is defined for each published document.
		add_action( 'admin_notices', array( &$this, 'admin_error_check' ), 1 );

		// filters the post to make sure there is a taxonomy value set.
		add_filter( 'wp_insert_post_empty_content', array( &$this, 'check_taxonomy_value_set' ), 10, 2 );
	}

	/**
	 * Fires on admin init to register capabilities
	 * Can't add initial caps on activation, because taxonomy isn't yet registered
	 */
	public function admin_init() {

		if ( ! get_option( $this->flag ) ) {
			return;
		}

		$this->add_caps();
	}

	/**
	 * Add our capabilities using the global WPDR object's native capabilities function
	 */
	public function add_caps() {

		global $wpdr;

		if ( ! $wpdr ) {
			$wpdr = new WP_Document_Revisions();
		}

		$wpdr->add_caps();

		delete_option( $this->flag );
	}

	/**
	 * Remove capabilities when a term is removed
	 *
	 * @param string $term term slug.
	 */
	public function remove_caps( $term ) {

		$term_obj = get_term( $term, $this->taxonomy );

		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			wp_die( esc_html__( 'WP Document Revisions Taxonomy Permissions requires wp_roles to be set', 'wp-document-revisions' ) );
		}

		foreach ( $wp_roles->role_names as $role => $label ) {
			foreach ( $this->base_caps as $base_cap ) {
				$role->remove_cap( $role, $base_cap . '_in_' . $term_obj->slug );
			}
		}
	}

	/**
	 * Checks that WP Document Revisisons is activated when this plugin is activated
	 * If so, toggle flag to add initial capabilities
	 */
	public function activation() {
		if ( ! class_exists( 'WP_Document_Revisions' ) ) {
			wp_die( esc_html__( 'WP Document Revisions must be activated to use Taxonomy Permissions', 'wp-document-revisions' ) );
		}

		update_option( $this->flag, true );
	}

	/**
	 * Conditionally registers the target taxonomy
	 */
	public function maybe_register_taxonomy() {
		global $wpdr_permissions_taxonomy_args;

		// taxonomy exists, no need to register.
		if ( taxonomy_exists( $this->taxonomy ) ) {
			// Need to check that the declared taxonomy is for documents.
			$tax = get_taxonomy( $this->taxonomy );
			if ( ! in_array( 'document', $tax->object_type, true ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( esc_html__( 'Taxonomy Permissions taxonomy must process document post type.', 'wp-document-revisions' ) );
			}
			return;
		}

		// Register the taxonomy.
		register_taxonomy( $this->taxonomy, array( 'document' ), $wpdr_permissions_taxonomy_args );
	}

	/**
	 * Adds capabilities to each role
	 * Suggest using Members or similar plugin to then manage each permission
	 *
	 * @uses document_caps filter
	 * @param array  $caps the default set of capabilities for the role.
	 * @param string $role the role being reviewed (all will be reviewed in turn).
	 * @return array $caps the modified set of capabilities for the role.
	 */
	public function default_caps_filter( $caps, $role ) {
		// get terms in the selected taxonomy.
		$terms = get_terms( array(
			'taxonomy'   => $this->taxonomy,
			'hide_empty' => false,
		) );

		// build out term specific caps.
		foreach ( $caps as $cap => $grant ) {
			foreach ( $terms as $term ) {
				$cap_term = $cap . '_in_' . $term->slug;
				if ( ! array_key_exists( $cap_term, $caps ) ) {
					$caps[ $cap_term ] = $grant;
				}
			}
		}

		// build out taxonomy capabilities.
		$taxonomy = get_taxonomy( $this->taxonomy );

		foreach ( get_object_vars( $taxonomy->cap ) as $cap ) {
			if ( ! array_key_exists( $cap, $caps ) ) {
				$caps[ $cap ] = $grant;
			}
		}

		return $caps;
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
		global $wpdr;

		// attempt to grab the post_ID.
		// note: will default to global $post if none passed.
		$post_ID = ( ! empty( $args ) ) ? $args[0] : null;

		// array of primitive caps that all document caps are based on.
		$primitive_caps = array( 'read_post', 'edit_post', 'delete_post', 'publish_post', 'override_document_lock' );

		// current cap being checked is not a post-specific cap, kick to save effort.
		if ( ! in_array( $cap, $primitive_caps, true ) ) {
			return $caps;
		}

		// cap being checked is not related to a document, kick.
		if ( ! $wpdr->verify_post_type( $post_ID ) ) {
			return $caps;
		}

		// get the terms in the taxonomy.
		$terms = get_the_terms( $post_ID, $this->taxonomy );

		// caps for read_post arrive as read.
		$caps[0] = str_replace( 'posts', 'documents', $caps[0] );
		$caps[0] = str_replace( 'post', 'documents', $caps[0] );

		// if no terms, assume primitive roles.
		if ( empty( $terms ) ) {
			return $caps;
		}

		// add taxonomy specific caps.
		$termcaps = array();
		foreach ( $caps as $tcap ) {
			foreach ( $terms as $term ) {
				if ( 'read' === $tcap ) {
					// treat read as read_documents.
					$termcaps[] = $tcap . '_documents_in_' . $term->slug;
				} else {
					$termcaps[] = $tcap . '_in_' . $term->slug;
				}
			}
		}

		return $termcaps;
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

		global $wpdr;

		// attempt to grab the post_ID.
		// note: will default to global $post if none passed.
		if ( ! $wpdr->verify_post_type( $args[2] ) ) {
			return $allcaps;
		}

		// if want to leave base terms, bail.
		if ( apply_filters( 'document_access_with_no_term', false ) ) {
			return $allcaps;
		}

		// remove the base capabilities.
		foreach ( $this->base_caps as $base_cap ) {
			unset( $allcaps[ $base_cap ] );
		}

		return $allcaps;
	}

	/**
	 * Try to retrieve only correct documents.
	 *
	 * @param WP_Query $query  Query object.
	 */
	public function retrieve_user_documents( $query ) {
		if ( isset( $query->query['post_type'] ) && 'document' === $query->query['post_type'] ) {
			// not for administrator.
			$user = wp_get_current_user();
			if ( in_array( 'administrator', $user->roles, true ) ) {
				return;
			}
		} else {
			// not interested.
			return;
		}

		// Get taxonomy query.
		$user_tax_query = $this->user_tax_query();

		if ( ! is_array( $user_tax_query ) ) {
			// User has no access to documents - so try to return that quickly.
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		// create/modify taxonomy query.
		if ( isset( $query->query['tax_query'] ) && ! empty( $query->query['tax_query'] ) ) {
			// Update tax query.
			$tax_query = $query->query['tax_query'];
			if ( ! isset( $tax_query['relation'] ) || 'AND' === $tax_query['relation'] ) {
				// can simply add a condition.
				$tax_query[]           = $user_tax_query;
				$tax_query['relation'] = 'AND';
			} else {
				// push down the query and add in the query with an AND.
				$tax_query = array(
					'relation' => 'AND',
					$user_tax_query,
					$tax_query,
				);
			}
			$query->set( 'tax_query', $tax_query );
		} else {
			$query->set( 'tax_query', $user_tax_query );
		}
	}

	/**
	 * Build tax query for documents.
	 */
	public function user_tax_query() {
		$user           = wp_get_current_user();
		$user_tax_query = wp_cache_get( 'wpdr_tax_query_' . $user->ID );

		if ( false === $user_tax_query ) {
			// build and create cache entry.

			// get user capabilities.
			$allcaps = $user->allcaps;

			// get terms in the selected taxonomy.
			$terms = get_terms( $this->taxonomy, array( 'hide_empty' => false ) );

			// See any caps exist for user in term.
			$user_terms = array();
			foreach ( $terms as $term ) {
				foreach ( $this->base_caps as $cap ) {
					$cap_term = $cap . '_in_' . $term->slug;
					// entry may exist - must be true.
					if ( isset( $allcaps[ $cap_term ] ) && 1 === (int) $allcaps[ $cap_term ] ) {
							$user_terms[] = $term->term_taxonomy_id;
							break;
					}
				}
			}

			if ( empty( $user_terms ) ) {
				if ( apply_filters( 'document_access_with_no_term', false ) ) {
					// Can access documents with no taxonomy term.
					$user_tax_query = array(
						array(
							'taxonomy' => $this->taxonomy,
							'operator' => 'NOT EXISTS',
						),
					);
				} else {
					$user_tax_query = 'No access';
				}
			} else {
				if ( apply_filters( 'document_access_with_no_term', false ) ) {
					// Can access documents with no taxonomy term.
					$user_tax_query = array(
						array(
							'taxonomy' => $this->taxonomy,
							'field'    => 'term_taxonomy_id',
							'terms'    => $user_terms,
							'operator' => 'IN',
						),
						array(
							'taxonomy' => $this->taxonomy,
							'operator' => 'NOT EXISTS',
						),
						'relation' => 'OR',
					);
				} else {
					$user_tax_query = array(
						array(
							'taxonomy' => $this->taxonomy,
							'field'    => 'term_taxonomy_id',
							'terms'    => $user_terms,
							'operator' => 'IN',
						),
					);
				}
			}

			wp_cache_set( 'wpdr_tax_query_' . $user->ID, $user_tax_query, '', ( WP_DEBUG ? 10 : 120 ) );
		}

		return $user_tax_query;
	}

	/**
	 * Get user terms.
	 */
	public function user_terms() {
		$user       = wp_get_current_user();
		$user_terms = wp_cache_get( 'wpdr_user_terms_' . $user->ID );

		if ( false === $user_terms ) {
			// build and create cache entry.
			$user_terms = array();
			if ( 0 === $user->ID ) {
				// no can do.
				null;
			} else {
				$caps = $user->allcaps;
				foreach ( $caps as $cap => $value ) {
					if ( true === (bool) $value && 0 < strpos( $cap, '_in_' ) ) {
						foreach ( $this->base_caps as $bcap ) {
							if ( 0 === strpos( $cap, $bcap . '_in_' ) ) {
								$slug = substr( $cap, strlen( $cap ) + 4 );
								if ( empty( $terms ) || ! in_array( $slug, $user_terms, true ) ) {
									$user_terms[] = $slug;
								}
							}
						}
					}
				}
			}

			wp_cache_set( 'wpdr_user_terms_' . $user->ID, $user_terms, '', ( WP_DEBUG ? 10 : 120 ) );
		}

		return $user_terms;
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

		if ( isset( $query->query['post_type'] ) && ! is_array( $query->query['post_type'] ) ) {
			// query by post type has been addressed in the initial query (when not an array).
			return $results;
		}

		// find the users terms.
		$user_terms = $this->user_terms();
		$no_terms   = apply_filters( 'document_access_with_no_term', false );
		$noaccess   = empty( $user_terms ) && ! $no_terms;

		global $wpdr;

		$match = false;
		foreach ( $results as $key => $result ) {
			// confirm a document.
			if ( ! $wpdr->verify_post_type( $result ) ) {
				continue;
			}

			// user has no access, remove from result.
			if ( $noaccess ) {
				unset( $results[ $key ] );
				$match = true;
				continue;
			}

			// get the document terms in the taxonomy.
			$terms = get_the_terms( $result, $this->taxonomy );

			// None on document, but allowed to access.
			if ( empty( $terms ) && $no_terms ) {
				continue;
			}

			// if match, let it through by jumping though to the end of the outer loop.
			foreach ( $terms as $term ) {
				if ( in_array( $term->ID, $user_terms, true ) ) {
					continue 2;
				}
			}

			// got to the end and no match, so remove.
			unset( $results[ $key ] );
			$match = true;
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

	/**
	 * Check that one (and only one) taxonomy term is entered for published documents.
	 *
	 * Invoked *before* post is inserted/updated.
	 *
	 * @param bool  $maybe_empty Whether the post should be considered "empty".
	 * @param array $postarr     Array of post data.
	 */
	public function check_taxonomy_value_set( $maybe_empty, $postarr ) {
		// No post object available, use the arrays.
		// Only process the document record at publish status.
		if ( 'document' !== $postarr['post_type'] || 'publish' !== $postarr['post_status'] ) {
			return $maybe_empty;
		}

		$error_type = '';
		// look for the terms in the post data.
		if ( ! isset( $postarr['tax_input'][ $this->taxonomy ] ) ) {
			$error_type = 'zero';
		}
		$terms = $postarr['tax_input'][ $this->taxonomy ];
		// ignore the 0 element.
		unset( $terms[0] );

		// no terms and not allowed.
		if ( empty( $terms ) && ! apply_filters( 'document_access_with_no_term', false ) ) {
			$error_type = 'none';
		}

		// more than one.
		if ( apply_filters( 'document_only_one_term_allowed', true ) && 1 < count( $terms ) ) {
			$error_type = 'many';
		}

		if ( '' !== $error_type ) {
			// commen error path.
			$url = add_query_arg(
				array(
					'post'           => $postarr['ID'],
					'action'         => 'edit',
					'wpdr_tax_perm'  => $error_type,
					'document_terms' => wp_create_nonce( 'terms' ),
				),
				get_home_url( null, $postarr['_wp_http_referer'] )
			);

			if ( wp_safe_redirect( $url ) ) {
				exit;
			}
		}

		return $maybe_empty;
	}

	/**
	 * Output any error when checking that one and only one taxonomy term set.
	 *
	 * Invoked on publish of a document (so no need to check).
	 */
	public function admin_error_check() {
		if ( ( ! isset( $_GET['document_terms'] ) ) || false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['document_terms'] ) ), 'terms' ) ) {
			// not valid.
			return;
		}

		if ( array_key_exists( 'wpdr_tax_perm', $_GET ) ) {
			if ( isset( $_GET['message'] ) ) {
				// This will over-ride any message.
				unset( $_GET['message'] );
			}

			?>
			<div class="error">
				<p>
				<?php
				switch ( $_GET['wpdr_tax_perm'] ) {
					case 'zero':
						// translators: %s is the taxonomy.
						echo esc_html( sprintf( __( 'WPDR Taxonomy Permissions requires published documents to have the taxonomy %s entered.', 'wp-document-revisions' ), $this->taxonomy ) );
						break;
					case 'none':
						// translators: %s is the taxonomy.
						echo esc_html( sprintf( __( 'WPDR Taxonomy Permissions requires published documents to have one term entered for taxonomy %s.', 'wp-document-revisions' ), $this->taxonomy ) );
						break;
					case 'many':
						// translators: %s is the taxonomy.
						echo esc_html( sprintf( __( 'WPDR Taxonomy Permissions requires published documents to have only one term entered for taxonomy %s.', 'wp-document-revisions' ), $this->taxonomy ) );
						break;
					default:
						null;
				};
				?>
				</p>
				<p><?php esc_html_e( 'Your update has been cancelled and data is in its original state.', 'wp-document-revisions' ); ?></p>
				<p><?php esc_html_e( 'Your browser may have an updated version, but it is invalid.', 'wp-document-revisions' ); ?></p>
			</div>
			<?php
		}
	}
}

<?php

/**
 * CiviCRM ACF Integration WordPress Post Class.
 *
 * A class that encapsulates WordPress Post functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_Post {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Post meta Contact ID key.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $plugin The Post meta Contact ID key.
	 */
	public $contact_id_key = '_civicrm_acf_integration_post_contact_id';



	/**
	 * Constructor.
	 *
	 * @since 0.2
	 *
	 * @param object $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Store reference to plugin.
		$this->plugin = $plugin;

		// Init when this plugin is loaded.
		add_action( 'civicrm_acf_integration_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.2
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.2
	 */
	public function register_hooks() {

		// Add our meta boxes.
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes_add' ], 11, 2 );

		// Listen for events from our Mapper that require Post updates.
		add_action( 'civicrm_acf_integration_mapper_contact_created', [ $this, 'contact_created' ], 10, 1 );
		add_action( 'civicrm_acf_integration_mapper_contact_edited', [ $this, 'contact_edited' ], 10, 1 );

		// Maybe sync the Contact "Display Name" to the WordPress Post Title.
		add_action( 'civicrm_acf_integration_contact_acf_fields_saved', [ $this, 'maybe_sync_title' ], 10, 3 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Create a WordPress Post when a CiviCRM Contact has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function contact_created( $args ) {

		// Bail if this is not a Contact.
		$top_level_types = $this->plugin->civicrm->contact_type->types_get_top_level();
		if ( ! in_array( $args['objectName'], $top_level_types ) ) {
			return;
		}

		// Bail if this Contact's Contact Type is not mapped.
		$contact_types = $this->plugin->civicrm->contact_type->hierarchy_get_for_contact( $args['objectRef'] );
		$post_type = $this->plugin->civicrm->contact_type->is_mapped( $contact_types );
		if ( $post_type === false ) {
			return;
		}

		// Create the WordPress Post.
		$post_id = $this->create_from_contact( $args['objectRef'], $post_type );

		// Add our data to the params.
		$args['contact_types'] = $contact_types;
		$args['post_type'] = $post_type;
		$args['post_id'] = $post_id;

		/**
		 * Broadcast that a WordPress Post has been updated from Contact details.
		 *
		 * Used internally to:
		 *
		 * - Update the ACF Fields for the WordPress Post
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM and discovered params.
		 */
		do_action( 'civicrm_acf_integration_post_created', $args );

	}



	/**
	 * Update a WordPress Post when a CiviCRM Contact has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function contact_edited( $args ) {

		// Bail if this is not a Contact.
		$top_level_types = $this->plugin->civicrm->contact_type->types_get_top_level();
		if ( ! in_array( $args['objectName'], $top_level_types ) ) {
			return;
		}

		// Get the full Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $args['objectId'] );

		// Bail if something went wrong.
		if ( $contact === false ) {
			return;
		}

		// Overwrite args with full Contact data.
		$args['objectRef'] = (object) $contact;

		// Bail if this Contact's Contact Type is not mapped.
		$contact_types = $this->plugin->civicrm->contact_type->hierarchy_get_for_contact( $args['objectRef'] );
		$post_type = $this->plugin->civicrm->contact_type->is_mapped( $contact_types );
		if ( $post_type === false ) {
			return;
		}

		// Get the Post ID for this Contact.
		$post_id = $this->plugin->civicrm->contact->is_mapped( $args['objectRef'] );

		// Create the WordPress Post if it doesn't exist, otherwise update.
		if ( $post_id === false ) {
			$post_id = $this->create_from_contact( $args['objectRef'], $post_type );
		} else {
			$this->update_from_contact( $args['objectRef'], $post_id );
		}

		// Add our data to the params.
		$args['contact_types'] = $contact_types;
		$args['post_type'] = $post_type;
		$args['post_id'] = $post_id;

		/**
		 * Broadcast that a WordPress Post has been updated from Contact details.
		 *
		 * Used internally to:
		 *
		 * - Update the ACF Fields for the WordPress Post
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM and discovered params.
		 */
		do_action( 'civicrm_acf_integration_post_edited', $args );

	}



	// -------------------------------------------------------------------------



	/**
	 * Register meta boxes.
	 *
	 * @since 0.3
	 *
	 * @param str $post_type The WordPress Post Type.
	 * @param WP_Post $post The WordPress Post.
	 */
	public function meta_boxes_add( $post_type, $post ) {

		// Bail if this Post Type is not mapped.
		if ( ! $this->plugin->post_type->is_mapped( $post_type ) ) {
			return;
		}

		// Bail if user cannot access CiviCRM.
		if ( ! current_user_can( 'access_civicrm' ) ) {
			return;
		}

		// Get Contact ID.
		$contact_id = $this->contact_id_get( $post->ID );

		// Bail if there's no corresponding Contact.
		if ( $contact_id === false ) {
			return;
		}

		// Check permission to view this Contact.
		if ( ! $this->plugin->civicrm->contact->user_can_view( $contact_id ) ) {
			return;
		}

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civicrm_acf_integration_metabox',
			__( 'CiviCRM ACF Integration', 'civicrm-acf-integration' ),
			[ $this, 'meta_box_link_render' ], // Callback.
			$post_type, // Post Type.
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

	}



	/**
	 * Render a meta box on Post edit screens with a link to the Contact.
	 *
	 * @since 0.3
	 *
	 * @param WP_Post $post The WordPress Post.
	 */
	public function meta_box_link_render( $post ) {

		// Get Contact ID.
		$contact_id = $this->contact_id_get( $post->ID );

		// Bail if we don't get one for some reason.
		if ( $contact_id === false ) {
			return;
		}

		// Get the URL for this Contact.
		$url = $this->plugin->civicrm->get_link( 'civicrm/contact/view', 'reset=1&cid=' . $contact_id );

		// Construct link.
		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'View this Contact in CiviCRM', 'civicrm-acf-integration' )
		);

		// Show it.
		echo '<p>' . $link . '</p>';

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Contact ID for a given WordPress Post ID.
	 *
	 * @since 0.2.1
	 *
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return int $contact_id The CiviCRM Contact ID, or false if none exists.
	 */
	public function contact_id_get( $post_id ) {

		// Get the Contact ID.
		$existing_id = get_post_meta( $post_id, $this->contact_id_key, true );

		// Does this Post have a Contact ID?
		if ( empty( $existing_id ) ) {
			$contact_id = false;
		} else {
			$contact_id = $existing_id;
		}

		// --<
		return $contact_id;

	}



	/**
	 * Set the CiviCRM Contact ID for a given WordPress Post ID.
	 *
	 * @since 0.2.1
	 *
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @param int $contact_id The CiviCRM Contact ID.
	 */
	public function contact_id_set( $post_id, $contact_id ) {

		// Store the Contact ID.
		add_post_meta( $post_id, $this->contact_id_key, $contact_id, true );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the WordPress Post for a given CiviCRM Contact ID.
	 *
	 * @since 0.2.1
	 *
	 * @param int $contact_id The CiviCRM Contact ID.
	 * @return WP_Post|bool $post The WordPress Post object, or false on failure.
	 */
	public function get_by_contact_id( $contact_id ) {

		// Init as failed.
		$post = false;

		// Define args for query.
		$args = [
			'post_type' => 'any',
			//'post_status' => 'publish',
			'no_found_rows' => true,
			'meta_key' => $this->contact_id_key,
			'meta_value' => (string) $contact_id,
			'posts_per_page' => 1,
		];

		// Do query.
		$query = new WP_Query( $args );

		// Grab what should be the only Post.
		if ( $query->have_posts() ) {
			foreach( $query->get_posts() AS $found ) {
				$post = $found;
				break;
			}
		}

		// --<
		return $post;

	}



	// -------------------------------------------------------------------------



	/**
	 * Create a CiviCRM Contact from a WordPress Post.
	 *
	 * @since 0.2.1
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param str $post_type The name of Post Type.
	 * @return int|bool $post_id The WordPress Post ID, or false on failure.
	 */
	public function create_from_contact( $contact, $post_type ) {

		// Maybe cast Contact data as array.
		if ( is_object( $contact ) ) {
			$contact = (array) $contact;
		}

		// Define basic Post data.
		$args = [
			'post_status' => 'publish',
			'post_parent' => 0,
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'to_ping' => '', // Quick fix for Windows.
			'pinged' => '', // Quick fix for Windows.
			'post_content_filtered' => '', // Quick fix for Windows.
			'post_excerpt' => '', // Quick fix for Windows.
			'menu_order' => 0,
			'post_type' => $post_type,
			'post_title' => $contact['display_name'],
			'post_content' => '',
		];

		// Insert the Post into the database.
		$post_id = wp_insert_post( $args );

		// Bail on failure.
		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Contact ID is sometimes stored in 'contact_id', sometimes in 'id'.
		if ( ! isset( $contact['id'] ) ) {
			$contact_id = $contact['contact_id'];
		} else {
			$contact_id = $contact['id'];
		}

		// Save correspondence.
		$this->contact_id_set( $post_id, $contact_id );

		// We need to force ACF to create Fields for the Post.

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// If there are some, prime them with an empty string.
		if ( ! empty( $acf_fields ) ) {
			foreach( $acf_fields AS $field_group ) {
				foreach( $field_group AS $selector => $contact_field ) {
					$this->plugin->acf->field->value_update( $selector, '', $post_id );
				}
			}
		}

		// --<
		return $post_id;

	}



	/**
	 * Sync a CiviCRM Contact with a WordPress Post.
	 *
	 * @since 0.2.1
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param int $existing_id The numeric ID of the Post.
	 * @param WP_Post $post The WordPress Post object if it exists.
	 * @return int|bool $post_id The WordPress Post ID, or false on failure.
	 */
	public function update_from_contact( $contact, $existing_id, $post = null ) {

		// Maybe cast Contact data as array.
		if ( is_object( $contact ) ) {
			$contact = (array) $contact;
		}

		// Define args to update the Post.
		$args = [
			'ID' => $existing_id,
			'post_title' => $contact['display_name'],
		];

		// Overwrite Permalink if the current Post Title is empty.
		if ( ! is_null( $post ) AND $post instanceof WP_Post ) {
			if ( empty( $post->post_title ) ) {
				$args['post_name'] = sanitize_title( $contact['display_name'] );
			}
		}

		// Update the Post.
		$post_id = wp_update_post( $args, true );

		// Bail on failure.
		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// --<
		return $post_id;

	}



	// -------------------------------------------------------------------------



	/**
	 * Check if a WordPress Post should be synced.
	 *
	 * @since 0.2.1
	 *
	 * @param WP_Post $post_obj The WordPress Post object.
	 * @return WP_Post|bool $post The WordPress Post object, or false if not allowed.
	 */
	public function should_be_synced( $post_obj ) {

		// Bail if no Post object.
		if ( ! $post_obj ) {
			return false;
		}

		// Bail if this is an auto save routine.
		if ( defined( 'DOING_AUTOSAVE' ) AND DOING_AUTOSAVE ) {
			return false;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_obj->ID ) ) {
			return false;
		}

		// Check for revision or auto-draft.
		if ( $post_obj->post_type == 'revision' ) {

			// Get parent.
			if ( $post_obj->post_parent != 0 ) {
				$post = get_post( $post_obj->post_parent );
			} else {
				$post = $post_obj;
			}

		} else {
			$post = $post_obj;
		}

		// --<
		return $post;

	}



	/**
	 * Check if a WordPress Post Title should be synced.
	 *
	 * @since 0.4.5
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param WP_Post $post The WordPress Post object.
	 * @param array $fields The array of ACF Field values, keyed by Field selector.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function maybe_sync_title( $contact, $post, $fields ) {

		// Maybe cast Contact data as array.
		if ( is_object( $contact ) ) {
			$contact = (array) $contact;
		}

		// Bail if no Display Name.
		if ( empty( $contact['display_name'] ) ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		// Bail if the Display Name and the Title match.
		if ( $post->post_title == $contact['display_name'] ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->plugin->mapper->hooks_wordpress_remove();

		// Update the Post Title (and maybe the Post Permalink).
		$this->update_from_contact( $contact, $post->ID, $post );

		// Reinstate WordPress callbacks.
		$this->plugin->mapper->hooks_wordpress_add();

	}



} // Class ends.




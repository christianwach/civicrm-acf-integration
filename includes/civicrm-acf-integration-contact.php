<?php

/**
 * CiviCRM ACF Integration CiviCRM Contact Class.
 *
 * A class that encapsulates CiviCRM Contact functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Contact {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Field" field key in the ACF Field data.
	 *
	 * @since 0.3
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Field" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_custom_field';



	/**
	 * Constructor.
	 *
	 * @since 0.2.1
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to parent.
		$this->civicrm = $parent;

		// Init when the CiviCRM object is loaded.
		add_action( 'civicrm_acf_integration_civicrm_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.2.1
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.2.1
	 */
	public function register_hooks() {

		// Listen for events from our Mapper that require Contact updates.
		add_action( 'civicrm_acf_integration_mapper_post_saved', [ $this, 'post_saved' ], 10, 1 );
		add_action( 'civicrm_acf_integration_mapper_acf_fields_saved', [ $this, 'acf_fields_saved' ], 10, 1 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact when a WordPress Post has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function post_saved( $args ) {

		// Bail if this Post should not be synced now.
		$this->do_not_sync = false;
		$post = $this->plugin->post->should_be_synced( $args['post'] );
		if ( false === $post ) {
			$this->do_not_sync = true;
			return;
		}

		// Bail if this Post Type is not mapped.
		if ( ! $this->plugin->post_type->is_mapped( $post->post_type ) ) {
			$this->do_not_sync = true;
			return;
		}

		// Get the Contact ID.
		$contact_id = $this->plugin->post->contact_id_get( $post->ID );

		/*
		// Get previous values.
		$prev_values = get_fields( $post_id );

		// Get submitted values.
		$values = acf_maybe_get_POST( 'acf' );
		*/

		// Does this Post have a Contact ID?
		if ( $contact_id === false ) {

			// No - create a Contact.
			$contact = $this->create_from_post( $post );

			// Store Contact ID if successful.
			if ( $contact !== false ) {
				$this->plugin->post->contact_id_set( $post->ID, $contact['id'] );
			}

		} else {

			// Yes - update the Contact.
			$success = $this->update_from_post( $post, $contact_id );

		}

	}



	/**
	 * Update a CiviCRM Contact when the ACF Fields on a WordPress Post have been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function acf_fields_saved( $args ) {

		// Bail early if this Post Type shouldn't be synced.
		// @see self::post_saved()
		if ( $this->do_not_sync === true ) {
			return;
		}

		// We need the Post itself.
		$post = get_post( $args['post_id'] );

		// Bail if this is a revision.
		if ( $post->post_type == 'revision' ) {
			return;
		}

		// Does this Post have a Contact ID?
		$contact_id = $this->plugin->post->contact_id_get( $post->ID );

		// Bail if there isn't one.
		if ( $contact_id === false ) {
			return;
		}

		/*
		 * Get existing field values.
		 *
		 * These are actually the *new* values because we are hooking in *after*
		 * the fields have been saved.
		 */
		$fields = get_fields( $post->ID );

		// Get submitted values. (No need for this - see hook priority)
		//$submitted_values = acf_maybe_get_POST( 'acf' );

		// Update the Contact with this data.
		$contact = $this->update_from_fields( $contact_id, $fields );

		// TODO: Maybe pass params as array?

		/*
		// Add our data to the params.
		$args['contact'] = $contact;
		$args['contact_id'] = $contact_id;
		$args['post'] = $post;
		$args['fields'] = $fields;
		*/

		/**
		 * Broadcast that a Contact has been updated when ACF Fields were saved.
		 *
		 * Used internally by:
		 *
		 * - Contact Fields
		 * - Relationships
		 * - Addresses
		 * - Websites
		 * - WordPress Posts - to maintain sync with the Contact "Display Name"
		 *
		 * @since 0.4.3
		 *
		 * @param array $contact The CiviCRM Contact data.
		 * @param WP_Post $post The WordPress Post object.
		 * @param array $fields The array of ACF Field values, keyed by Field selector.
		 */
		do_action( 'civicrm_acf_integration_contact_acf_fields_saved', $contact, $post, $fields );

	}



	// -------------------------------------------------------------------------



	/**
	 * Getter method for the "Handled Fields" array.
	 *
	 * @since 0.4.3
	 *
	 * @return array $fields_handled The array of Contact Fields which must be handled separately.
	 */
	public function fields_handled_get() {

		/**
		 * Filter the "Handled Fields" array.
		 *
		 * Classes in this plugin add the fields they handle via this filter.
		 *
		 * @since 0.4.3
		 *
		 * @param array $fields_handled The existing array of Fields which must be handled separately.
		 * @return array $fields_handled The modified array of Fields which must be handled separately.
		 */
		$fields_handled = apply_filters( 'civicrm_acf_integration_civicrm_fields_handled', [] );

		// --<
		return $fields_handled;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Contact data for a set of given IDs.
	 *
	 * @since 0.4
	 *
	 * @param array $contact_ids The array of numeric IDs of the CiviCRM Contacts to query.
	 * @return array|bool $contact_data An array of Contact data, or false on failure.
	 */
	public function get_by_ids( $contact_ids = [] ) {

		// Init return.
		$contact_data = false;

		// Bail if we have no Contact IDs.
		if ( empty( $contact_ids ) ) {
			return $contact_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_data;
		}

		// Define params to get queried Contacts.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => [ 'IN' => $contact_ids ],
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Contact', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_data;
		}

		// --<
		return $result['values'];

	}



	/**
	 * Get the CiviCRM Contact data for a given ID.
	 *
	 * @since 0.3
	 *
	 * @param int $contact_id The numeric ID of the CiviCRM Contact to query.
	 * @return array|bool $contact_data An array of Contact data, or false on failure.
	 */
	public function get_by_id( $contact_id ) {

		// Init return.
		$contact_data = false;

		// Bail if we have no Contact ID.
		if ( empty( $contact_id ) ) {
			return $contact_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_data;
		}

		// Define params to get queried Contact.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $contact_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Contact', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_data;
		}

		// The result set should contain only one item.
		$contact_data = array_pop( $result['values'] );

		// --<
		return $contact_data;

	}



	/**
	 * Get the CiviCRM Contact data for a given search string.
	 *
	 * @since 0.4
	 *
	 * @param str $search The search string to query.
	 * @param str $contact_type The CiviCRM Contact Type.
	 * @param str $contact_subtype The CiviCRM Contact Sub-type.
	 * @return array|bool $contact_data An array of Contact data, or false on failure.
	 */
	public function get_by_search_string( $search, $contact_type = '', $contact_subtype = '' ) {

		// Init return.
		$contact_data = false;

		// Bail if we have no Contact ID.
		if ( empty( $search ) ) {
			return $contact_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_data;
		}

		// Define params to get queried Contact.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'input' => $search,
			'search_field' => 'display_name',
			'label_field' => 'display_name',
			'options' => [
				'limit' => 25, // No limit.
			],
		];

		// Maybe narrow the search to a Contact Type.
		if ( ! empty( $contact_type ) ) {
			$params['params'] = [ 'contact_type' => $contact_type ];
		}

		// Maybe narrow the search to a Contact Sub-type.
		if ( ! empty( $contact_type ) AND ! empty( $contact_subtype ) ) {
			$params['params']['contact_sub_type'] = $contact_subtype;
		}

		// Call the API.
		$result = civicrm_api( 'Contact', 'getlist', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_data;
		}

		// --<
		return $result['values'];

	}



	// -------------------------------------------------------------------------



	/**
	 * Check if a Contact is mapped to a Post.
	 *
	 * @since 0.2
	 *
	 * @param array|obj $contact The Contact data.
	 * @return int|bool $is_mapped The ID of the WordPress Post if the Contact is mapped, false otherwise.
	 */
	public function is_mapped( $contact ) {

		// Assume not.
		$is_mapped = false;

		// Maybe cast Contact data as array.
		if ( is_object( $contact ) ) {
			$contact = (array) $contact;
		}

		// Get the Contact Type hierarchy for this Contact.
		$contact_type = $this->civicrm->contact_type->hierarchy_get_for_contact( $contact );

		// Check if it's a top level Contact Type.
		if ( empty( $contact_type['subtype'] ) ) {
			$contact_type = $contact_type['type'];
		} else {
			$contact_type = $contact_type['subtype'];
		}

		// Get the data for this Contact Type.
		$contact_type_data = $this->civicrm->contact_type->get_data( $contact_type, 'name' );

		// Bail if we didn't get any.
		if ( $contact_type_data === false ) {
			return $is_mapped;
		}

		// Bail if Contact Type not a mapped to a Post Type.
		$mapped = $this->civicrm->contact_type->is_mapped( $contact_type_data['id'] );
		if ( $mapped === false ) {
			return $is_mapped;
		}

		// "hook_civicrm_pre" sends $contact['contact_id']
		if ( isset( $contact['contact_id'] ) ) {
			$contact_id = $contact['contact_id'];
		}

		// "hook_civicrm_post" sends $contact['id']
		if ( isset( $contact['id'] ) ) {
			$contact_id = $contact['id'];
		}

		// Bail if no Contact ID is found.
		if ( empty( $contact_id ) ) {
			return $is_mapped;
		}

		// Find the Post that this Contact is synced with.
		$post = $this->plugin->post->get_by_contact_id( $contact_id );

		// Bail if no Post is found.
		if ( ! ( $post instanceOf WP_Post ) ) {
			return $is_mapped;
		}

		// Assign return.
		$is_mapped = $post->ID;

		// --<
		return $is_mapped;

	}



	// -------------------------------------------------------------------------



	/**
	 * Create a CiviCRM Contact for a given set of data.
	 *
	 * @since 0.2
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @return array|bool $contact_data The array Contact data from the CiviCRM API, or false on failure.
	 */
	public function create( $contact ) {

		// Init as failure.
		$contact_data = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_data;
		}

		// Buld params to create Contact.
		$params = [
			'version' => 3,
		] + $contact;

		/*

		// Minimum array to create a Contact:
		$params = [
			'version' => 3,
			'contact_type' => "Individual",
			'contact_sub_type' => "Dog",
			'display_name' => "Rover",
		];

		// Updates are triggered by:
		$params['id'] = 255;

		// Custom Fields are addressed by ID:
		$params['custom_9'] = "Blah"; // Dog Story
		$params['custom_7'] = 1; // House Broken
		$params['custom_8'] = 0; // Crate Trained

		*/

		// Call the API.
		$result = civicrm_api( 'Contact', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_data;
		}

		// The result set should contain only one item.
		$contact_data = array_pop( $result['values'] );

		// --<
		return $contact_data;

	}



	/**
	 * Update a CiviCRM Contact with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect a
	 * Contact ID to have been set in the Contact data.
	 *
	 * @since 0.2
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @return array|bool The array Contact data from the CiviCRM API, or false on failure.
	 */
	public function update( $contact ) {

		// Log and bail if there's no Contact ID.
		if ( empty( $contact['id'] ) ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'A numerical ID must be present to update a Contact.', 'civicrm-acf-integration' ),
				'contact' => $contact,
				'backtrace' => $trace,
			), true ) );
			return false;
		}

		// Pass through.
		return $this->create( $contact );

	}



	/**
	 * Delete a CiviCRM Contact for a given set of data.
	 *
	 * @since 0.2
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @return array|bool $contact_data The array Contact data from the CiviCRM API, or false on failure.
	 */
	public function delete( $contact ) {

		// Init as failure.
		$contact_data = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_data;
		}

		// Log and bail if there's no Contact ID.
		if ( empty( $contact['id'] ) ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'A numerical ID must be present to delete a Contact.', 'civicrm-acf-integration' ),
				'contact' => $contact,
				'backtrace' => $trace,
			), true ) );
			return false;
		}

		// Buld params to delete Contact.
		$params = [
			'version' => 3,
		] + $contact;

		// Call the API.
		$result = civicrm_api( 'Contact', 'delete', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_data;
		}

		// The result set should contain only one item.
		$contact_data = array_pop( $result['values'] );

		// --<
		return $contact_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Prepare the required CiviCRM Contact data from a WordPress Post.
	 *
	 * @since 0.2
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @param int $contact_id The numeric ID of the Contact (or null if new).
	 * @return array $contact_data The CiviCRM Contact data.
	 */
	public function prepare_from_post( $post, $contact_id = null ) {

		// Init required data.
		$contact_data = [];

		// Maybe add the Contact ID.
		if ( ! empty( $contact_id ) ) {
			$contact_data['id'] = $contact_id;
		}

		// Always assign Post Title to Contact "display_name".
		$contact_data['display_name'] = $post->post_title;

		// Retrieve and assign Contact Types.
		$contact_types = $this->civicrm->contact_type->hierarchy_get_for_post_type( $post->post_type );
		$contact_data['contact_type'] = $contact_types['type'];
		$contact_data['contact_sub_type'] = $contact_types['subtype'];

		// Set a status for the Contact depending on the Post status.
		if ( $post->post_status == 'trash' ) {
			$contact_data['is_deleted'] = 1;
		} else {
			$contact_data['is_deleted'] = 0;
		}

		/**
		 * Filter the way that names are built.
		 *
		 * Syncing names is complicated!
		 *
		 * CiviCRM builds the "display_name" from the "first_name" and "last_name"
		 * params sent to the API when a Contact is *updated*. It does not do this
		 * when it *creates* a Contact.
		 *
		 * The question, therefore, is how to manage the sync between the WordPress
		 * "post_title" and the CiviCRM Contact "display_name"...
		 *
		 * When there are mapped ACF fields for "first_name" and "last_name", this
		 * becomes less of a problem, though it's not clear how to populate these
		 * fields for a Contact with just one name that is not a Contact Type which
		 * extends "Organisation" or "Household" (a dog, for example).
		 *
		 * Let's take the example of a dog called "Rover":
		 *
		 * - The WordPress "post_title" would be "Rover".
		 * - The Contact "display_name" should be "Rover".
		 * - The CiviCRM API requires the "first_name" and "last_name" fields.
		 * - The CiviCRM API does not update the "display_name" directly.
		 * - There are no "first_name" and "last_name" ACF fields.
		 *
		 * In this situation, there is no obvious way of configuring this in either
		 * the WordPress or CiviCRM UIs. WordPress has no UI for Post Types (except
		 * via a plugin - which means supporting plugins that offer a UI) and the
		 * CiviCRM UI for Contact Types would be very difficult to adapt such that
		 * these options are configurable.
		 *
		 * At present, I can't think of other situations where there's a mismatch
		 * between these fields, so perhaps a plugin Settings Page might be needed
		 * with a checkbox per Post Type selecting between:
		 *
		 * - Sync where the Contact has just one name
		 * - Sync where the Contact has the common "first_name" and "last_name"
		 *
		 * @since 0.2.1
		 *
		 * @param array $contact_data The existing CiviCRM Contact data.
		 * @param WP_Post $post The WordPress Post.
		 * @return array $contact_data The modified CiviCRM Contact data.
		 */
		$contact_data = apply_filters( 'civicrm_acf_integration_post_contact_data', $contact_data, $post );

		// --<
		return $contact_data;

	}



	/**
	 * Create a CiviCRM Contact from a WordPress Post.
	 *
	 * This can be merged with `self::update_from_post()` in due course.
	 *
	 * @since 0.2
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @return array|bool $contact The CiviCRM Contact data, or false on failure.
	 */
	public function create_from_post( $post ) {

		// Build required data.
		$contact_data = $this->prepare_from_post( $post );

		/*
		 * Should we save the Post ID in the "External ID" field?
		 *
		 * The problem with this is that people actually use the field!
		 *
		 * Reverse look-ups (i.e. from CiviCRM) can be done by querying Posts
		 * with a meta query.
		 *
		 * @see CiviCRM_ACF_Integration_Post::get_by_contact_id().
		 */

		// Create the Contact.
		$contact = $this->create( $contact_data );

		// --<
		return $contact;

	}



	/**
	 * Sync a WordPress Post with a CiviCRM Contact.
	 *
	 * When we update the Contact, we always sync the WordPress Post's title
	 * with the CiviCRM Contact's "display name".
	 *
	 * Depending on the setting for the Contact Type, we also optionally sync
	 * the "post_content" with a Custom Field.
	 *
	 * @since 0.2
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @param int $existing_id The numeric ID of the Contact.
	 * @return array|bool $contact The CiviCRM Contact data, or false on failure.
	 */
	public function update_from_post( $post, $existing_id ) {

		// Build required data.
		$contact_data = $this->prepare_from_post( $post, $existing_id );

		// Update the Contact.
		$contact = $this->update( $contact_data );

		// --<
		return $contact;

	}



	// -------------------------------------------------------------------------



	/**
	 * Prepare the required CiviCRM Contact data from a set of ACF Fields.
	 *
	 * This method combines all Contact Fields that the CiviCRM API accepts as
	 * params for ( 'Contact', 'create' ) along with the linked Custom Fields.
	 *
	 * The CiviCRM API will update Custom Fields as long as they are passed to
	 * ( 'Contact', 'create' ) in the correct format. This is of the form:
	 * 'custom_N' where N is the ID of the Custom Field.
	 *
	 * Some Fields have to be handled elsewhere (e.g. 'email') because they are
	 * not included in these API calls.
	 *
	 * @see CiviCRM_ACF_Integration_CiviCRM_Base
	 *
	 * @since 0.2
	 *
	 * @param array $fields The ACF Field data.
	 * @return array|bool $contact_data The CiviCRM Contact data.
	 */
	public function prepare_from_fields( $fields ) {

		// Init data for fields.
		$contact_data = [];

		// Bail if we have no field data to save.
		if ( empty( $fields ) ) {
			return $contact_data;
		}

		// Get all Fields that are handled separately.
		$fields_handled = $this->fields_handled_get();

		// Loop through the field data.
		foreach( $fields AS $field => $value ) {

			// Get the field settings.
			$settings = get_field_object( $field );

			// Get the CiviCRM Custom Field and Contact Field.
			$custom_field_id = $this->custom_field_id_get( $settings );
			$contact_field_name = $this->contact_field_name_get( $settings );

			// Do we have a synced Custom Field or Contact Field?
			if ( ! empty( $custom_field_id ) OR ! empty( $contact_field_name ) ) {

				// If it's a Custom Field.
				if ( ! empty( $custom_field_id ) ) {

					// Build Custom Field code.
					$code = 'custom_' . $custom_field_id;

				} else {

					// The Contact Field code is the setting.
					$code = $contact_field_name;

					// Skip if it's a Field that requires special handling.
					if ( in_array( $code, $fields_handled ) ) {
						continue;
					}

				}

				// Parse value by field type.
				$value = $this->plugin->acf->field->value_get_for_civicrm( $settings['type'], $value );

				// Add it to the field data.
				$contact_data[$code] = $value;

			}

		}

		// --<
		return $contact_data;

	}



	/**
	 * Update a CiviCRM Contact with data from ACF Fields.
	 *
	 * @since 0.3
	 *
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $fields The ACF Field data.
	 * @return array|bool $contact The CiviCRM Contact data, or false on failure.
	 */
	public function update_from_fields( $contact_id, $fields ) {

		// Build required data.
		$contact_data = $this->prepare_from_fields( $fields );

		// Add the Contact ID.
		$contact_data['id'] = $contact_id;

		// Update the Contact.
		$contact = $this->update( $contact_data );

		// --<
		return $contact;

	}



	// -------------------------------------------------------------------------



	/**
	 * Return the "CiviCRM Field" ACF Settings Field.
	 *
	 * @since 0.3
	 *
	 * @param array $custom_fields The Custom Fields to populate the ACF Field with.
	 * @param array $contact_fields The Contact Fields to populate the ACF Field with.
	 * @return array $field The ACF Field data array.
	 */
	public function acf_field_get( $custom_fields = [], $contact_fields = [] ) {

		// Build choices array for dropdown.
		$choices = [];

		// Build Contact Field choices array for dropdown.
		$contact_fields_label = esc_attr__( 'Contact Fields', 'civicrm-acf-integration' );
		foreach( $contact_fields AS $contact_field ) {
			$choices[$contact_fields_label]['caicontact_' . $contact_field['name']] = $contact_field['title'];
		}

		// Build Custom Field choices array for dropdown.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			$custom_fields_label = esc_attr( $custom_group_name );
			foreach( $custom_group AS $custom_field ) {
				$choices[$custom_fields_label]['caicustom_' . $custom_field['id']] = $custom_field['label'];
			}
		}

		/**
		 * Filter the choices to display in the "CiviCRM Field" select.
		 *
		 * @since 0.5
		 *
		 * @param array $choices The existing select options array.
		 * @param array $choices The modified select options array.
		 */
		$choices = apply_filters( 'civicrm_acf_integration_civicrm_field_choices', $choices );

		// Define field.
		$field = [
			'key' => $this->acf_field_key_get(),
			'label' => __( 'CiviCRM Field', 'civicrm-acf-integration' ),
			'name' => $this->acf_field_key_get(),
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Custom Field or Contact Field that this ACF Field should sync with. (Optional)', 'civicrm-acf-integration' ),
			'default_value' => '',
			'placeholder' => '',
			'allow_null' => 1,
			'multiple' => 0,
			'ui' => 0,
			'required' => 0,
			'return_format' => 'value',
			'parent' => $this->plugin->acf->field_group->placeholder_group_get(),
			'choices' => $choices,
		];

		// --<
		return $field;

	}



	/**
	 * Get the mapped Custom Field ID if present.
	 *
	 * @since 0.3.1
	 *
	 * @param array $field The existing field data array.
	 * @return int|bool $custom_field_id The numeric ID of the Custom Field, or false if none.
	 */
	public function custom_field_id_get( $field ) {

		// Init return.
		$custom_field_id = false;

		// Get the ACF CiviCRM Field key.
		$acf_field_key = $this->acf_field_key_get();

		// Get the mapped Custom Field ID if present.
		if ( isset( $field[$acf_field_key] ) ) {
			if ( false !== strpos( $field[$acf_field_key], 'caicustom_' ) ) {
				$custom_field_id = absint( str_replace( 'caicustom_', '', $field[$acf_field_key] ) );
			}
		}

		/**
		 * Filter the Custom Field ID.
		 *
		 * @since 0.5
		 *
		 * @param int $custom_field_id The existing Custom Field ID.
		 * @param array $field The array of ACF Field data.
		 * @return int $custom_field_id The modified Custom Field ID.
		 */
		$custom_field_id = apply_filters( 'civicrm_acf_integration_contact_custom_field_id_get', $custom_field_id, $field );

		// --<
		return $custom_field_id;

	}



	/**
	 * Get the mapped Contact Field name if present.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The existing field data array.
	 * @return str|bool $contact_field_name The name of the Contact Field, or false if none.
	 */
	public function contact_field_name_get( $field ) {

		// Init return.
		$contact_field_name = false;

		// Get the ACF CiviCRM Field key.
		$acf_field_key = $this->acf_field_key_get();

		// Set the mapped Contact Field name if present.
		if ( isset( $field[$acf_field_key] ) ) {
			if ( false !== strpos( $field[$acf_field_key], 'caicontact_' ) ) {
				$contact_field_name = strval( str_replace( 'caicontact_', '', $field[$acf_field_key] ) );
			}
		}

		/**
		 * Filter the Contact Field name.
		 *
		 * @since 0.5
		 *
		 * @param int $contact_field_name The existing Contact Field name.
		 * @param array $field The array of ACF Field data.
		 * @return int $contact_field_name The modified Contact Field name.
		 */
		$custom_field_id = apply_filters( 'civicrm_acf_integration_contact_contact_field_name_get', $contact_field_name, $field );

		// --<
		return $contact_field_name;

	}



	/**
	 * Getter method for the "CiviCRM Field" key.
	 *
	 * @since 0.4.1
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Field" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	// -------------------------------------------------------------------------



	/**
	 * Check with CiviCRM that this Contact can be viewed.
	 *
	 * @since 0.3
	 *
	 * @param int $contact_id The CiviCRM Contact ID to check.
	 * @return bool $permitted True if allowed, false otherwise.
	 */
	public function user_can_view( $contact_id ) {

		// Deny by default.
		$permitted = false;

		// Always deny if CiviCRM is not active.
		if ( ! $this->civicrm->is_initialised() ) {
			return $permitted;
		}

		// Check with CiviCRM that this Contact can be viewed.
		if ( CRM_Contact_BAO_Contact_Permission::allow( $contact_id, CRM_Core_Permission::VIEW ) ) {
			$permitted = true;
		}

		/**
		 * Return permission but allow overrides.
		 *
		 * @since 0.3
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param int $contact_id The CiviCRM Contact ID.
		 * @return bool $permitted True if allowed, false otherwise.
		 */
		return apply_filters( 'civicrm_acf_integration_user_can_view_contact', $permitted, $contact_id );

	}



} // Class ends.




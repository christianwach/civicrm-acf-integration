<?php
/**
 * CiviCRM Instant Messenger Class.
 *
 * Handles CiviCRM Instant Messenger functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.7.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Instant Messenger Class.
 *
 * A class that encapsulates CiviCRM Instant Messenger functionality.
 *
 * @since 0.7.3
 */
class CiviCRM_ACF_Integration_CiviCRM_Instant_Messenger extends CiviCRM_ACF_Integration_CiviCRM_Base {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * ACF Fields which must be handled separately.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var array $fields_handled The array of ACF Fields which must be handled separately.
	 */
	public $fields_handled = [
		'civicrm_im',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.7.3
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

		// Init parent.
		parent::__construct();

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.7.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.7.3
	 */
	public function register_hooks() {

		// Always register Mapper hooks.
		$this->register_mapper_hooks();

		// Add any Instant Messenger Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Intercept Post created from Contact events.
		add_action( 'civicrm_acf_integration_post_contact_sync_to_post', [ $this, 'contact_sync_to_post' ], 10 );

		// Maybe sync the Instant Messenger Record "Instant Messenger ID" to the ACF Subfields.
		add_action( 'civicrm_acf_integration_im_created', [ $this, 'maybe_sync_im_id' ], 10, 2 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.8
	 */
	public function register_mapper_hooks() {

		// Listen for events from our Mapper that require Instant Messenger updates.
		add_action( 'civicrm_acf_integration_mapper_im_created', [ $this, 'im_edited' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_im_edited', [ $this, 'im_edited' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_im_pre_delete', [ $this, 'im_pre_delete' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_im_deleted', [ $this, 'im_deleted' ], 10 );

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.8
	 */
	public function unregister_mapper_hooks() {

		// Remove all Mapper listeners.
		remove_action( 'civicrm_acf_integration_mapper_im_created', [ $this, 'im_edited' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_im_edited', [ $this, 'im_edited' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_im_pre_delete', [ $this, 'im_pre_delete' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_im_deleted', [ $this, 'im_deleted' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of WordPress params.
	 * @return bool $success True if updates were successful, or false on failure.
	 */
	public function fields_handled_update( $args ) {

		// Init success.
		$success = true;

		// Bail if we have no field data to save.
		if ( empty( $args['fields'] ) ) {
			return $success;
		}

		// Loop through the field data.
		foreach( $args['fields'] AS $field => $value ) {

			// Get the field settings.
			$settings = get_field_object( $field, $args['post_id'] );

			// Maybe update an Instant Messenger Record.
			$success = $this->field_handled_update( $field, $value, $args['contact']['id'], $settings, $args );

		}

		// --<
		return $success;

	}



	/**
	 * Update a CiviCRM Contact's Field with data from an ACF Field.
	 *
	 * These Fields require special handling because they are not part
	 * of the core Contact data.
	 *
	 * @since 0.7.3
	 *
	 * @param str $field The ACF Field selector.
	 * @param mixed $value The ACF Field value.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $settings The ACF Field settings.
	 * @param array $args The array of WordPress params.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function field_handled_update( $field, $value, $contact_id, $settings, $args ) {

		// Skip if it's not an ACF Field Type that this class handles.
		if ( ! in_array( $settings['type'], $this->fields_handled ) ) {
			return true;
		}

		// Update the Instant Messenger Records.
		$success = $this->ims_update( $value, $contact_id, $field, $args );

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the data for an Instant Messenger Record.
	 *
	 * @since 0.7.3
	 *
	 * @param int $im_id The numeric ID of the Instant Messenger Record.
	 * @param array $im The array of Instant Messenger Record data, or empty if none.
	 */
	public function im_get_by_id( $im_id ) {

		// Init return.
		$im = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $im;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id' => $im_id,
		];

		// Get Instant Messenger Record details via API.
		$result = civicrm_api( 'Im', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $im;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $im;
		}

 		// The result set should contain only one item.
		$im = array_pop( $result['values'] );

		// --<
		return $im;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Instant Messenger Records for a given Contact ID.
	 *
	 * @since 0.7.3
	 *
	 * @param int $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array $im_data The array of Instant Messenger Record data for the CiviCRM Contact.
	 */
	public function ims_get_for_contact( $contact_id ) {

		// Init return.
		$im_data = [];

		// Bail if we have no Contact ID.
		if ( empty( $contact_id ) ) {
			return $im_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $im_data;
		}

		// Define params to get queried Instant Messenger Records.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'contact_id' => $contact_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Im', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $im_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $im_data;
		}

		// The result set it what we want.
		$im_data = $result['values'];

		// --<
		return $im_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a Post is been synced from a Contact.
	 *
	 * Sync any associated ACF Fields mapped to Custom Fields.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function contact_sync_to_post( $args ) {

		// Get all Instant Messenger Records for this Contact.
		$data = $this->ims_get_for_contact( $args['objectId'] );

		// Bail if there are no Instant Messenger Record Fields.
		if ( empty( $data ) ) {
			return;
		}

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Instant Messenger Record Fields.
		if ( empty( $acf_fields['im'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['im'] AS $selector => $im_field ) {

			// Init Field value.
			$value = [];

			// Let's look at each Instant Messenger in turn.
			foreach( $data AS $im ) {

				// Convert to ACF Instant Messenger data.
				$acf_im = $this->prepare_from_civicrm( $im );

				// Add to Field value.
				$value[] = $acf_im;

			}

			// Now update Field.
			$this->plugin->acf->field->value_update( $selector, $value, $args['post_id'] );

		}

	}



	/**
	 * Update all of a CiviCRM Contact's Instant Messenger Records.
	 *
	 * @since 0.7.3
	 *
	 * @param array $values The array of Instant Messenger Records to update the Contact with.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $selector The ACF Field selector.
	 * @param array $args The array of WordPress params.
	 * @return array|bool $ims The array of Instant Messenger Records, or false on failure.
	 */
	public function ims_update( $values, $contact_id, $selector, $args = [] ) {

		// Init return.
		$ims = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $ims;
		}

		// Get the current Instant Messenger Records.
		$current = $this->ims_get_for_contact( $contact_id );

		// If there are no existing Instant Messenger Records.
		if ( empty( $current ) ) {

			// Create an Instant Messenger Record from each value.
			foreach( $values AS $key => $value ) {

				// Build required data.
				$im_data = $this->prepare_from_field( $value );

				// Okay, let's do it.
				$im = $this->update( $contact_id, $im_data );

				// Add to return array.
				$ims[] = $im;

				// Make an array of our params.
				$params = [
					'key' => $key,
					'value' => $value,
					'im' => $im,
					'contact_id' => $contact_id,
					'selector' => $selector,
				];

				/**
				 * Broadcast that an Instant Messenger Record has been created.
				 *
				 * We use this internally to update the ACF Field with the Instant Messenger ID.
				 *
				 * @since 0.7.3
				 *
				 * @param array $params The Instant Messenger data.
				 * @param array $args The array of WordPress params.
				 */
				do_action( 'civicrm_acf_integration_im_created', $params, $args );

			}

			// No need to go any further.
			return $ims;

		}

		// We have existing Instant Messenger Records.
		$actions = [
			'create' => [],
			'update' => [],
			'delete' => [],
		];

		// Let's look at each ACF Record and check its Instant Messenger ID.
		foreach( $values AS $key => $value ) {

			// New Records have no Instant Messenger ID.
			if ( empty( $value['field_im_id'] ) ) {
				$actions['create'][$key] = $value;
				continue;
			}

			// Records to update have an Instant Messenger ID.
			if ( ! empty( $value['field_im_id'] ) ) {
				$actions['update'][$key] = $value;
				continue;
			}

		}

		// Grab the ACF Instant Messenger ID values.
		$acf_im_ids = wp_list_pluck( $values, 'field_im_id' );

		// Sanitise array contents.
		array_walk( $acf_im_ids, function( &$item ) {
			$item = (int) trim( $item );
		} );

		// Records to delete are missing from the ACF data.
		foreach( $current AS $current_im ) {
			if ( ! in_array( $current_im['id'], $acf_im_ids ) ) {
				$actions['delete'][] = $current_im['id'];
				continue;
			}
		}

		// Create CiviCRM Instant Messenger Records.
		foreach( $actions['create'] AS $key => $value ) {

			// Build required data.
			$im_data = $this->prepare_from_field( $value );

			// Okay, let's do it.
			$im = $this->update( $contact_id, $im_data );

			// Add to return array.
			$ims[] = $im;

			// Make an array of our params.
			$params = [
				'key' => $key,
				'value' => $value,
				'im' => $im,
				'contact_id' => $contact_id,
				'selector' => $selector,
			];

			/**
			 * Broadcast that an Instant Messenger Record has been created.
			 *
			 * We use this internally to update the ACF Field with the Instant Messenger ID.
			 *
			 * @since 0.7.3
			 *
			 * @param array $params The Instant Messenger data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'civicrm_acf_integration_im_created', $params, $args );

		}

		// Update CiviCRM Instant Messenger Records.
		foreach( $actions['update'] AS $key => $value ) {

			// Build required data.
			$im_data = $this->prepare_from_field( $value, $value['field_im_id'] );

			// Okay, let's do it.
			$im = $this->update( $contact_id, $im_data );

			// Add to return array.
			$ims[] = $im;

			// Make an array of our params.
			$params = [
				'key' => $key,
				'value' => $value,
				'im' => $im,
				'contact_id' => $contact_id,
				'selector' => $selector,
			];

			/**
			 * Broadcast that an Instant Messenger Record has been updated.
			 *
			 * @since 0.7.3
			 *
			 * @param array $params The Instant Messenger data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'civicrm_acf_integration_im_updated', $params, $args );

		}

		// Delete CiviCRM Instant Messenger Records.
		foreach( $actions['delete'] AS $im_id ) {

			// Okay, let's do it.
			$im = $this->delete( $im_id );

			// Make an array of our params.
			$params = [
				'im_id' => $im_id,
				'im' => $im,
				'contact_id' => $contact_id,
				'selector' => $selector,
			];

			/**
			 * Broadcast that an Instant Messenger Record has been deleted.
			 *
			 * @since 0.7.3
			 *
			 * @param array $params The Instant Messenger data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'civicrm_acf_integration_im_deleted', $params, $args );

		}

	}



	/**
	 * Prepare the CiviCRM Instant Messenger Record from an ACF Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $value The array of Instant Messenger Record data in the ACF Field.
	 * @param int $im_id The numeric ID of the Instant Messenger Record (or null if new).
	 * @return array $im_data The CiviCRM Instant Messenger Record data.
	 */
	public function prepare_from_field( $value, $im_id = null ) {

		// Init required data.
		$im_data = [];

		// Maybe add the Instant Messenger ID.
		if ( ! empty( $im_id ) ) {
			$im_data['id'] = $im_id;
		}

		// Convert ACF data to CiviCRM data.
		$im_data['is_primary'] = empty( $value['field_im_primary'] ) ? '0' : '1';
		$im_data['location_type_id'] = (int) $value['field_im_location'];
		$im_data['provider_id'] = (int) $value['field_im_provider'];
		$im_data['name'] = trim( $value['field_im_name'] );

		// --<
		return $im_data;

	}



	/**
	 * Prepare the ACF Field data from a CiviCRM Instant Messenger Record.
	 *
	 * @since 0.7.3
	 *
	 * @param array $value The array of Instant Messenger Record data in CiviCRM.
	 * @return array $im_data The ACF Instant Messenger data.
	 */
	public function prepare_from_civicrm( $value ) {

		// Init required data.
		$im_data = [];

		// Maybe cast as an object.
		if ( ! is_object( $value ) ) {
			$value = (object) $value;
		}

		// Convert CiviCRM data to ACF data.
		$im_data['field_im_name'] = trim( $value->name );
		$im_data['field_im_location'] = (int) $value->location_type_id;
		$im_data['field_im_provider'] = (int) $value->provider_id;
		$im_data['field_im_primary'] = empty( $value->is_primary ) ? '0' : '1';
		$im_data['field_im_id'] = (int) $value->id;

		// --<
		return $im_data;

	}



	/**
	 * Update a CiviCRM Contact's Instant Messenger Record.
	 *
	 * If you want to "create" an Instant Messenger Record, do not pass
	 * $data['id'] in. The presence of an ID will cause an update to that
	 * Instant Messenger Record.
	 *
	 * @since 0.7.3
	 *
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $data The Instant Messenger data to update the Contact with.
	 * @return array|bool $im The array of Instant Messenger Record data, or false on failure.
	 */
	public function update( $contact_id, $data ) {

		// Init return.
		$im = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $im;
		}

		// Define params to create new Instant Messenger Record.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
		] + $data;

		// Call the API.
		$result = civicrm_api( 'Im', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $im;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $im;
		}

		// The result set should contain only one item.
		$im = array_pop( $result['values'] );

		// --<
		return $im;

	}



	/**
	 * Delete an Instant Messenger Record in CiviCRM.
	 *
	 * @since 0.7.3
	 *
	 * @param int $im_id The numeric ID of the Instant Messenger Record.
	 * @return bool $success True if successfully deleted, or false on failure.
	 */
	public function delete( $im_id ) {

		// Init return.
		$success = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $success;
		}

		// Define params to delete this Instant Messenger Record.
		$params = [
			'version' => 3,
			'id' => $im_id,
		];

		// Call the API.
		$result = civicrm_api( 'Im', 'delete', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $success;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $success;
		}

		// The result set should contain only one item.
		$success = ( $result['values'] == '1' ) ? true : false;

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Instant Messenger Record has been updated.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function im_edited( $args ) {

		// Grab the Instant Messenger Record data.
		$civicrm_im = $args['objectRef'];

		// Bail if this is not a Contact's Instant Messenger Record.
		if ( empty( $civicrm_im->contact_id ) ) {
			return;
		}

		// Process the Instant Messenger Record.
		$this->im_process( $civicrm_im, $args );

	}



	/**
	 * A CiviCRM Contact's Instant Messenger Record is about to be deleted.
	 *
	 * Before an Instant Messenger Record is deleted, we need to retrieve the
	 * Instant Messenger Record because the data passed via "civicrm_post" only
	 *  contains the ID of the Instant Messenger Record.
	 *
	 * This is not required when creating or editing an Instant Messenger Record.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function im_pre_delete( $args ) {

		// Always clear properties if set previously.
		if ( isset( $this->im_pre ) ) {
			unset( $this->im_pre );
		}

		// We just need the Instant Messenger ID.
		$im_id = (int) $args['objectId'];

		// Grab the Instant Messenger Record data from the database.
		$im_pre = $this->im_get_by_id( $im_id );

		// Maybe cast previous Instant Messenger Record data as object and stash in a property.
		if ( ! is_object( $im_pre ) ) {
			$this->im_pre = (object) $im_pre;
		} else {
			$this->im_pre = $im_pre;
		}

	}



	/**
	 * A CiviCRM Instant Messenger Record has just been deleted.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function im_deleted( $args ) {

		// Bail if we don't have a pre-delete Instant Messenger Record.
		if ( ! isset( $this->im_pre ) ) {
			return;
		}

		// We just need the Instant Messenger ID.
		$im_id = (int) $args['objectId'];

		// Sanity check.
		if ( $im_id != $this->im_pre->id ) {
			return;
		}

		// Bail if this is not a Contact's Instant Messenger Record.
		if ( empty( $this->im_pre->contact_id ) ) {
			return;
		}

		// Process the Instant Messenger Record.
		$this->im_process( $this->im_pre, $args );

	}



	/**
	 * Process a CiviCRM Instant Messenger Record.
	 *
	 * @since 0.7.3
	 *
	 * @param object $im The CiviCRM Instant Messenger Record object.
	 * @param array $args The array of CiviCRM params.
	 */
	public function im_process( $im, $args ) {

		// Convert to ACF Instant Messenger data.
		$acf_im = $this->prepare_from_civicrm( $im );

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $im->contact_id );

		// Get originating Entity.
		$entity = $this->plugin->mapper->entity_get();

		// Test if any of this Contact's Contact Types is mapped to a Post Type.
		$post_types = $this->plugin->civicrm->contact->is_mapped( $contact, 'create' );
		if ( $post_types !== false ) {

			// Handle each Post Type in turn.
			foreach( $post_types AS $post_type ) {

				// Get the Post ID for this Contact.
				$post_id = $this->plugin->civicrm->contact->is_mapped_to_post( $contact, $post_type );

				// Skip if not mapped or Post doesn't yet exist.
				if ( $post_id === false ) {
					continue;
				}

				// Exclude "reverse" edits when a Post is the originator.
				if ( $entity['entity'] === 'post' AND $post_id == $entity['id'] ) {
					continue;
				}

				// Update the ACF Fields for this Post.
				$this->fields_update( $post_id, $im, $acf_im, $args );

			}

		}

		/**
		 * Broadcast that an Instant Messenger ACF Field may have been edited.
		 *
		 * @since 0.8
		 *
		 * @param array $contact The array of CiviCRM Contact data.
		 * @param object $im The CiviCRM Instant Messenger Record object.
		 * @param array $acf_im The ACF Instant Messenger Record array.
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_im_im_update', $contact, $im, $acf_im, $args );

	}



	/**
	 * Update Instant Messenger ACF Fields on an Entity mapped to a Contact ID.
	 *
	 * @since 0.8
	 *
	 * @param int|str $post_id The ACF "Post ID".
	 * @param object $im The CiviCRM Instant Messenger Record object.
	 * @param array $acf_im The ACF Instant Messenger Record array.
	 * @param array $args The array of CiviCRM params.
	 */
	public function fields_update( $post_id, $im, $acf_im, $args ) {

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if there are no Instant Messenger Record Fields.
		if ( empty( $acf_fields['im'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['im'] AS $selector => $im_field ) {

			// Get existing Field value.
			$existing = get_field( $selector, $post_id );

			// Before applying edit, make some checks.
			if ( $args['op'] == 'edit' ) {

				// If there is no existing Field value, treat as a 'create' op.
				if ( empty( $existing ) ) {
					$args['op'] = 'create';
				} else {

					// Grab the ACF Instant Messenger ID values.
					$acf_im_ids = wp_list_pluck( $existing, 'field_im_id' );

					// Sanitise array contents.
					array_walk( $acf_im_ids, function( &$item ) {
						$item = (int) trim( $item );
					} );

					// If the ID is missing, treat as a 'create' op.
					if ( ! in_array( $im->id, $acf_im_ids ) ) {
						$args['op'] = 'create';
					}

				}

			}

			// Process array record.
			switch( $args['op'] ) {

				case 'create' :

					// Make sure no other Instant Messenger is Primary if this one is.
					if ( $acf_im['field_im_primary'] == '1' AND ! empty( $existing ) ) {
						foreach( $existing AS $key => $record ) {
							$existing[$key]['field_im_primary'] = '0';
						}
					}

					// Add array record.
					$existing[] = $acf_im;

					break;

				case 'edit' :

					// Make sure no other Instant Messenger is Primary if this one is.
					if ( $acf_im['field_im_primary'] == '1' ) {
						foreach( $existing AS $key => $record ) {
							$existing[$key]['field_im_primary'] = '0';
						}
					}

					// Overwrite array record.
					foreach( $existing AS $key => $record ) {
						if ( $im->id == $record['field_im_id'] ) {
							$existing[$key] = $acf_im;
							break;
						}
					}

					break;

				case 'delete' :

					// Remove array record.
					foreach( $existing AS $key => $record ) {
						if ( $im->id == $record['field_im_id'] ) {
							unset( $existing[$key] );
							break;
						}
					}

					break;

			}

			// Now update Field.
			$this->plugin->acf->field->value_update( $selector, $existing, $post_id );

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Instant Messenger Locations that are defined in CiviCRM.
	 *
	 * @since 0.7.3
	 *
	 * @return array $location_types The array of possible Instant Messenger Locations.
	 */
	public function location_types_get() {

		// Init return.
		$location_types = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $location_types;
		}

		// Params to get all Location Types.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'options' => [
				'limit' => 0,
			],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'LocationType', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $location_types;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $location_types;
		}

		// Assign results to return.
		$location_types = $result['values'];

		// --<
		return $location_types;

	}



	/**
	 * Get the Instant Messenger Locations that can be mapped to an ACF Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $location_types The array of possible Instant Messenger Locations.
	 */
	public function location_types_get_for_acf_field( $field ) {

		// Init return.
		$location_types = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $location_types;
		}

		// Get Location Types.
		$location_types = $this->location_types_get();

		/**
		 * Filter the retrieved Location Types.
		 *
		 * @since 0.7.3
		 *
		 * @param array $location_types The retrieved array of Location Types.
		 * @param array $field The ACF Field data array.
		 * @return array $location_types The modified array of Location Types.
		 */
		$location_types = apply_filters(
			'civicrm_acf_integration_im_location_types_get_for_acf_field',
			$location_types, $field
		);

		// --<
		return $location_types;

	}



	/**
	 * Get the Instant Messenger Providers that are defined in CiviCRM.
	 *
	 * @since 0.7.3
	 *
	 * @return array $im_providers The array of possible Instant Messenger Providers.
	 */
	public function im_providers_get() {

		// Only do this once per Field Group.
		static $pseudocache;
		if ( isset( $pseudocache ) ) {
			return $pseudocache;
		}

		// Init return.
		$im_providers = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $im_providers;
		}

		// Get the Instant Messenger Providers array.
		$im_provider_ids = CRM_Core_PseudoConstant::get( 'CRM_Core_DAO_IM', 'provider_id' );

		// Bail if there are no results.
		if ( empty( $im_provider_ids ) ) {
			return $im_providers;
		}

		// Assign to return.
		$im_providers = $im_provider_ids;

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache ) ) {
			$pseudocache = $im_providers;
		}

		// --<
		return $im_providers;

	}



	/**
	 * Get the Instant Messenger Providers that can be mapped to an ACF Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $im_providers The array of possible Instant Messenger Providers.
	 */
	public function im_providers_get_for_acf_field( $field ) {

		// Init return.
		$im_providers = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $im_providers;
		}

		// Get the Instant Messenger Providers array.
		$im_provider_ids = $this->im_providers_get();

		// Bail if there are no results.
		if ( empty( $im_provider_ids ) ) {
			return $im_providers;
		}

		// Assign to return.
		$im_providers = $im_provider_ids;

		// --<
		return $im_providers;

	}



	/**
	 * Add any Instant Messenger Fields that are attached to a Post.
	 *
	 * @since 0.7.3
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field The ACF Field.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Add if it has a reference to an Instant Messenger Field.
		if ( ! empty( $field['type'] ) AND $field['type'] == 'civicrm_im' ) {
			$acf_fields['im'][$field['name']] = $field['type'];
		}

		// --<
		return $acf_fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Sync the CiviCRM "Instant Messenger ID" to the ACF Fields on a WordPress Post.
	 *
	 * @since 0.7.3
	 *
	 * @param array $params The Instant Messenger data.
	 * @param array $args The array of WordPress params.
	 */
	public function maybe_sync_im_id( $params, $args ) {

		// Get Entity reference.
		$entity = $this->plugin->acf->field->entity_type_get( $args['post_id'] );

		// Check permissions if it's a Post.
		if ( $entity === 'post' ) {
			if ( ! current_user_can( 'edit_post', $args['post_id'] ) ) {
				return;
			}
		}

		// Check permissions if it's a User.
		if ( $entity === 'user' ) {
			if ( ! current_user_can( 'edit_user', $args['user_id'] ) ) {
				return;
			}
		}

		// Maybe cast Instant Messenger data as an object.
		if ( ! is_object( $params['im'] ) ) {
			$params['im'] = (object) $params['im'];
		}

		// Get existing Field value.
		$existing = get_field( $params['selector'], $args['post_id'] );

		// Add Instant Messenger ID and overwrite array element.
		if ( ! empty( $existing[$params['key']] ) ) {
			$params['value']['field_im_id'] = $params['im']->id;
			$existing[$params['key']] = $params['value'];
		}

		// Now update Field.
		$this->plugin->acf->field->value_update( $params['selector'], $existing, $args['post_id'] );

	}



} // Class ends.





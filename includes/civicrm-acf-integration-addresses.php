<?php
/**
 * CiviCRM Addresses Class.
 *
 * Handles CiviCRM Addresses functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Addresses Class.
 *
 * A class that encapsulates CiviCRM Addresses functionality.
 *
 * @since 0.8.2
 */
class CiviCRM_ACF_Integration_CiviCRM_Addresses extends CiviCRM_ACF_Integration_CiviCRM_Base {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Addresses" field key in the ACF Field data.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Addresses" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_addresses';

	/**
	 * Fields which must be handled separately.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array $fields_handled The array of Fields which must be handled separately.
	 */
	public $fields_handled = [
		'civicrm_address',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.8.2
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
	 * @since 0.8.2
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.8.2
	 */
	public function register_hooks() {

		// Always register Mapper hooks.
		$this->register_mapper_hooks();

		// Add any Addresses Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Intercept Post-Contact sync event.
		add_action( 'civicrm_acf_integration_post_contact_sync_to_post', [ $this, 'contact_sync_to_post' ], 10 );

		// Maybe sync the Address ID to the ACF Subfields.
		add_action( 'civicrm_acf_integration_address_created', [ $this, 'maybe_sync_address_data' ], 10, 2 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.8.2
	 */
	public function register_mapper_hooks() {

		// Listen for events from our Mapper that require Address updates.
		add_action( 'civicrm_acf_integration_mapper_address_created', [ $this, 'address_edited' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_address_edited', [ $this, 'address_edited' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_address_pre_delete', [ $this, 'address_pre_delete' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_address_deleted', [ $this, 'address_deleted' ], 10 );

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.8.2
	 */
	public function unregister_mapper_hooks() {

		// Remove all Mapper listeners.
		remove_action( 'civicrm_acf_integration_mapper_address_created', [ $this, 'address_edited' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_address_edited', [ $this, 'address_edited' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_address_pre_delete', [ $this, 'address_pre_delete' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_address_deleted', [ $this, 'address_deleted' ], 10 );


	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * @since 0.8.2
	 *
	 * @param array $args The array of WordPress params.
	 * @return bool True if updates were successful, or false on failure.
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

			// Maybe update an Address Record.
			$success = $this->field_handled_update( $field, $value, $args['contact']['id'], $settings, $args );

		}

		// --<
		return $success;

	}



	/**
	 * Update a CiviCRM Contact's Addresses with data from an ACF Field.
	 *
	 * These Fields require special handling because they are not part
	 * of the core Contact data.
	 *
	 * @since 0.8.2
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

		// Update the Address Records.
		$success = $this->addresses_update( $value, $contact_id, $field, $args );

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a Post has been updated from a Contact via the Mapper.
	 *
	 * Sync any associated ACF Fields mapped to built-in Contact Fields.
	 *
	 * @since 0.8.2
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function contact_sync_to_post( $args ) {

		// Get all Address Records for this Contact.
		$data = $this->civicrm->address->addresses_get_by_contact_id( $args['objectId'] );

		// Bail if there are no Address Records.
		if ( empty( $data ) ) {
			return;
		}

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Address Record Fields.
		if ( empty( $acf_fields['addresses'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['addresses'] AS $selector => $address_field ) {

			// Init Field value.
			$value = [];

			// Let's look at each Address in turn.
			foreach( $data AS $address ) {

				// Convert to ACF Address data.
				$acf_address = $this->prepare_from_civicrm( $address );

				// Add to Field value.
				$value[] = $acf_address;

			}

			// Now update the ACF Field.
			$this->plugin->acf->field->value_update( $selector, $value, $args['post_id'] );

		}

	}



	/**
	 * Prepare the ACF Field data from a CiviCRM Address.
	 *
	 * @since 0.8.2
	 *
	 * @param array $value The array of Address Record data in CiviCRM.
	 * @return array $address_data The ACF Address data.
	 */
	public function prepare_from_civicrm( $value ) {

		// Init required data.
		$address_data = [];

		// Maybe cast as an object.
		if ( ! is_object( $value ) ) {
			$value = (object) $value;
		}

		// Init optional data.
		$address_1 = empty( $value->supplemental_address_1 ) ? '' : trim( $value->supplemental_address_1 );
		$address_2 = empty( $value->supplemental_address_2 ) ? '' : trim( $value->supplemental_address_2 );
		$address_3 = empty( $value->supplemental_address_3 ) ? '' : trim( $value->supplemental_address_3 );

		// Convert CiviCRM data to ACF data.
		$address_data['field_address_location_type'] = (int) $value->location_type_id;
		$address_data['field_address_primary'] = empty( $value->is_primary ) ? '0' : '1';
		$address_data['field_address_billing'] = empty( $value->is_billing ) ? '0' : '1';
		$address_data['field_address_street_address'] = trim( $value->street_address );
		$address_data['field_address_supplemental_address_1'] = $this->plugin->civicrm->denullify( $address_1 );
		$address_data['field_address_supplemental_address_2'] = $this->plugin->civicrm->denullify( $address_2 );
		$address_data['field_address_supplemental_address_3'] = $this->plugin->civicrm->denullify( $address_3 );
		$address_data['field_address_city'] = empty( $value->city ) ? '' : trim( $value->city );
		$address_data['field_address_postal_code'] = empty( $value->postal_code ) ? '' : trim( $value->postal_code );
		$address_data['field_address_country_id'] = empty( $value->country_id ) ? '' : (int) $value->country_id;
		$address_data['field_address_state_province_id'] = empty( $value->state_province_id ) ? '' : (int) $value->state_province_id;
		$address_data['field_address_geo_code_1'] = empty( $value->geo_code_1 ) ? '' : (float) $value->geo_code_1;
		$address_data['field_address_geo_code_2'] = empty( $value->geo_code_2 ) ? '' : (float) $value->geo_code_2;
		$address_data['field_address_manual_geo_code'] = empty( $value->manual_geo_code ) ? '0' : '1';
		$address_data['field_address_id'] = (int) $value->id;

		// --<
		return $address_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update all of a CiviCRM Contact's Address Records.
	 *
	 * @since 0.8.2
	 *
	 * @param array $values The array of Address Record arrays to update the Contact with.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $selector The ACF Field selector.
	 * @param array $args The array of WordPress params.
	 * @return array|bool $addresses The array of Address Record data, or false on failure.
	 */
	public function addresses_update( $values, $contact_id, $selector, $args = [] ) {

		// Init return.
		$addresses = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $addresses;
		}

		// Get the current Address Records.
		$current = $this->civicrm->address->addresses_get_by_contact_id( $contact_id );

		// If there are no existing Address Records.
		if ( empty( $current ) ) {

			// Create a Address Record from each value.
			foreach( $values AS $key => $value ) {

				// Build required data.
				$address_data = $this->prepare_from_field( $value );

				// Okay, let's do it.
				$address = $this->update( $contact_id, $address_data );

				// Add to return array.
				$addresses[] = $address;

				// Make an array of our params.
				$params = [
					'key' => $key,
					'value' => $value,
					'address' => $address,
					'contact_id' => $contact_id,
					'selector' => $selector,
				];

				/**
				 * Broadcast that a Address Record has been created.
				 *
				 * We use this internally to update the ACF Field with the Address ID.
				 *
				 * @since 0.8.2
				 *
				 * @param array $params The Address data.
				 * @param array $args The array of WordPress params.
				 */
				do_action( 'civicrm_acf_integration_address_created', $params, $args );

			}

			// No need to go any further.
			return $addresses;

		}

		// We have existing Address Records.
		$actions = [
			'create' => [],
			'update' => [],
			'delete' => [],
		];

		// Let's look at each ACF Record and check its Address ID.
		foreach( $values AS $key => $value ) {

			// New Records have no Address ID.
			if ( empty( $value['field_address_id'] ) ) {
				$actions['create'][$key] = $value;
				continue;
			}

			// Records to update have a Address ID.
			if ( ! empty( $value['field_address_id'] ) ) {
				$actions['update'][$key] = $value;
				continue;
			}

		}

		// Grab the ACF Address ID values.
		$acf_address_ids = wp_list_pluck( $values, 'field_address_id' );

		// Sanitise array contents.
		array_walk( $acf_address_ids, function( &$item ) {
			$item = (int) trim( $item );
		} );

		// Records to delete are missing from the ACF data.
		foreach( $current AS $current_address ) {
			if ( ! in_array( $current_address->id, $acf_address_ids ) ) {
				$actions['delete'][] = $current_address->id;
				continue;
			}
		}

		// Create CiviCRM Address Records.
		foreach( $actions['create'] AS $key => $value ) {

			// Build required data.
			$address_data = $this->prepare_from_field( $value );

			// Okay, let's do it.
			$address = $this->civicrm->address->update( $contact_id, $address_data );

			// Add to return array.
			$addresses[] = $address;

			// Make an array of our params.
			$params = [
				'key' => $key,
				'value' => $value,
				'address' => $address,
				'contact_id' => $contact_id,
				'selector' => $selector,
			];

			/**
			 * Broadcast that a Address Record has been created.
			 *
			 * We use this internally to update the ACF Field with the Address ID.
			 *
			 * @since 0.8.2
			 *
			 * @param array $params The Address data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'civicrm_acf_integration_address_created', $params, $args );

		}

		// Update CiviCRM Address Records.
		foreach( $actions['update'] AS $key => $value ) {

			// Build required data.
			$address_data = $this->prepare_from_field( $value, $value['field_address_id'] );

			// Okay, let's do it.
			$address = $this->civicrm->address->update( $contact_id, $address_data );

			// Add to return array.
			$addresses[] = $address;

			// Make an array of our params.
			$params = [
				'key' => $key,
				'value' => $value,
				'address' => $address,
				'contact_id' => $contact_id,
				'selector' => $selector,
			];

			/**
			 * Broadcast that a Address Record has been updated.
			 *
			 * @since 0.8.2
			 *
			 * @param array $params The Address data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'civicrm_acf_integration_address_updated', $params, $args );

		}

		// Delete CiviCRM Address Records.
		foreach( $actions['delete'] AS $address_id ) {

			// Okay, let's do it.
			$address = $this->civicrm->address->delete( $address_id );

			// Make an array of our params.
			$params = [
				'address_id' => $address_id,
				'address' => $address,
				'contact_id' => $contact_id,
				'selector' => $selector,
			];

			/**
			 * Broadcast that a Address Record has been deleted.
			 *
			 * @since 0.8.2
			 *
			 * @param array $params The Address data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'civicrm_acf_integration_address_deleted', $params, $args );

		}

	}



	/**
	 * Prepare the CiviCRM Address Record data from an ACF Field.
	 *
	 * @since 0.8.2
	 *
	 * @param array $value The array of Address data in the ACF Field.
	 * @param int $address_id The numeric ID of the Address Record (or null if new).
	 * @return array $address_data The CiviCRM Address Record data.
	 */
	public function prepare_from_field( $value, $address_id = null ) {

		// Init required data.
		$address_data = [];

		// Maybe add the Address ID.
		if ( ! empty( $address_id ) ) {
			$address_data['id'] = $address_id;
		}

		// Convert ACF data to CiviCRM data.
		$address_data['location_type_id'] = (int) $value['field_address_location_type'];
		$address_data['is_primary'] = empty( $value['field_address_primary'] ) ? '0' : '1';
		$address_data['is_billing'] = empty( $value['field_address_billing'] ) ? '0' : '1';
		$address_data['street_address'] = trim( $value['field_address_street_address'] );
		$address_data['supplemental_address_1'] = trim( $value['field_address_supplemental_address_1'] );
		$address_data['supplemental_address_2'] = trim( $value['field_address_supplemental_address_2'] );
		$address_data['supplemental_address_3'] = trim( $value['field_address_supplemental_address_3'] );
		$address_data['city'] = trim( $value['field_address_city'] );
		$address_data['postal_code'] = trim( $value['field_address_postal_code'] );
		$address_data['country_id'] = empty( $value['field_address_country_id'] ) ? '' :
									  (int) $value['field_address_country_id'];
		$address_data['state_province_id'] = empty( $value['field_address_state_province_id'] ) ? '' :
											 (int) $value['field_address_state_province_id'];
		$address_data['geo_code_1'] = (float) trim( $value['field_address_geo_code_1'] );
		$address_data['geo_code_2'] = (float) trim( $value['field_address_geo_code_2'] );
		$address_data['manual_geo_code'] = empty( $value['field_address_manual_geo_code'] ) ? '0' : '1';

		// --<
		return $address_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Address Record has been updated.
	 *
	 * @since 0.8.2
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_edited( $args ) {

		// Grab the Address Record data.
		$address = $args['objectRef'];

		// Bail if this is not a Contact's Address Record.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Process the Address Record.
		$this->address_process( $address, $args );

	}



	/**
	 * A CiviCRM Contact's Address Record is about to be deleted.
	 *
	 * Before a Address Record is deleted, we need to retrieve the Address Record
	 * because the data passed via "civicrm_post" only contains the ID of the
	 * Address Record.
	 *
	 * This is not required when creating or editing a Address Record.
	 *
	 * @since 0.8.2
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_pre_delete( $args ) {

		// Always clear properties if set previously.
		if ( isset( $this->address_pre ) ) {
			unset( $this->address_pre );
		}

		// We just need the Address ID.
		$address_id = (int) $args['objectId'];

		// Grab the Address Record data from the database.
		$address_pre = $this->civicrm->address->address_get_by_id( $address_id );

		// Maybe cast previous Address Record data as object and stash in a property.
		if ( ! is_object( $address_pre ) ) {
			$this->address_pre = (object) $address_pre;
		} else {
			$this->address_pre = $address_pre;
		}

	}



	/**
	 * A CiviCRM Address Record has just been deleted.
	 *
	 * @since 0.8.2
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_deleted( $args ) {

		// Bail if we don't have a pre-delete Address Record.
		if ( ! isset( $this->address_pre ) ) {
			return;
		}

		// We just need the Address ID.
		$address_id = (int) $args['objectId'];

		// Sanity check.
		if ( $address_id != $this->address_pre->id ) {
			return;
		}

		// Bail if this is not a Contact's Address Record.
		if ( empty( $this->address_pre->contact_id ) ) {
			return;
		}

		// Process the Address Record.
		$this->address_process( $this->address_pre, $args );

	}



	/**
	 * Process a CiviCRM Address Record.
	 *
	 * @since 0.8.2
	 *
	 * @param object $address The CiviCRM Address Record object.
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_process( $address, $args ) {

		// Convert to ACF Address data.
		$acf_address = $this->prepare_from_civicrm( $address );

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $address->contact_id );

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
				$this->fields_update( $post_id, $address, $acf_address, $args );

			}

		}

		/**
		 * Broadcast that a Address ACF Field may have been edited.
		 *
		 * @since 0.8.2
		 *
		 * @param array $contact The array of CiviCRM Contact data.
		 * @param object $address The CiviCRM Address Record object.
		 * @param array $acf_address The ACF Address Record array.
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_addresses_address_update', $contact, $address, $acf_address, $args );

	}



	/**
	 * Update Address ACF Fields on an Entity mapped to a Contact ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int|str $post_id The ACF "Post ID".
	 * @param object $address The CiviCRM Address Record object.
	 * @param array $acf_address The ACF Address Record array.
	 * @param array $args The array of CiviCRM params.
	 */
	public function fields_update( $post_id, $address, $acf_address, $args ) {

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if there are no Address Record Fields.
		if ( empty( $acf_fields['addresses'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['addresses'] AS $selector => $address_field ) {

			// Get existing Field value.
			$existing = get_field( $selector, $post_id );

			// Before applying edit, make some checks.
			if ( $args['op'] == 'edit' ) {

				// If there is no existing Field value, treat as a 'create' op.
				if ( empty( $existing ) ) {
					$args['op'] = 'create';
				} else {

					// Grab the ACF Address ID values.
					$acf_address_ids = wp_list_pluck( $existing, 'field_address_id' );

					// Sanitise array contents.
					array_walk( $acf_address_ids, function( &$item ) {
						$item = (int) trim( $item );
					} );

					// If the ID is missing, treat as a 'create' op.
					if ( ! in_array( $address->id, $acf_address_ids ) ) {
						$args['op'] = 'create';
					}

				}

			}

			// Process array record.
			switch( $args['op'] ) {

				case 'create' :

					// Make sure no other Address is Primary if this one is.
					if ( $acf_address['field_address_primary'] == '1' AND ! empty( $existing ) ) {
						foreach( $existing AS $key => $record ) {
							$existing[$key]['field_address_id'] = '0';
						}
					}

					// Add array record.
					$existing[] = $acf_address;

					break;

				case 'edit' :

					// Overwrite array record.
					foreach( $existing AS $key => $record ) {
						if ( $address->id == $record['field_address_id'] ) {
							$existing[$key] = $acf_address;
							break;
						}
					}

					break;

				case 'delete' :

					// Remove array record.
					foreach( $existing AS $key => $record ) {
						if ( $address->id == $record['field_address_id'] ) {
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
	 * Get the Location Types that can be mapped to an ACF Field.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $location_types The array of possible Location Types.
	 */
	public function get_for_acf_field( $field ) {

		// Init return.
		$location_types = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $location_types;
		}

		// Get all Location Types.
		$types = $this->civicrm->address->location_types_get();

		// Bail if there are none.
		if ( empty( $types ) ) {
			return $location_types;
		}

		/**
		 * Filter the retrieved Location Types.
		 *
		 * @since 0.8.2
		 *
		 * @param array $types The retrieved array of Location Types.
		 * @param array $field The ACF Field data array.
		 * @return array $types The modified array of Location Types.
		 */
		$location_types = apply_filters(
			'civicrm_acf_integration_addresses_location_types_get_for_acf_field',
			$types, $field
		);

		// --<
		return $location_types;

	}



	// -------------------------------------------------------------------------



	/**
	 * Getter method for the "CiviCRM Addresses" key.
	 *
	 * @since 0.8.2
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Addresses" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	/**
	 * Add any Address Fields that are attached to a Post.
	 *
	 * @since 0.8.2
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Add if it has a reference to an Addresses Field.
		if ( ! empty( $field['type'] ) AND $field['type'] == 'civicrm_address' ) {
			$acf_fields['addresses'][$field['name']] = $field['type'];
		}

		// --<
		return $acf_fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Sync new CiviCRM Address data back to the ACF Fields on a WordPress Post.
	 *
	 * The Address ID needs to be reverse-synced to the relevant array element
	 * in the field. Addresses may be run through geolocation, so also include
	 * that data if it exists.
	 *
	 * @since 0.8.2
	 *
	 * @param array $params The Address data.
	 * @param array $args The array of WordPress params.
	 */
	public function maybe_sync_address_data( $params, $args ) {

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

		// Maybe cast Address as an object.
		if ( ! is_object( $params['address'] ) ) {
			$params['address'] = (object) $params['address'];
		}

		// Get existing Field value.
		$existing = get_field( $params['selector'], $args['post_id'] );

		// Add Address ID and overwrite array element.
		if ( ! empty( $existing[$params['key']] ) ) {

			// Assign Address ID.
			$params['value']['field_address_id'] = $params['address']->id;

			// Maybe assign Latitude.
			if ( ! empty( $params['address']->geo_code_1 ) ) {
				$params['value']['field_address_geo_code_1'] = $params['address']->geo_code_1;
			}

			// Maybe assign Longitude.
			if ( ! empty( $params['address']->geo_code_2 ) ) {
				$params['value']['field_address_geo_code_2'] = $params['address']->geo_code_2;
			}

			// Apply changes.
			$existing[$params['key']] = $params['value'];

		}

		// Now update Field.
		$this->plugin->acf->field->value_update( $params['selector'], $existing, $args['post_id'] );

	}



} // Class ends.




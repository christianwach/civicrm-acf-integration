<?php

/**
 * CiviCRM ACF Integration CiviCRM Address Class.
 *
 * A class that encapsulates CiviCRM Address functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Address extends CiviCRM_ACF_Integration_CiviCRM_Base {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Address" field key in the ACF Field data.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Address" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_address';

	/**
	 * Fields which must be handled separately.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var array $fields_handled The array of Fields which must be handled separately.
	 */
	public $fields_handled = [
		'google_map',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.4.4
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
	 * @since 0.4.4
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.4.4
	 */
	public function register_hooks() {

		// Customise "Google Map" Fields.
		//add_action( 'acf/render_field_settings/type=google_map', [ $this, 'map_setting_add' ] );

		// Intercept when a CiviCRM Address is about to be updated.
		add_action( 'civicrm_acf_integration_mapper_address_pre_edit', [ $this, 'address_pre_edit' ], 10, 1 );

		// Intercept when a CiviCRM Address has been created.
		add_action( 'civicrm_acf_integration_mapper_address_created', [ $this, 'address_created' ], 10, 1 );

		// Intercept when a CiviCRM Address has been edited.
		add_action( 'civicrm_acf_integration_mapper_address_edited', [ $this, 'address_edited' ], 10, 1 );

		// Intercept when a CiviCRM Address has been deleted.
		add_action( 'civicrm_acf_integration_mapper_address_deleted', [ $this, 'address_deleted' ], 10, 1 );

		// Add any Address Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Intercept Post updated (or created) from Contact events.
		add_action( 'civicrm_acf_integration_post_created', [ $this, 'post_edited' ], 10 );
		add_action( 'civicrm_acf_integration_post_edited', [ $this, 'post_edited' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * Actually, don't since Addresses are currently CiviCRM --> ACF only.
	 *
	 * @since 0.4.5
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param WP_Post $post The WordPress Post object.
	 * @param array $fields The array of ACF Field values, keyed by Field selector.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function fields_handled_update( $contact, $post, $fields ) {

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a Post has been updated from a Contact via the Mapper.
	 *
	 * Sync any associated ACF Fields mapped to built-in Contact Fields.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function post_edited( $args ) {

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Address Fields.
		if ( empty( $acf_fields['address'] ) ) {
			return;
		}

		// Convert Contact data into an Address.
		$address = $this->address_get_by_id( $args['objectRef']->address_id );

		// Cast Address as an object.
		if ( ! is_object( $address ) ) {
			$address = (object) $address;
		}

		// Update the Address.
		$this->address_update( $address );

		// If this address has no "Master Address" then it might be one itself.
		$addresses_shared = $this->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Update all of them.
		foreach( $addresses_shared AS $address_shared ) {
			$this->address_update( $address_shared );
		}

	}



	// -------------------------------------------------------------------------



	/**
	 * A CiviCRM Contact's Address has just been created.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_created( $args ) {

		// Maybe cast as an object.
		if ( ! is_object( $args['objectRef'] ) ) {
			$address = (object) $args['objectRef'];
		} else {
			$address = $args['objectRef'];
		}

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Do the Address update.
		$this->address_update( $address );

		// If this address has no "Master Address" then it might be one itself.
		$addresses_shared = $this->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Update all of them.
		foreach( $addresses_shared AS $address_shared ) {
			$this->address_update( $address_shared );
		}

	}



	/**
	 * A CiviCRM Contact's Address is about to be edited.
	 *
	 * Before an Address is edited, we need to store the previous data so that
	 * we can compare with the data after the edit. If there are changes, then
	 * we will need to update accordingly.
	 *
	 * This is not required for Address creation or deletion.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_pre_edit( $args ) {

		// Always clear properties if set previously.
		if ( isset( $this->address_pre ) ){
			unset( $this->address_pre );
		}

		// Maybe cast as an object.
		if ( ! is_object( $args['objectRef'] ) ) {
			$address = (object) $args['objectRef'];
		} else {
			$address = $args['objectRef'];
		}

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Grab the previous Address data from the database via API.
		$address_pre = $this->address_get_by_id( $address->id );

		// Maybe cast previous Address data as object and stash in a property.
		if ( ! is_object( $address_pre ) ) {
			$this->address_pre = (object) $address_pre;
		} else {
			$this->address_pre = $address_pre;
		}

	}



	/**
	 * A CiviCRM Contact's Address has just been edited.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_edited( $args ) {

		// Maybe cast as an object.
		if ( ! is_object( $args['objectRef'] ) ) {
			$address = (object) $args['objectRef'];
		} else {
			$address = $args['objectRef'];
		}

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Check if the edited Address has had its properties toggled.
		$address = $this->address_properties_check( $address );

		// Do the Address update.
		$this->address_update( $address );

		// If this address has no "Master Address" then it might be one itself.
		$addresses_shared = $this->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Update all of them.
		foreach( $addresses_shared AS $address_shared ) {
			$this->address_update( $address_shared );
		}

	}



	/**
	 * A CiviCRM Contact's Address has just been deleted.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_deleted( $args ) {

		// Maybe cast as an object.
		if ( ! is_object( $args['objectRef'] ) ) {
			$address = (object) $args['objectRef'];
		} else {
			$address = $args['objectRef'];
		}

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Set a property to flag that it's being deleted.
		$address->to_delete = true;

		// Clear the Address.
		$this->address_update( $address );

		// If this address has no "Master Address" then it might be one itself.
		$addresses_shared = $this->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Clear all of them.
		foreach( $addresses_shared AS $address_shared ) {

			// Set a property to flag that it's being deleted.
			$address_shared->to_delete = true;

			// Clear the ACF Field.
			$this->address_update( $address_shared );

		}

	}



	/**
	 * Check if the Address has had any properties toggled.
	 *
	 * These are only of relevance to the Address that has been edited - not
	 * Shared Addresses.
	 *
	 * There can be only one "Primary Address" per Contact - which means that
	 * this can only be toggled ON or remain the same.
	 *
	 * There can be multiple "Billing Addresses" per Contact - so this can be
	 * toggled ON or OFF or remain the same. This plugin assumes (for the time
	 * being) that people are not implementing multiple ACF "Billing Address"
	 * Fields since there is no way of showing multiple addresses at present.
	 *
	 * @since 0.4.4
	 *
	 * @param object $address The CiviCRM Address data.
	 * @return object $address The CiviCRM Address data with the state of the properties.
	 */
	public function address_properties_check( $address ) {

		// Init toggle properties.
		$address->toggle_primary = '';
		$address->toggle_billing = '';

		// Check if "Primary" has been toggled.
		if ( $address->is_primary != $this->address_pre->is_primary ) {

			// Get direction of toggle.
			$address->toggle_primary = 'off';
			if ( $this->address_pre->is_primary == '0' ) {
				$address->toggle_primary = 'on';
			}

		} else {

		}

		// Check if "Billing" has been toggled.
		if ( $address->is_billing != $this->address_pre->is_billing ) {

			// Get direction of toggle.
			$address->toggle_billing = 'off';
			if ( $this->address_pre->is_billing == '0' ) {
				$address->toggle_billing = 'on';
			}

		} else {

		}

		// --<
		return $address;

	}



	/**
	 * Update the Address ACF Field on a Post mapped to a Contact ID.
	 *
	 * @since 0.4.4
	 *
	 * @param array|object $address The Address data.
	 */
	public function address_update( $address ) {

		// Maybe cast as an object.
		if ( ! is_object( $address ) ) {
			$address = (object) $address;
		}

		// Bail if there's no Contact ID.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $address->contact_id );

		// Bail if there's no Contact.
		if ( $contact === false ) {
			return;
		}

		// Get the Post ID for this Contact.
		$post_id = $this->plugin->civicrm->contact->is_mapped( $contact );

		// Bail if this Contact has no mapped Post.
		if ( $post_id === false ) {
			return;
		}

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if there are no Address fields.
		if ( empty( $acf_fields['address'] ) ) {
			return;
		}

		// Find the ACF Fields to update.
		$fields_to_update = $this->fields_to_update_get( $acf_fields, $address );

		// Bail if there are no fields to update.
		if ( empty( $fields_to_update ) ) {
			return;
		}

		// Update the found ACF Fields.
		foreach( $fields_to_update AS $selector => $address_field ) {
			$this->field_update( $address, $selector, $post_id, $address_field['action'] );
		}

	}



	/**
	 * Update the Address ACF Field on a Post mapped to a Contact ID.
	 *
	 * @since 0.4.4
	 *
	 * @param object $address The Address data.
	 * @param str $selector The ACF Field selector.
	 * @param int $post_id The numeric OD of the WordPress Post.
	 * @param str $action The kind of action to perform on the ACF Field - 'update' or 'clear'.
	 */
	public function field_update( $address, $selector, $post_id, $action = '' ) {

		// Get the field settings.
		$settings = get_field_object( $selector, $post_id );

		// Init Field data.
		$field_data = [];

		// Prepare the data.
		switch( $settings['type'] ) {

			// Prepare data for Google Map field.
			case 'google_map' :
				$field_data = $this->field_map_prepare( $address, $action );
				break;

			/*
			// Other Address-type Fields catered for here.
			case 'some_address' :
				$field_data = $this->field_map_prepare( $address );
				break;
			*/

		}

		// Update the ACF Field now.
		$this->plugin->acf->field->value_update( $selector, $field_data, $post_id );

	}



	/**
	 * Prepare the Address data for updating a Google Maps ACF Field.
	 *
	 * @since 0.4.4
	 *
	 * @param array|object $address The CiviCRM Address data.
	 * @param str $action The kind of action to perform on the ACF Field.
	 * @return array $field_data The Address data prepared for an ACF Google Map field.
	 */
	public function field_map_prepare( $address, $action = '' ) {

		// If we want to clear the ACF Field, return now.
		if ( $action === 'clear' ) {
			return [];
		}

		// Init ACF Field data.
		$field_data = [
			'address' => '',
			'street_number' => '',
			'city' => '',
			'post_code' => '',
			'state' => '',
			'state_short' => '',
			'country' => '',
			'country_short' => '',
		];

		// We do not set the "lat" and "lng" elements because Google Maps moans
		// if they are empty.

		// Maybe cast as an object.
		if ( ! is_object( $address ) ) {
			$address = (object) $address;
		}

		// Get basic entries.
		if ( ! empty( $address->street_address ) ) {
			$field_data['address'] = $address->street_address;
		}
		if ( ! empty( $address->street_number ) ) {
			$field_data['street_number'] = $address->street_number;
		}
		if ( ! empty( $address->city ) ) {
			$field_data['city'] = $address->city;
		}
		if ( ! empty( $address->postal_code ) ) {
			$field_data['post_code'] = $address->postal_code;
		}

		// Add the State/Province if we get one.
		if ( ! empty( $address->state_province_id ) ) {
			$state_province = $this->state_province_get_by_id( $address->state_province_id );
			if ( ! empty( $state_province ) ) {
				$field_data['state'] = $state_province['name'];
				$field_data['state_short'] = $state_province['abbreviation'];
			}
		}

		// If we have a Country present.
		if ( ! empty( $address->country_id ) ) {

			// Add the Country if we get one.
			$country = $this->country_get_by_id( $address->country_id );
			if ( ! empty( $country ) ) {
				$field_data['country'] = $country['name'];
				$field_data['country_short'] = $country['iso_code'];
			}

		} else {

			// We may be able to get Country data from the State/Province.
			if ( ! empty( $address->state_province_id ) AND ! empty( $state_province ) ) {

				// Add the Country if we get one.
				if ( ! empty( $state_province['country_id'] ) ) {
					$country = $this->country_get_by_id( $state_province['country_id'] );
					if ( ! empty( $country ) ) {
						$field_data['country'] = $country['name'];
						$field_data['country_short'] = $country['iso_code'];
					}
				}

			}

		}

		// Latitude and Longitude.
		if ( ! empty( $address->geo_code_1 ) ) {
			$field_data['lat'] = $address->geo_code_1;
		}
		if ( ! empty( $address->geo_code_2 ) ) {
			$field_data['lng'] = $address->geo_code_2;
		}

		// --<
		return $field_data;

	}



	/**
	 * Get the ACF Fields to update.
	 *
	 * The returned array is of the form:
	 *
	 * $fields_to_update = [
	 *   'ACF Selector 1' => [ 'field' => 'CiviCRM Address Field 1', 'action' => 'update' ],
	 *   'ACF Selector 2' => [ 'field' => 'CiviCRM Address Field 2', 'action' => 'clear' ],
	 * ]
	 *
	 * The "operation" element for each ACF Field is either "clear" or "update"
	 * because of the toggles that can occur for the "Primary" and "Billing"
	 * properties of the Address.
	 *
	 * @since 0.4.4
	 *
	 * @return array $acf_fields The array of ACF Fields in the Post.
	 * @param object $address The CiviCRM Address data.
	 * @return array $fields_to_update The array of ACF Fields to update.
	 */
	public function fields_to_update_get( $acf_fields, $address ) {

		// Init Fields to update.
		$fields_to_update = [];

		// Find the ACF Fields to update.
		foreach( $acf_fields['address'] AS $selector => $address_field ) {

			// If this Field references the "Primary Address".
			if ( $address_field == 'primary' ) {

				// If this address is now the "Primary Address" it means that
				// another Address is no longer Primary.

				// TODO: Do we need to update the Address that is now Primary?

				// We still need to update the field though.
				if ( $address->is_primary == '1' ) {

					// Always update.
					$fields_to_update[$selector] = [
						'field' => $address_field,
						'action' => 'update',
					];

					// Override if we're deleting it.
					if ( isset( $address->to_delete ) AND $address->to_delete === true ) {
						$fields_to_update[$selector] = [
							'field' => $address_field,
							'action' => 'clear',
						];
					}

				}

			}

			// If this Field references the "Billing Address".
			if ( $address_field == 'billing' ) {

				// If this Address is the "Billing Address".
				if ( $address->is_billing == '1' ) {

					// Always update.
					$fields_to_update[$selector] = [
						'field' => $address_field,
						'action' => 'update',
					];

					// Override if we're deleting it.
					if ( isset( $address->to_delete ) AND $address->to_delete === true ) {
						$fields_to_update[$selector] = [
							'field' => $address_field,
							'action' => 'clear',
						];
					}

				}

				// If this Address WAS the "Billing Address" but is NOT NOW, it
				// means we have to clear the ACF Field.
				if (
					$address->is_billing == '0' AND
					isset( $address->toggle_billing ) AND
					$address->toggle_billing == 'off'
				) {
					$fields_to_update[$selector] = [
						'field' => $address_field,
						'action' => 'clear',
					];
				}

			}

			// If this Field matches the current Location Type.
			if ( $address->location_type_id == $address_field ) {

				// Always update.
				$fields_to_update[$selector] = [
					'field' => $address_field,
					'action' => 'update',
				];

				// Override if we're deleting it.
				if ( isset( $address->to_delete ) AND $address->to_delete === true ) {
					$fields_to_update[$selector] = [
						'field' => $address_field,
						'action' => 'clear',
					];
				}

			}

			// If this Field has CHANGED its Location Type.
			if (
				$address->location_type_id != $address_field AND
				isset( $this->address_pre->location_type_id ) AND
				$this->address_pre->location_type_id != $address->location_type_id AND
				$this->address_pre->location_type_id == $address_field
			) {

				// Always clear the previous one.
				$fields_to_update[$selector] = [
					'field' => $address_field,
					'action' => 'clear',
				];

			}

		}

		// --<
		return $fields_to_update;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get a Location Type by its numeric ID.
	 *
	 * @since 0.4.4
	 *
	 * @param int $location_type_id The numeric ID of the Location Type.
	 * @return array $location_type The array of Location Type data.
	 */
	public function type_get_by_id( $location_type_id ) {

		// Init return.
		$location_type = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $location_type;
		}

		// Params to get the Address Type.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $location_type_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'LocationType', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $location_type;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $location_type;
		}

 		// The result set should contain only one item.
		$location_type = array_pop( $result['values'] );

		// --<
		return $location_type;

	}



	/**
	 * Get a Country by its numeric ID.
	 *
	 * @since 0.4.4
	 *
	 * @param int $country_id The numeric ID of the Country.
	 * @return array $country The array of Country data.
	 */
	public function country_get_by_id( $country_id ) {

		// Init return.
		$country = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $country;
		}

		// Params to get the Address Type.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $country_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Country', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $country;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $country;
		}

 		// The result set should contain only one item.
		$country = array_pop( $result['values'] );

		// --<
		return $country;

	}



	/**
	 * Get a State/Province by its numeric ID.
	 *
	 * @since 0.4.4
	 *
	 * @param int $state_province_id The numeric ID of the State/Province.
	 * @return array $state_province The array of State/Province data.
	 */
	public function state_province_get_by_id( $state_province_id ) {

		// Init return.
		$state_province = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $state_province;
		}

		// Params to get the Address Type.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'state_province_id' => $state_province_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'StateProvince', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $state_province;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $state_province;
		}

 		// The result set should contain only one item.
		$state_province = array_pop( $result['values'] );

		// --<
		return $state_province;

	}



	/**
	 * Get the data for Shared Addresses.
	 *
	 * @since 0.4.4
	 *
	 * @param int $address_id The numeric ID of the Address.
	 * @param array $shared The array of Shared Address data.
	 */
	public function addresses_shared_get_by_id( $address_id ) {

		// Init return.
		$shared = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $shared;
		}

		// Construct params to find Shared Addresses.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'master_id' => $address_id,
			'options' => [ 'limit' => 0 ],
		];

		// Get Shared Addresses via API.
		$result = civicrm_api( 'Address', 'get', $params );

		// Bail on failure.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			return $shared;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $shared;
		}

 		// Return the Addresses.
		$shared = $result['values'];

		// --<
		return $shared;

	}



	/**
	 * Get the data for an Address.
	 *
	 * @since 0.4.4
	 *
	 * @param int $address_id The numeric ID of the Address.
	 * @param array $address The array of Address data, or empty if none.
	 */
	public function address_get_by_id( $address_id ) {

		// Init return.
		$address = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $address;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id' => $address_id,
		];

		// Get Address details via API.
		$result = civicrm_api( 'Address', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $address;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $address;
		}

 		// The result set should contain only one item.
		$address = array_pop( $result['values'] );

		// --<
		return $address;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Location Types that can be mapped to an ACF Field.
	 *
	 * @since 0.4.4
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

		/**
		 * Filter the retrieved Location Types.
		 *
		 * @since 0.4.4
		 *
		 * @param array $location_types The retrieved array of Location Types.
		 * @param array $field The ACF Field data array.
		 * @return array $location_types The modified array of Location Types.
		 */
		$location_types = apply_filters(
			'civicrm_acf_integration_address_location_types_get_for_acf_field',
			$result['values'], $field
		);

		// --<
		return $location_types;

	}



	/**
	 * Return the "CiviCRM Address" ACF Settings Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $location_types The Location Types to populate the ACF Field with.
	 * @return array $field The ACF Field data array.
	 */
	public function acf_field_get( $location_types = [] ) {

		// Bail if empty.
		if ( empty( $location_types ) ) {
			return;
		}

		// Build choices array for dropdown.
		$choices = [];

		// Prepend "Primary Address" and "Billing Address" choices for dropdown.
		$specific_address_label = esc_attr__( 'Specific Addresses', 'civicrm-acf-integration' );
		$choices[$specific_address_label]['primary'] = esc_attr__( 'Primary Address', 'civicrm-acf-integration' );
		$choices[$specific_address_label]['billing'] = esc_attr__( 'Billing Address', 'civicrm-acf-integration' );

		// Build Location Types choices array for dropdown.
		$location_types_label = esc_attr__( 'Location Types', 'civicrm-acf-integration' );
		foreach( $location_types AS $location_type ) {
			$choices[$location_types_label][$location_type['id']] = esc_attr( $location_type['display_name'] );
		}

		// Define field.
		$field = [
			'key' => $this->acf_field_key_get(),
			'label' => __( 'CiviCRM Address', 'civicrm-acf-integration' ),
			'name' => $this->acf_field_key_get(),
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Location Type that this ACF Field should sync with. (Optional)', 'civicrm-acf-integration' ),
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
	 * Getter method for the "CiviCRM Address" key.
	 *
	 * @since 0.4.4
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Address" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	/**
	 * Add any Address Fields that are attached to a Post.
	 *
	 * @since 0.4.3
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field_in_group The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Get the "CiviCRM Address" key.
		$address_key = $this->acf_field_key_get();

		// Add if it has a reference to an Address Field.
		if ( ! empty( $field[$address_key] ) ) {
			$acf_fields['address'][$field['name']] = $field[$address_key];
		}

		// --<
		return $acf_fields;

	}



} // Class ends.




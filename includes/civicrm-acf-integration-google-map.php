<?php
/**
 * CiviCRM Google Map Class.
 *
 * Handles ACF Google Map Field sync functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.4.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration Google Map Class.
 *
 * A class that encapsulates ACF Google Map sync functionality.
 *
 * @since 0.4.4
 */
class CiviCRM_ACF_Integration_CiviCRM_Google_Map extends CiviCRM_ACF_Integration_CiviCRM_Base {

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
	 * @since 0.4.4
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Address" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_address';

	/**
	 * "Make Read Only" field key in the ACF Field data.
	 *
	 * @since 0.8
	 * @access public
	 * @var str $acf_field_edit_key The key of the "Make Read Only" in the ACF Field data.
	 */
	public $acf_field_edit_key = 'field_cacf_civicrm_address_readonly';

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

		// Always register Mapper hooks.
		$this->register_mapper_hooks();

		// Customise "Google Map" Fields.
		//add_action( 'acf/render_field_settings/type=google_map', [ $this, 'map_setting_add' ] );

		// Add any Address Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Check Contact prior to Post-Contact sync event.
		add_action( 'civicrm_acf_integration_post_contact_sync_to_post_pre', [ $this, 'contact_sync_to_post_pre' ], 10 );

		// Intercept Post-Contact sync event.
		add_action( 'civicrm_acf_integration_post_contact_sync_to_post', [ $this, 'contact_sync_to_post' ], 10 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.8
	 */
	public function register_mapper_hooks() {

		// Listen for events from our Mapper that require Address updates.
		add_action( 'civicrm_acf_integration_mapper_address_pre_edit', [ $this, 'address_pre_edit' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_address_created', [ $this, 'address_created' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_address_edited', [ $this, 'address_edited' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_address_deleted', [ $this, 'address_deleted' ], 10 );

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.8
	 */
	public function unregister_mapper_hooks() {

		// Remove all Mapper listeners.
		remove_action( 'civicrm_acf_integration_mapper_address_pre_edit', [ $this, 'address_pre_edit' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_address_created', [ $this, 'address_created' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_address_edited', [ $this, 'address_edited' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_address_deleted', [ $this, 'address_deleted' ], 10 );


	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * @since 0.4.5
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
	 * Update a CiviCRM Contact's Address with data from an ACF Field.
	 *
	 * These Fields require special handling because they are not part
	 * of the core Contact data.
	 *
	 * @since 0.8
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

		// Skip if this Field isn't linked to a CiviCRM Address.
		$key = $this->acf_field_key_get();
		if ( empty( $settings[$key] ) ) {
			return true;
		}

		// Skip if this Field isn't linked to a Primary Address.
		if ( $settings[$key] !== 'primary' ) {
			return true;
		}

		// Skip if "Make Read Only" has not been set yet.
		$edit_key = $this->acf_field_key_edit_get();
		if ( ! isset( $settings[$edit_key] ) ) {
			return true;
		}

		// Skip if it is "Make Read Only".
		if ( $settings[$edit_key] == 1 ) {
			return true;
		}

		// Make sure we have an array.
		if ( empty( $value ) AND ! is_array( $value ) ) {
			$value = [];
		}

		// Update the Address.
		$success = $this->address_update( $field, $value, $contact_id, $settings, $args );

		// --<
		return $success;

	}



	/**
	 * Update a CiviCRM Contact's Address.
	 *
	 * Work in progress...
	 *
	 * @since 0.8
	 *
	 * @param str $field The ACF Field selector.
	 * @param mixed $value The ACF Field value.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $settings The ACF Field settings.
	 * @param array $args The array of WordPress params.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function address_update( $field, $value, $contact_id, $settings, $args ) {

		// Disabled.
		return true;

		// Prepare the incoming Address data for CiviCRM.
		$address = $this->address_prepare_from_map( $value );

		// Don't process empty Addresses.
		if ( empty( $address ) ) {
			return true;
		}

		// --<
		return true;

	}



	/**
	 * Prepare CiviCRM Address data from the data in a Google Maps ACF Field.
	 *
	 * @since 0.8
	 *
	 * @param array|object $address The ACF Address data.
	 * @return array $field_data The Address data prepared for CiviCRM.
	 */
	public function address_prepare_from_map( $address ) {

		/*

            "id": "179",
            "contact_id": "202",
            "location_type_id": "1",
            "is_primary": "1",
            "is_billing": "0",
            "street_address": "18-19 Welton Road",
            "supplemental_address_1": "Supp 1",
            "supplemental_address_2": "Supp 2",
            "supplemental_address_3": "Supp 3",
            "city": "Radstock",
            "state_province_id": "2777", // CRM_Core_PseudoConstant::stateProvince()
            "postal_code": "BA3 3UA",
            "country_id": "1226",
            "geo_code_1": "51.2908204",
            "geo_code_2": "-2.4554749",
            "manual_geo_code": "0"

		[id] => 233
		[contact_id] => 228
		[location_type_id] => 1
		[is_primary] => 1
		[is_billing] => 0
		[street_address] => 10 Walpole Road
		[city] => Brighton
		[state_province_id] => 2664
		[postal_code] => BN2 0EA
		[country_id] => 1226
		[geo_code_1] => 50.8209349
		[geo_code_2] => -0.1205826
		[manual_geo_code] => 0

		*/

		/*

		// Google-sourced data does not contain "city".
		// What to do?

		[address] => 10 Walpole Road, Brighton, UK
		[lat] => 50.8209349
		[lng] => -0.1205826
		[zoom] => 14
		[place_id] => ChIJpcTrIbyFdUgRIT9SCbGREI0
		[name] => 10 Walpole Rd
		[street_number] => 10
		[street_name] => Walpole Road
		[street_name_short] => Walpole Rd
		[state] => England
		[post_code] => BN2 0EA
		[country] => United Kingdom
		[country_short] => GB

		*/

		// If we have a "place_id" then the data comes from a Google lookup.
		// Bail if we don't have it - because the data will have come from CiviCRM.
		if ( ! isset( $address['place_id'] ) ) {
			return [];
		}

		// Init CiviCRM data.
		$address_data = [
			'street_address' => '',
			'street_number' => '',
			'street_name' => '',
			'city' => '',
			'postal_code' => '',
			'country_id' => '',
			'geo_code_1' => '',
			'geo_code_2' => '',
		];

		// Get basic entries.
		if ( ! empty( $address['street_number'] ) ) {
			$address_data['street_number'] = $address['street_number'];
		}
		if ( ! empty( $address['street_name'] ) ) {
			$address_data['street_name'] = $address['street_name'];
		}

		/*
		 * CiviCRM says about "street_address":
		 *
		 * "Concatenation of all routable street address components
		 * (prefix, street number, street name, suffix, unit number OR P.O. Box)
		 *
		 * Apps should be able to determine physical location with this data
		 * (for mapping, mail delivery, etc.)."
		 *
		 * It seems that "number" and "street name" are enough.
		 */
		if ( ! empty( $address['street_number'] ) AND ! empty( $address['street_name'] ) ) {
			$address_data['street_address'] = $address['street_number'] . ' ' . $address['street_name'];
		}

		if ( ! empty( $address['city'] ) ) {
			$address_data['city'] = $address['city'];
		}
		if ( ! empty( $address['post_code'] ) ) {
			$address_data['postal_code'] = $address['post_code'];
		}

		// If we have a Country present.
		if ( ! empty( $address['country_short'] ) ) {

			// Add the Country data if we get one.
			$country = $this->civicrm->address->country_get_by_short( $address['country_short'] );
			if ( ! empty( $country ) ) {
				$address_data['country_id'] = $country['id'];
				$address_data['country'] = $country['name'];
				$address_data['iso_code'] = $country['iso_code'];
			}

		}

		// Latitude and Longitude.
		if ( ! empty( $address['lat'] ) ) {
			$address_data['geo_code_1'] = $address['lat'];
		}
		if ( ! empty( $address['lng'] ) ) {
			$address_data['geo_code_2'] = $address['lng'];
		}

		// --<
		return $address_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * A CiviCRM Contact is about to be edited.
	 *
	 * Before a Contact is edited, we need to store the previous Addresses so
	 * we can compare with the data after the edit. If there are changes, then
	 * we will need to update accordingly.
	 *
	 * This is not required for Contact creation or deletion.
	 *
	 * @since 0.6
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function contact_sync_to_post_pre( $args ) {

		// Always clear properties if set previously.
		if ( isset( $this->contact_addresses_pre ) ) {
			unset( $this->contact_addresses_pre );
		}

		// Grab Contact object.
		$contact = $args['objectRef'];

		// Init empty property.
		$this->contact_addresses_pre = [];

		// Override if the Contact has Address(es).
		if ( ! empty( $contact->address ) AND is_array( $contact->address ) ) {

			// Get the full Addresses data and add to property, cast as object.
			$contact_addresses_pre = $this->civicrm->address->addresses_get_by_contact_id( $contact->contact_id );
			foreach( $contact_addresses_pre AS $contact_address ) {
				$key = $contact_address->id;
				$this->contact_addresses_pre[$key] = $contact_address;
			}

		}

	}



	/**
	 * Intercept when a Post has been updated from a Contact via the Mapper.
	 *
	 * Sync any associated ACF Fields mapped to built-in Contact Fields.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function contact_sync_to_post( $args ) {

		// Get the ACF Fields for this ACF "Post ID".
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Address Fields.
		if ( empty( $acf_fields['google_map'] ) ) {
			return;
		}

		// Get the current Contact Addresses.
		$contact_addresses = [];
		$current_addresses = $this->civicrm->address->addresses_get_by_contact_id( $args['objectId'] );
		foreach( $current_addresses AS $current_address ) {
			$key = $current_address->id;
			$contact_addresses[$key] = $current_address;
		}

		// Bail if there are neither previous Addresses nor current Addresses.
		if ( empty( $this->contact_addresses_pre ) AND empty( $contact_addresses ) ) {
			return;
		}

		// Clear them if there are previous Addresses.
		if ( ! empty( $this->contact_addresses_pre ) ) {
			foreach( $acf_fields['google_map'] AS $selector => $address_field ) {
				$this->plugin->acf->field->value_update( $selector, [], $args['post_id'] );
			}
		}

		// Add them if there are current Addresses.
		if ( ! empty( $contact_addresses ) ) {

			// Sync all current Addresses to their the ACF Fields.
			foreach( $contact_addresses AS $address ) {

				// Find previous Address if it exists.
				$previous = null;
				if ( ! empty( $this->contact_addresses_pre[$address->id] ) ) {
					$previous = $this->contact_addresses_pre[$address->id];
				}

				// Skip if there are no ACF Fields to update for this Address.
				$fields_to_update = $this->fields_to_update_get( $acf_fields, $address, $previous );
				if ( empty( $fields_to_update ) ) {
					continue;
				}

				// Update the found ACF Fields.
				foreach( $fields_to_update AS $selector => $address_field ) {
					$this->field_update( $address, $selector, $args['post_id'], $address_field['action'] );
				}

				/*
				// If this address is a "Master Address" then it will return "Shared Addresses".
				$addresses_shared = $this->civicrm->address->addresses_shared_get_by_id( $address->id );

				// Bail if there are none.
				if ( empty( $addresses_shared ) ) {
					return;
				}

				// Update all of them.
				foreach( $addresses_shared AS $address_shared ) {

					// Find previous Address if it exists.
					$previous_shared = null;
					if ( ! empty( $this->contact_addresses_pre[$address_shared->id] ) ) {
						$previous_shared = $this->contact_addresses_pre[$address_shared->id];
					}

					// Update it.
					$this->address_fields_update( $address_shared, $previous_shared );

				}
				*/

			}

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

		// Grab the Address object.
		$address = $args['objectRef'];

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Do the Address update.
		$this->address_fields_update( $address );

		// If this address is a "Master Address" then it will return "Shared Addresses".
		$addresses_shared = $this->civicrm->address->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Update all of them.
		foreach( $addresses_shared AS $address_shared ) {
			$this->address_fields_update( $address_shared );
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
		if ( isset( $this->address_pre ) ) {
			unset( $this->address_pre );
		}

		// Grab the Address object.
		$address = $args['objectRef'];

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Grab the previous Address data from the database via API.
		$this->address_pre = $this->civicrm->address->address_get_by_id( $address->id );

	}



	/**
	 * A CiviCRM Contact's Address has just been edited.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function address_edited( $args ) {

		// Grab the Address object.
		$address = $args['objectRef'];

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Check if the edited Address has had its properties toggled.
		$address = $this->address_properties_check( $address, $this->address_pre );

		// Do the Address update.
		$this->address_fields_update( $address, $this->address_pre );

		// If this address is a "Master Address" then it will return "Shared Addresses".
		$addresses_shared = $this->civicrm->address->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Update all of them.
		foreach( $addresses_shared AS $address_shared ) {
			$this->address_fields_update( $address_shared, $this->address_pre );
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

		// Grab the Address object.
		$address = $args['objectRef'];

		// We need a Contact ID in the edited Address.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Set a property to flag that it's being deleted.
		$address->to_delete = true;

		// Clear the Address.
		$this->address_fields_update( $address );

		// If this address is a "Master Address" then it will return "Shared Addresses".
		$addresses_shared = $this->civicrm->address->addresses_shared_get_by_id( $address->id );

		// Bail if there are none.
		if ( empty( $addresses_shared ) ) {
			return;
		}

		// Clear all of them.
		foreach( $addresses_shared AS $address_shared ) {

			// Set a property to flag that it's being deleted.
			$address_shared->to_delete = true;

			// Clear the ACF Field.
			$this->address_fields_update( $address_shared );

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
	 * @param object $address The current CiviCRM Address data.
	 * @param object $previous The previous CiviCRM Address data.
	 * @return object $address The CiviCRM Address data with the state of the properties.
	 */
	public function address_properties_check( $address, $previous ) {

		// Init toggle properties.
		$address->toggle_primary = '';
		$address->toggle_billing = '';

		// Check if "Primary" has been toggled.
		if ( $address->is_primary != $previous->is_primary ) {

			// Get direction of toggle.
			$address->toggle_primary = 'off';
			if ( $previous->is_primary == '0' ) {
				$address->toggle_primary = 'on';
			}

		} else {

		}

		// Check if "Billing" has been toggled.
		if ( $address->is_billing != $previous->is_billing ) {

			// Get direction of toggle.
			$address->toggle_billing = 'off';
			if ( $previous->is_billing == '0' ) {
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
	 * @param array|object $address The current Address data.
	 * @param object $previous The previous CiviCRM Address data.
	 */
	public function address_fields_update( $address, $previous = null ) {

		// Maybe cast as an object.
		if ( ! is_object( $address ) ) {
			$address = (object) $address;
		}

		// Bail if there's no Contact ID.
		if ( empty( $address->contact_id ) ) {
			return;
		}

		// Bail if there's no Contact.
		$contact = $this->plugin->civicrm->contact->get_by_id( $address->contact_id );
		if ( $contact === false ) {
			return;
		}

		// Test if of this Contact's Contact Types is mapped to a Post Type.
		$post_types = $this->plugin->civicrm->contact->is_mapped( $contact, 'create' );
		if ( $post_types !== false ) {

			// Handle each Post Type in turn.
			foreach( $post_types AS $post_type ) {

				// Bail if this Contact has no mapped Post.
				$post_id = $this->plugin->civicrm->contact->is_mapped_to_post( $contact, $post_type );
				if ( $post_id === false ) {
					continue;
				}

				// Update the ACF Fields for this Post.
				$this->fields_update( $post_id, $address, $previous );

			}

		}

		/**
		 * Broadcast that an Address ACF Field may have been edited.
		 *
		 * @since 0.8
		 *
		 * @param array $contact The array of CiviCRM Contact data.
		 * @param object $address The current Address data.
		 * @param object $previous The previous CiviCRM Address data.
		 */
		do_action( 'civicrm_acf_integration_google_map_address_update', $contact, $address, $previous );

	}



	/**
	 * Update the Address ACF Field on an Entity mapped to a Contact ID.
	 *
	 * @since 0.8
	 *
	 * @param int|str $post_id The ACF "Post ID".
	 * @param object $address The current CiviCRM Address data.
	 * @param object $previous The previous CiviCRM Address data.
	 */
	public function fields_update( $post_id, $address, $previous = null ) {

		// Bail if there are no Address fields for this "Post ID".
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		if ( empty( $acf_fields['google_map'] ) ) {
			return;
		}

		// Bail if there are no ACF Fields to update.
		$fields_to_update = $this->fields_to_update_get( $acf_fields, $address, $previous );
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
	 * @param int $post_id The ACF "Post ID".
	 * @param str $action The kind of action to perform on the ACF Field - 'update' or 'clear'.
	 */
	public function field_update( $address, $selector, $post_id, $action = '' ) {

		// Get the field settings.
		$settings = get_field_object( $selector, $post_id );

		// Init Field data.
		$field_data = [];

		// Prepare the data.
		switch( $settings['type'] ) {

			/*
			// Other Address-type Fields catered for here.
			case 'some_address' :
				$field_data = $this->field_map_prepare( $address );
				break;
			*/

			// Prepare data for Google Map field (our default).
			case 'google_map' :
			default :
				$field_data = $this->field_map_prepare( $address, $action );
				break;

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
			$state_province = $this->civicrm->address->state_province_get_by_id( $address->state_province_id );
			if ( ! empty( $state_province ) ) {
				$field_data['state'] = $state_province['name'];
				$field_data['state_short'] = $state_province['abbreviation'];
			}
		}

		// If we have a Country present.
		if ( ! empty( $address->country_id ) ) {

			// Add the Country if we get one.
			$country = $this->civicrm->address->country_get_by_id( $address->country_id );
			if ( ! empty( $country ) ) {
				$field_data['country'] = $country['name'];
				$field_data['country_short'] = $country['iso_code'];
			}

		} else {

			// We may be able to get Country data from the State/Province.
			if ( ! empty( $address->state_province_id ) AND ! empty( $state_province ) ) {

				// Add the Country if we get one.
				if ( ! empty( $state_province['country_id'] ) ) {
					$country = $this->civicrm->address->country_get_by_id( $state_province['country_id'] );
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
	 * @param array $acf_fields The array of ACF Fields in the Post.
	 * @param object $address The current CiviCRM Address data.
	 * @param object $previous The previous CiviCRM Address data.
	 * @return array $fields_to_update The array of ACF Fields to update.
	 */
	public function fields_to_update_get( $acf_fields, $address, $previous = null ) {

		// Init Fields to update.
		$fields_to_update = [];

		// Find the ACF Fields to update.
		foreach( $acf_fields['google_map'] AS $selector => $address_field ) {

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
				isset( $previous->location_type_id ) AND
				$previous->location_type_id != $address->location_type_id AND
				$previous->location_type_id == $address_field
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

		// Get all Location Types.
		$types = $this->civicrm->address->location_types_get();

		// Bail if there are none.
		if ( empty( $types ) ) {
			return $location_types;
		}

		/**
		 * Filter the retrieved Location Types.
		 *
		 * @since 0.4.4
		 *
		 * @param array $types The retrieved array of Location Types.
		 * @param array $field The ACF Field data array.
		 * @return array $types The modified array of Location Types.
		 */
		$location_types = apply_filters(
			'civicrm_acf_integration_google_map_location_types_get_for_acf_field',
			$types, $field
		);

		// --<
		return $location_types;

	}



	// -------------------------------------------------------------------------



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
	 * Return the "Make Read Only" ACF Settings Field.
	 *
	 * @since 0.8
	 *
	 * @param array $location_types The Location Types to populate the ACF Field with.
	 * @return array $field The ACF Field data array.
	 */
	public function acf_field_edit_get( $location_types = [] ) {

		// Bail if empty.
		if ( empty( $location_types ) ) {
			return;
		}

		// Define field.
		$field = [
			'key' => $this->acf_field_key_edit_get(),
			'label' => __( 'Make Read Only', 'civicrm-acf-integration' ),
			'name' => $this->acf_field_key_edit_get(),
			'type' => 'true_false',
			//'message' => __( 'More explaining.', 'civicrm-acf-integration' ),
			'instructions' => __( 'Only CiviCRM can set the Location in this Field.', 'civicrm-acf-integration' ),
			'ui' => 1,
			'default_value' => 1,
			'required' => 0,
			'conditional_logic' => [
				[
					[
						'field' => $this->acf_field_key_get(),
						'operator' => '==',
						'value' => 'primary',
					],
				],
			],
			'parent' => $this->plugin->acf->field_group->placeholder_group_get(),
		];

		/**
		 * Filter the "Make Read Only" settings field.
		 *
		 * @since 0.8
		 *
		 * @param array $field The existing Field.
		 * @return array $field The modified Field.
		 */
		$field = apply_filters( 'civicrm_acf_integration_google_map_acf_field_edit_get', $field );

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
	 * Getter method for the "Make Read Only" key.
	 *
	 * @since 0.8
	 *
	 * @return str $acf_field_edit_key The key of the "Make Read Only" in the ACF Field data.
	 */
	public function acf_field_key_edit_get() {

		// --<
		return $this->acf_field_edit_key;

	}



	/**
	 * Add any Google Map Fields that are attached to a Post.
	 *
	 * @since 0.4.3
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Get the "CiviCRM Address" key.
		$address_key = $this->acf_field_key_get();

		// Add if it has a reference to a Google Map Field.
		if ( ! empty( $field[$address_key] ) ) {
			$acf_fields['google_map'][$field['name']] = $field[$address_key];
		}

		// --<
		return $acf_fields;

	}



} // Class ends.




<?php
/**
 * CiviCRM Email Class.
 *
 * Handles CiviCRM Email functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Email Class.
 *
 * A class that encapsulates CiviCRM Email functionality.
 *
 * @since 0.3
 */
class CiviCRM_ACF_Integration_CiviCRM_Email extends CiviCRM_ACF_Integration_CiviCRM_Base {

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
	 * @since 0.3
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Email" field key in the ACF Field data.
	 *
	 * @since 0.5
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Email" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_email';

	/**
	 * Contact Fields which must be handled separately.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var array $fields_handled The array of Contact Fields which must be handled separately.
	 */
	public $fields_handled = [
		'email',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.3
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
	 * @since 0.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.3
	 */
	public function register_hooks() {

		// Intercept when a CiviCRM Contact's Email has been updated.
		add_action( 'civicrm_acf_integration_mapper_email_edited', [ $this, 'email_edited' ], 10, 2 );

		// Add any Email Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Intercept Post created from Contact events.
		add_action( 'civicrm_acf_integration_post_contact_sync', [ $this, 'sync_to_post' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update CiviCRM Email Fields with data from ACF Fields.
	 *
	 * @since 0.4.1
	 *
	 * @param array $args The array of WordPress params.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function fields_handled_update( $args ) {

		// Bail if we have no field data to save.
		if ( empty( $args['fields'] ) ) {
			return true;
		}

		// Init success.
		$success = true;

		// Loop through the field data.
		foreach( $args['fields'] AS $field => $value ) {

			// Get the field settings.
			$settings = get_field_object( $field );

			// Maybe update a Contact Field.
			$this->field_handled_update( $field, $value, $args['contact']['id'], $settings );

		}

		// --<
		return $success;

	}



	/**
	 * Update a CiviCRM Email Field with data from an ACF Field.
	 *
	 * This Contact Field requires special handling because it is not part
	 * of the core Contact data.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data.
	 * @param mixed $value The ACF Field value.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $settings The ACF Field settings.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function field_handled_update( $field, $value, $contact_id, $settings ) {

		// Skip if it's not a Field that this class handles.
		if ( ! in_array( $settings['type'], $this->fields_handled ) ) {
			return true;
		}

		// Get the "CiviCRM Email" key.
		$email_key = $this->acf_field_key_get();

		// Skip if we don't have a synced Email.
		if ( empty( $settings[$email_key] ) ) {
			return true;
		}

		// Parse value by field type.
		$value = $this->plugin->acf->field->value_get_for_civicrm( $settings['type'], $value );

		// Is this mapped to the Primary Email?
		if ( $settings[$email_key] == 'primary' ) {

			// Update and return early.
			$this->primary_email_update( $contact_id, $value );
			return true;

		}

		// The ID of the Location Type is the setting.
		$location_type_id = absint( $settings[$email_key] );

		// Update the Email.
		$this->email_update( $location_type_id, $contact_id, $value );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Primary Email.
	 *
	 * @since 0.4.1
	 *
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $value The email to update the Contact with.
	 * @return array|bool $email The array of Email data, or false on failure.
	 */
	public function primary_email_update( $contact_id, $value ) {

		// Init return.
		$email = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $email;
		}

		// Get the current Primary Email.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
			'is_primary' => 1,
		];

		// Call the CiviCRM API.
		$primary_email = civicrm_api( 'Email', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $primary_email['is_error'] ) AND $primary_email['is_error'] == 1 ) {
			return $email;
		}

		// Create a Primary Email if there are no results.
		if ( empty( $primary_email['values'] ) ) {

			// Define params to create new Primary Email.
			$params = [
				'version' => 3,
				'contact_id' => $contact_id,
				'is_primary' => 1,
				'email' => $value,
			];

			// Call the API.
			$result = civicrm_api( 'Email', 'create', $params );

		} else {

			// There should be only one item.
			$existing_data = array_pop( $primary_email['values'] );

			// Bail if it hasn't changed.
			if ( $existing_data['email'] == $value ) {
				return $existing_data;
			}

			// If there is an incoming value, update.
			if ( ! empty( $value ) ) {

				// Define params to update this Email.
				$params = [
					'version' => 3,
					'id' => $primary_email['id'],
					'contact_id' => $contact_id,
					'email' => $value,
				];

				// Call the API.
				$result = civicrm_api( 'Email', 'create', $params );

			} else {

				// Define params to delete this Email.
				$params = [
					'version' => 3,
					'id' => $primary_email['id'],
				];

				// Call the API.
				$result = civicrm_api( 'Email', 'delete', $params );

				// Bail early.
				return $email;

			}

		}

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $email;
		}

		// The result set should contain only one item.
		$email = array_pop( $result['values'] );

		// --<
		return $email;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Emails for a given Contact ID.
	 *
	 * @since 0.6.4
	 *
	 * @param int $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array $email_data The array of Email data for the CiviCRM Contact.
	 */
	public function emails_get_for_contact( $contact_id ) {

		// Init return.
		$email_data = [];

		// Bail if we have no Contact ID.
		if ( empty( $contact_id ) ) {
			return $email_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $email_data;
		}

		// Define params to get queried Emails.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'contact_id' => $contact_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Email', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $email_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $email_data;
		}

		// The result set it what we want.
		$email_data = $result['values'];

		// --<
		return $email_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a Post is been synced from a Contact.
	 *
	 * Sync any associated ACF Fields mapped to Custom Fields.
	 *
	 * @since 0.6.4
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function sync_to_post( $args ) {

		// Get all Emails for this Contact.
		$data = $this->emails_get_for_contact( $args['objectId'] );

		// Bail if there are no Email Fields.
		if ( empty( $data ) ) {
			return;
		}

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Email Fields.
		if ( empty( $acf_fields['email'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['email'] AS $selector => $email_field ) {

			// Let's look at each Email in turn.
			foreach( $data AS $email ) {

				// Cast as object.
				$email = (object) $email;

				// If this is mapped to the Primary Email.
				if ( $email_field == 'primary' AND $email->is_primary == '1' ) {
					$this->plugin->acf->field->value_update( $selector, $email->email, $args['post_id'] );
					continue;
				}

				// Skip if the Location Types don't match.
				if ( $email_field != $email->location_type_id ) {
					continue;
				}

				// Update it.
				$this->plugin->acf->field->value_update( $selector, $email->email, $args['post_id'] );

			}

		}

	}



	/**
	 * Update a CiviCRM Contact's Email.
	 *
	 * @since 0.5
	 *
	 * @param int $location_type_id The numeric ID of the Location Type.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $value The Email to update the Contact with.
	 * @return array|bool $email The array of Email data, or false on failure.
	 */
	public function email_update( $location_type_id, $contact_id, $value ) {

		// Init return.
		$email = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $email;
		}

		// Get the current Email for this Location Type.
		$params = [
			'version' => 3,
			'location_type_id' => $location_type_id,
			'contact_id' => $contact_id,
		];

		// Call the CiviCRM API.
		$existing_email = civicrm_api( 'Email', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $existing_email['is_error'] ) AND $existing_email['is_error'] == 1 ) {
			return $email;
		}

		// Create a new Email if there are no results.
		if ( empty( $existing_email['values'] ) ) {

			// Define params to create new Email.
			$params = [
				'version' => 3,
				'location_type_id' => $location_type_id,
				'contact_id' => $contact_id,
				'email' => $value,
			];

			// Call the API.
			$result = civicrm_api( 'Email', 'create', $params );

		} else {

			// There should be only one item.
			$existing_data = array_pop( $existing_email['values'] );

			// Bail if it hasn't changed.
			if ( $existing_data['email'] == $value ) {
				return $existing_data;
			}

			// If there is an incoming value, update.
			if ( ! empty( $value ) ) {

				// Define params to update this Email.
				$params = [
					'version' => 3,
					'id' => $existing_email['id'],
					'contact_id' => $contact_id,
					'email' => $value,
				];

				// Call the API.
				$result = civicrm_api( 'Email', 'create', $params );

			} else {

				// Define params to delete this Email.
				$params = [
					'version' => 3,
					'id' => $existing_email['id'],
				];

				// Call the API.
				$result = civicrm_api( 'Email', 'delete', $params );

				// Bail early.
				return $email;

			}

		}

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $email;
		}

		// The result set should contain only one item.
		$email = array_pop( $result['values'] );

		// --<
		return $email;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Email has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function email_edited( $args ) {

		// Grab the Email data.
		$email = $args['objectRef'];

		// Maybe cast as object.
		if ( ! is_object( $email ) ) {
			$email = (object) $email;
		}

		// Bail if this is not a Contact's Email.
		if ( empty( $email->contact_id ) ) {
			return;
		}

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $email->contact_id );

		// Bail if none of this Contact's Contact Types is mapped.
		$post_types = $this->plugin->civicrm->contact->is_mapped( $contact, 'create' );
		if ( $post_types === false ) {
			return;
		}

		// Handle each Post Type in turn.
		foreach( $post_types AS $post_type ) {

			// Get the Post ID for this Contact.
			$post_id = $this->plugin->civicrm->contact->is_mapped_to_post( $contact, $post_type );

			// Skip if not mapped or Post doesn't yet exist.
			if ( $post_id === false ) {
				continue;
			}

			// Get the ACF Fields for this Post.
			$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

			// Bail if there are no Email Fields.
			if ( empty( $acf_fields['email'] ) ) {
				continue;
			}

			// Let's look at each ACF Field in turn.
			foreach( $acf_fields['email'] AS $selector => $email_field ) {

				// If this is mapped to the Primary Email.
				if ( $email_field == 'primary' AND $email->is_primary == '1' ) {
					$this->plugin->acf->field->value_update( $selector, $email->email, $post_id );
					continue;
				}

				// Skip if the Location Types don't match.
				if ( $email_field != $email->location_type_id ) {
					continue;
				}

				// Update it.
				$this->plugin->acf->field->value_update( $selector, $email->email, $post_id );

			}

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Location Types that can be mapped to an ACF Field.
	 *
	 * @since 0.5
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
		 * @since 0.5
		 *
		 * @param array $location_types The retrieved array of Location Types.
		 * @param array $field The ACF Field data array.
		 * @return array $location_types The modified array of Location Types.
		 */
		$location_types = apply_filters(
			'civicrm_acf_integration_email_location_types_get_for_acf_field',
			$result['values'], $field
		);

		// --<
		return $location_types;

	}



	/**
	 * Return the "CiviCRM Email" ACF Settings Field.
	 *
	 * @since 0.5
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

		// Prepend "Primary Email" choice for dropdown.
		$specific_email_label = esc_attr__( 'Specific Emails', 'civicrm-acf-integration' );
		$choices[$specific_email_label]['primary'] = esc_attr__( 'Primary Email', 'civicrm-acf-integration' );

		// Build Location Types choices array for dropdown.
		$location_types_label = esc_attr__( 'Location Types', 'civicrm-acf-integration' );
		foreach( $location_types AS $location_type ) {
			$choices[$location_types_label][$location_type['id']] = esc_attr( $location_type['display_name'] );
		}

		// Define field.
		$field = [
			'key' => $this->acf_field_key_get(),
			'label' => __( 'CiviCRM Email', 'civicrm-acf-integration' ),
			'name' => $this->acf_field_key_get(),
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Email that this ACF Field should sync with. (Optional)', 'civicrm-acf-integration' ),
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
	 * Getter method for the "CiviCRM Email" key.
	 *
	 * @since 0.5
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Email" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	/**
	 * Add any Email Fields that are attached to a Post.
	 *
	 * @since 0.5
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Get the "CiviCRM Email" key.
		$email_key = $this->acf_field_key_get();

		// Add if it has a reference to an Email Field.
		if ( ! empty( $field[$email_key] ) ) {
			$acf_fields['email'][$field['name']] = $field[$email_key];
		}

		// --<
		return $acf_fields;

	}



} // Class ends.




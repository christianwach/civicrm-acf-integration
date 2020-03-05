<?php

/**
 * CiviCRM ACF Integration CiviCRM Website Class.
 *
 * A class that encapsulates CiviCRM Website functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Website extends CiviCRM_ACF_Integration_CiviCRM_Base {

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
	 * "CiviCRM Website" field key in the ACF Field data.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Website" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_website';

	/**
	 * Contact Fields which must be handled separately.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var array $fields_handled The array of Contact Fields which must be handled separately.
	 */
	public $fields_handled = [
		'url',
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

		// Intercept when a CiviCRM Website is about to be updated.
		add_action( 'civicrm_acf_integration_mapper_website_pre_edit', [ $this, 'website_pre_edit' ], 10, 1 );

		// Intercept when a CiviCRM Website has been updated.
		add_action( 'civicrm_acf_integration_mapper_website_edited', [ $this, 'website_edited' ], 10, 2 );

		// Add any Website Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Add and Website Fields that are Custom Fields.
		add_filter( 'civicrm_acf_integration_contact_custom_field_id_get', [ $this, 'custom_field_id_get' ], 10, 2 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * @since 0.4.1
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param WP_Post $post The WordPress Post object.
	 * @param array $fields The array of ACF Field values, keyed by Field selector.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function fields_handled_update( $contact, $post, $fields ) {

		// Bail if we have no field data to save.
		if ( empty( $fields ) ) {
			return true;
		}

		// Init success.
		$success = true;

		// Loop through the field data.
		foreach( $fields AS $field => $value ) {

			// Get the field settings.
			$settings = get_field_object( $field );

			// Maybe update a Contact Field.
			$this->field_handled_update( $field, $value, $contact['id'], $settings );

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
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data.
	 * @param mixed $value The ACF Field value.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $settings The ACF Field settings.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function field_handled_update( $field, $value, $contact_id, $settings ) {

		// Skip if it's not an ACF Field Type that this class handles.
		if ( ! in_array( $settings['type'], $this->fields_handled ) ) {
			return true;
		}

		// Get the "CiviCRM Website" key.
		$website_key = $this->acf_field_key_get();

		// Skip if we don't have a synced Website.
		if ( empty( $settings[$website_key] ) ) {
			return true;
		}

		// Skip if it maps to a Custom Field.
		if ( false !== strpos( $settings[$website_key], 'caicustom_' ) ) {
			return true;
		}

		// Parse value by field type.
		$value = $this->plugin->acf->field->value_get_for_civicrm( $settings['type'], $value );

		// The ID of the Website Type is the setting.
		$website_type_id = $settings[$website_key];

		// Update the Website.
		$this->website_update( $website_type_id, $contact_id, $value );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the data for an Website.
	 *
	 * @since 0.4.4
	 *
	 * @param int $website_id The numeric ID of the Website.
	 * @param array $website The array of Website data, or empty if none.
	 */
	public function website_get_by_id( $website_id ) {

		// Init return.
		$website = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $website;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id' => $website_id,
		];

		// Get Website details via API.
		$result = civicrm_api( 'Website', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $website;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $website;
		}

 		// The result set should contain only one item.
		$website = array_pop( $result['values'] );

		// --<
		return $website;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Website.
	 *
	 * @since 0.4.1
	 *
	 * @param int $website_type_id The numeric ID of the Website Type.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $value The Website URL to update the Contact with.
	 * @return array|bool $website The array of Website data, or false on failure.
	 */
	public function website_update( $website_type_id, $contact_id, $value ) {

		// Init return.
		$website = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $website;
		}

		// Get the current Website for this Website Type.
		$params = [
			'version' => 3,
			'website_type_id' => $website_type_id,
			'contact_id' => $contact_id,
		];

		// Call the CiviCRM API.
		$existing_website = civicrm_api( 'Website', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $existing_website['is_error'] ) AND $existing_website['is_error'] == 1 ) {
			return $website;
		}

		// Create a new Website if there are no results.
		if ( empty( $existing_website['values'] ) ) {

			// Define params to create new Website.
			$params = [
				'version' => 3,
				'website_type_id' => $website_type_id,
				'contact_id' => $contact_id,
				'url' => $value,
			];

			// Call the API.
			$result = civicrm_api( 'Website', 'create', $params );

		} else {

			// There should be only one item.
			$existing_data = array_pop( $existing_website['values'] );

			// Bail if it hasn't changed.
			if ( !empty( $existing_data['url'] ) AND $existing_data['url'] == $value ) {
				return $existing_data;
			}

			// If there is an incoming value, update.
			if ( ! empty( $value ) ) {

				// Define params to update this Website.
				$params = [
					'version' => 3,
					'id' => $existing_website['id'],
					'contact_id' => $contact_id,
					'url' => $value,
				];

				// Call the API.
				$result = civicrm_api( 'Website', 'create', $params );

			} else {

				// Define params to delete this Website.
				$params = [
					'version' => 3,
					'id' => $existing_website['id'],
				];

				// Call the API.
				$result = civicrm_api( 'Website', 'delete', $params );

				// Bail early.
				return $website;

			}

		}

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $website;
		}

		// The result set should contain only one item.
		$website = array_pop( $result['values'] );

		// --<
		return $website;

	}



	// -------------------------------------------------------------------------



	/**
	 * A CiviCRM Contact's Website is about to be edited.
	 *
	 * Before an Website is edited, we need to store the previous data so that
	 * we can compare with the data after the edit. If there are changes, then
	 * we will need to update accordingly.
	 *
	 * This is not required for Website creation or deletion.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function website_pre_edit( $args ) {

		// Always clear properties if set previously.
		if ( isset( $this->website_pre ) ){
			unset( $this->website_pre );
		}

		// Maybe cast as an object.
		if ( ! is_object( $args['objectRef'] ) ) {
			$website = (object) $args['objectRef'];
		} else {
			$website = $args['objectRef'];
		}

		// We need a Contact ID in the edited Website.
		if ( empty( $website->contact_id ) ) {
			return;
		}

		// Grab the previous Website data from the database.
		$website_pre = $this->website_get_by_id( $website->id );

		// Maybe cast previous Website data as object and stash in a property.
		if ( ! is_object( $website_pre ) ) {
			$this->website_pre = (object) $website_pre;
		} else {
			$this->website_pre = $website_pre;
		}

	}



	/**
	 * Intercept when a CiviCRM Website has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function website_edited( $args ) {

		// Grab the Website data.
		$website = $args['objectRef'];

		// Bail if this is not a Contact's Website.
		if ( empty( $website->contact_id ) ) {
			return;
		}

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $website->contact_id );

		// Bail if this Contact's Contact Type is not mapped.
		$contact_types = $this->plugin->civicrm->contact_type->hierarchy_get_for_contact( $contact );
		$post_type = $this->plugin->civicrm->contact_type->is_mapped( $contact_types );
		if ( $post_type === false ) {
			return;
		}

		// Get the Post ID for this Contact.
		$post_id = $this->plugin->civicrm->contact->is_mapped( $contact );

		// Skip if not mapped.
		if ( $post_id === false ) {
			return;
		}

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if there are no Website Fields.
		if ( empty( $acf_fields['website'] ) ) {
			return;
		}

		// TODO: Find the ACF Fields to update.
		//$fields_to_update = $this->fields_to_update_get( $acf_fields, $website, $args['op'] );

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['website'] AS $selector => $website_field ) {

			// Skip if it's a Custom Field.
			if ( false !== strpos( $website_field, 'caicustom_' ) ) {
				continue;
			}

			// Skip if it's not the right Website Type.
			if ( $website_field != $website->website_type_id ) {
				continue;
			}

			// Update it.
			$this->plugin->acf->field->value_update( $selector, $website->url, $post_id );

		}

	}



	/**
	 * Get the ACF Fields to update.
	 *
	 * The returned array is of the form:
	 *
	 * $fields_to_update = [
	 *   'ACF Selector 1' => [ 'field' => 'CiviCRM Website Field 1', 'action' => 'update' ],
	 *   'ACF Selector 2' => [ 'field' => 'CiviCRM Website Field 2', 'action' => 'clear' ],
	 * ]
	 *
	 * The "operation" element for each ACF Field is either "clear" or "update"
	 *
	 * @since 0.4.4
	 *
	 * @return array $acf_fields The array of ACF Fields in the Post.
	 * @param object $website The CiviCRM Website data.
	 * @param str $op The database operation.
	 * @return array $fields_to_update The array of ACF Fields to update.
	 */
	public function fields_to_update_get( $acf_fields, $website, $op ) {

		// Init Fields to update.
		$fields_to_update = [];

		// Find the ACF Fields to update.
		foreach( $acf_fields['website'] AS $selector => $website_field ) {

			// Skip if it's a Custom Field.
			if ( false !== strpos( $website_field, 'caicustom_' ) ) {
				continue;
			}

			// If this Field matches the current Website Type.
			if ( $website->website_type_id == $website_field ) {

				// Always update.
				$fields_to_update[$selector] = [
					'field' => $website_field,
					'action' => 'update',
				];

				// Override if we're deleting it.
				if ( isset( $website->to_delete ) AND $website->to_delete === true ) {
					$fields_to_update[$selector] = [
						'field' => $website_field,
						'action' => 'clear',
					];
				}

			}

			// If this Field has CHANGED its Website Type.
			if (
				$website->website_type_id != $website_field AND
				isset( $this->website_pre->website_type_id ) AND
				$this->website_pre->website_type_id != $website->website_type_id AND
				$this->website_pre->website_type_id == $website_field
			) {

				// Always clear the previous one.
				$fields_to_update[$selector] = [
					'field' => $website_field,
					'action' => 'clear',
				];

			}

		}

		// --<
		return $fields_to_update;

	}



	// -------------------------------------------------------------------------



	/**
	 * Filter the Custom Field ID.
	 *
	 * Some Website Fields may be mapped to CiviCRM Custom Fields. This filter
	 * teases out which ones and, if they are mapped to a Custom Field, returns
	 * their Custom Field ID.
	 *
	 * @since 0.5
	 *
	 * @param int $custom_field_id The existing Custom Field ID.
	 * @param array $field The array of ACF Field data.
	 * @return int $custom_field_id The modified Custom Field ID.
	 */
	public function custom_field_id_get( $custom_field_id, $field ) {

		// Get the "CiviCRM Website" key.
		$website_key = $this->acf_field_key_get();

		// Return it if the Field has a reference to a Website Custom Field.
		if ( ! empty( $field[$website_key] ) ) {
			if ( false !== strpos( $field[$website_key], 'caicustom_' ) ) {
				$custom_field_id = absint( str_replace( 'caicustom_', '', $field[$website_key] ) );
			}
		}

		// --<
		return $custom_field_id;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Website Types that can be mapped to an ACF Field.
	 *
	 * @since 0.4.4
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $website_types The array of possible Website Types.
	 */
	public function get_for_acf_field( $field ) {

		// Init return.
		$website_types = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $website_types;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $website_types;
		}

		// Get the Website Types array.
		$website_type_ids = CRM_Core_PseudoConstant::get( 'CRM_Core_DAO_Website', 'website_type_id' );

		// Bail if there are no results.
		if ( empty( $website_type_ids ) ) {
			return $website_types;
		}

		// Assign to return.
		$website_types = $website_type_ids;

		// --<
		return $website_types;

	}



	/**
	 * Return the "CiviCRM Website" ACF Settings Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $website_types The Website Types to populate the ACF Field with.
	 * @param array $custom_fields The Custom Fields to populate the ACF Field with.
	 * @return array $field The ACF Field data array.
	 */
	public function acf_field_get( $custom_fields = [], $website_types = [] ) {

		// Bail if empty.
		if ( empty( $website_types ) ) {
			return;
		}

		// Build choices array for dropdown.
		$choices = [];

		// Build Website Types choices array for dropdown.
		$website_types_label = esc_attr__( 'Contact Website Type', 'civicrm-acf-integration' );
		foreach( $website_types AS $website_type_id => $website_type_name ) {
			$choices[$website_types_label][$website_type_id] = esc_attr( $website_type_name );
		}

		// Build Custom Field choices array for dropdown.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			$custom_fields_label = esc_attr( $custom_group_name );
			foreach( $custom_group AS $custom_field ) {
				$choices[$custom_fields_label]['caicustom_' . $custom_field['id']] = $custom_field['label'];
			}
		}

		// Define field.
		$field = [
			'key' => $this->acf_field_key_get(),
			'label' => __( 'CiviCRM Website', 'civicrm-acf-integration' ),
			'name' => $this->acf_field_key_get(),
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Website Field that this ACF Field should sync with. (Optional)', 'civicrm-acf-integration' ),
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
	 * Getter method for the "CiviCRM Website" key.
	 *
	 * @since 0.4.3
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Website" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	/**
	 * Add any Website Fields that are attached to a Post.
	 *
	 * @since 0.4.3
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field_in_group The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Get the "CiviCRM Website" key.
		$website_key = $this->acf_field_key_get();

		// Add if it has a reference to a Website Field.
		if ( ! empty( $field[$website_key] ) ) {
			$acf_fields['website'][$field['name']] = $field[$website_key];
		}

		// --<
		return $acf_fields;

	}



} // Class ends.




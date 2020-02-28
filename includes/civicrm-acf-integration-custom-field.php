<?php

/**
 * CiviCRM ACF Integration CiviCRM Custom Field Class.
 *
 * A class that encapsulates CiviCRM Custom Field functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Custom_Field {

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
	 * CiviCRM Custom Field data types that can have "Select", "Radio" and
	 * "CheckBox" HTML subtypes.
	 *
	 * @since 0.3
	 * @access public
	 * @var array $data_types The data types that can have "Select", "Radio"
	 *                        and "CheckBox" HTML subtypes.
	 */
	public $data_types = [
		'String',
		'Int',
		'Float',
		'Money',
		'Country',
		'StateProvince',
	];

	/**
	 * All CiviCRM Custom Fields that are of type "Select".
	 *
	 * @since 0.4.3
	 * @access public
	 * @var array $data_types The Custom Fields that are of type "Select".
	 */
	public $select_types = [
		'Select',
		'Multi-Select',
		'Autocomplete-Select',
		'Select Country',
		'Multi-Select Country',
		'Select State/Province',
		'Multi-Select State/Province',
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

		// Intercept when the content of a set of CiviCRM Custom Fields has been updated.
		add_action( 'civicrm_acf_integration_mapper_custom_edited', [ $this, 'custom_edited' ], 10 );

		// Intercept CiviCRM Add/Edit Custom Field postSave hook.
		//add_action( 'civicrm_postSave_civicrm_custom_field', [ $this, 'custom_field_edited' ], 10, 1 );

		// Intercept CiviCRM Add/Edit Option Value postSave hook.
		//add_action( 'civicrm_postSave_civicrm_option_value', [ $this, 'option_value_edited' ], 10, 1 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Callback for the CiviCRM Add/Edit Custom Field postSave hook.
	 *
	 * This method listens for changes to Custom Fields and if they are mapped
	 * to ACF Fields, attempts to update the ACF Field settings accordingly.
	 *
	 * The same limitations that apply to the Option Value postSave hook also
	 * apply here.
	 *
	 * @see self::option_value_edited()
	 *
	 * @since 0.5
	 *
	 * @param object $objectRef The DAO object.
	 */
	public function custom_field_edited( $objectRef ) {

		// Bail if not Option Value save operation.
		if ( ! ( $objectRef instanceof CRM_Core_DAO_CustomField ) ) {
			return;
		}

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'objectRef' => $objectRef,
			//'backtrace' => $trace,
		), true ) );
		*/

	}



	/**
	 * Callback for the CiviCRM Add/Edit Option Value postSave hook.
	 *
	 * The idea here is to listen for Option Value changes in Option Groups that
	 * are mapped to ACF Fields and update the ACF Field settings accordingly.
	 *
	 * The problem is that ACF stores Fields as Posts of type "acf-field" where
	 * the Post Content is a serialised array of settings. This means that the
	 * only way I can think of to discover which ACF Fields are mapped is to
	 * load *all of them* and iterate through them unserialising their content
	 * and checking for the setting. This doesn't seem very, um, elegant.
	 *
	 * CiviCRM Option Groups and Custom Fields don't have a way of saving meta
	 * data, so the only alternative approach that I can see right now would be
	 * to introduce a plugin setting that holds the mapping data. This would be
	 * more easily queried but would introduce more complexity.
	 *
	 * @since 0.5
	 *
	 * @param object $objectRef The DAO object.
	 */
	public function option_value_edited( $objectRef ) {

		// Bail if not Option Value save operation.
		if ( ! ( $objectRef instanceof CRM_Core_DAO_OptionValue ) ) {
			return;
		}

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'objectRef' => $objectRef,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Get the Option Group to which this Option Value is attached.
		$option_group = $this->option_group_get_by_id( $objectRef->option_group_id );

		// Bail if something went wrong.
		if ( $option_group === false ) {
			return;
		}

		// TODO: Find the ACF Fields which map to this Option Group.

	}



	/**
	 * Get the CiviCRM Option Group data for a given ID.
	 *
	 * @since 0.5
	 *
	 * @param str|int $option_group_id The numeric ID of the Custom Group.
	 * @return array|bool $option_group An array of Option Group data, or false on failure.
	 */
	public function option_group_get_by_id( $option_group_id ) {

		// Init return.
		$option_group = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $option_group;
		}

		// Build params to get Option Group data.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $option_group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionGroup', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $option_group;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $option_group;
		}

		// The result set should contain only one item.
		$option_group = array_pop( $result['values'] );

		// --<
		return $option_group;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update ACF Fields when a set of CiviCRM Custom Fields has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function custom_edited( $args ) {

		// Init Contact.
		$contact = false;

		// Let's tease out the context from the Custom Field data.
		foreach( $args['custom_fields'] AS $field ) {

			// Skip if it is not attached to a Contact.
			if ( $field['entity_table'] != 'civicrm_contact' ) {
				continue;
			}

			// Grab the Contact.
			$contact = $this->plugin->civicrm->contact->get_by_id( $field['entity_id'] );

			// We can bail now that we know.
			break;

		}

		// Bail if there's no Contact.
		if ( $contact === false ) {
			return;
		}

		// Get the Post ID that this Contact is mapped to.
		$post_id = $this->plugin->civicrm->contact->is_mapped( $contact );

		// Skip if not mapped.
		if ( $post_id === false ) {
			return;
		}

		/*
		// Get the mapped Post.
		$post = $this->plugin->post->get_by_contact_id( $contact['contact_id'] );

		// Bail if there isn't one.
		if ( $post === false ) {
			return;
		}
		*/

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if we don't have any Custom Fields.
		if ( empty( $acf_fields['custom'] ) ) {
			return;
		}

		// Let's look at each Custom Field in turn.
		foreach( $args['custom_fields'] AS $field ) {

			// Does this Custom Field match an ACF Field?
			if ( in_array( $field['custom_field_id'], $acf_fields['custom'] ) ) {

				// Find key - we can only guarantee that keys are unique.
				$selector = '';
				foreach( $acf_fields['custom'] AS $acf_field => $custom_field_id ) {
					if ( $custom_field_id == $field['custom_field_id'] ) {
						$selector = $acf_field;
					}
				}

				// Modify values for ACF prior to update.
				$field['value'] = $this->value_get_for_acf(
					$field['value'],
					$field,
					$selector,
					$post_id
				);

				// Update it.
				$this->plugin->acf->field->value_update( $selector, $field['value'], $post_id );

			}

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the value of a Custom Field, formatted for ACF.
	 *
	 * @since 0.3
	 *
	 * @param mixed $value The Custom Field value.
	 * @param array $field The Custom Field data.
	 * @param str $selector The ACF Field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return mixed $value The formatted field value.
	 */
	public function value_get_for_acf( $value, $field, $selector, $post_id ) {

		// Bail if empty.
		if ( empty( $value ) ) {
			return $value;
		}

		// Convert CiviCRM value to ACF value by field type.
		switch( $field['type'] ) {

			// Used by "CheckBox" and others.
			case 'String' :

				// Convert if the value has the special CiviCRM array-like format.
				if ( false !== strpos( $value, CRM_Core_DAO::VALUE_SEPARATOR ) ) {
					$value = CRM_Utils_Array::explodePadded( $value );
				}

				break;

			// Used by "Date Select" and  "Date Time Select".
			case 'Timestamp' :

				// Get field setting.
				$acf_setting = get_field_object( $selector, $post_id );

				// Convert to ACF format.
				$datetime = DateTime::createFromFormat( 'YmdHis', $value );
				if ( $acf_setting['type'] == 'date_picker' ) {
					$value = $datetime->format( 'Ymd' );
				} elseif ( $acf_setting['type'] == 'date_time_picker' ) {
					$value = $datetime->format( 'Y-m-d H:i:s' );
				}

				break;

		}

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Custom Group data for a given ID.
	 *
	 * @since 0.2.1
	 *
	 * @param str|int $group_id The numeric ID of the Custom Group.
	 * @return array|bool $group An array of Custom Group data, or false on failure.
	 */
	public function group_get_by_id( $group_id ) {

		// Init return.
		$group = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group;
		}

		// Build params to get Custom Group data.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'CustomGroup', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $group;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// The result set should contain only one item.
		$group = array_pop( $result['values'] );

		// --<
		return $group;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Custom Field data for a given ID.
	 *
	 * This is called on a per-Field basis. If it ends up slowing things down
	 * too much, an alternative would be to query *all* Custom Fields, stash
	 * that data set, then query it locally for each subsequent request.
	 *
	 * @since 0.3
	 *
	 * @param str|int $field_id The numeric ID of the Custom Field.
	 * @return array|bool $field An array of Custom Field data, or false on failure.
	 */
	public function get_by_id( $field_id ) {

		// Init return.
		$field = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $field;
		}

		// Build params to get Custom Group data.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $field_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'CustomField', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $field;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $field;
		}

		// The result set should contain only one item.
		$field = array_pop( $result['values'] );

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Custom Fields for an ACF Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 */
	public function get_for_acf_field( $field ) {

		// Init return.
		$custom_fields = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $custom_fields;
		}

		// Get field key.
		$field_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Get the Contact Type.
		$contact_type_id = $field_group[$field_key];

		// Get Contact Type hierarchy.
		$contact_types = $this->civicrm->contact_type->hierarchy_get_by_id( $contact_type_id );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->get_for_contact_type(
			$contact_types['type'],
			$contact_types['subtype']
		);

		// --<
		return $custom_fields;

	}



	/**
	 * Get the Custom Fields for a CiviCRM Contact Type/Subtype.
	 *
	 * There's a discussion to be had about whether or not to include Custom Groups
	 * for the Contact Subtype or not. The code in this method can return data
	 * specific to the Subtype, but it's presumably desirable to include all
	 * Custom Groups that apply to a Contact Type.
	 *
	 * There's also a slight weakness in this code, in that the returned array is
	 * keyed by the "title" of the Custom Group. It is possible (though unlikely)
	 * that two Custom Groups may have the same "title", in which case the Custom
	 * Fields will be grouped together in the "CiviCRM Field" dropdown. The unique
	 * element is the Custom Group's "name" property, but then we would have to
	 * retrieve the "title" somewhere else - as it stands, the return array has
	 * all the data required to build the select, so I'm leaving it as is for now.
	 *
	 * @since 0.3
	 *
	 * @param str $type The Contact Type that the Option Group applies to.
	 * @param str $subtype The Contact Sub-type that the Option Group applies to.
	 * @return array $custom_fields The array of custom fields.
	 */
	public function get_for_contact_type( $type = 'Individual', $subtype = '' ) {

		// Only do this once per Contact Type.
		static $pseudocache;
		if ( isset( $pseudocache[$type] ) ) {
			return $pseudocache[$type];
		}

		// Init array to build.
		$custom_fields = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $custom_fields;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'is_active' => 1,
			'extends' => $type,
			'api.CustomField.get' => [
				'is_active' => 1,
				'options' => [
					'limit' => 0, // No limit.
				],
			],
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Add subtype if present.
		if ( ! empty( $subtype ) ) {
			//$params['extends_entity_column_value'] = $subtype;
		}

		// Call the API.
		$result = civicrm_api( 'CustomGroup', 'get', $params );

		// Override return if we get some.
		if (
			$result['is_error'] == 0 AND
			isset( $result['values'] ) AND
			count( $result['values'] ) > 0
		) {

			// We only need the results from the chained API data.
			foreach( $result['values'] as $key => $value ) {
				foreach( $value['api.CustomField.get']['values'] as $subkey => $item ) {
					$custom_fields[$value['title']][] = $item;
				}
			}

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$type] ) ) {
			$pseudocache[$type] = $custom_fields;
		}

		// --<
		return $custom_fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the choices for the Setting of a "Select" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param str $custom_field_id The numeric ID of the CiviCRM Custom Field.
	 * @return array $choices The choices for the field.
	 */
	public function select_choices_get( $custom_field_id ) {

		// Init return.
		$choices = [];

		// Get Custom Field data.
		$field_data = $this->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $choices;
		}

		// Bail if it's not a data type that can have a "Select".
		if ( ! in_array( $field_data['data_type'], $this->data_types ) ) {
			return $choices;
		}

		// Bail if it's not a type of "Select".
		if ( ! in_array( $field_data['html_type'], $this->select_types ) ) {
			return $choices;
		}

		// Populate with child options where possible.
		if ( ! empty( $field_data['option_group_id'] ) ) {
			$choices = CRM_Core_OptionGroup::valuesByID( absint( $field_data['option_group_id'] ) );
		}

		// "Country" selects require special handling.
		$country_selects = [ 'Select Country', 'Multi-Select Country' ];
		if ( in_array( $field_data['html_type'], $country_selects ) ) {
			$choices = CRM_Core_PseudoConstant::country();
		}

		// "State/Province" selects also require special handling.
		$state_selects = [ 'Select State/Province', 'Multi-Select State/Province' ];
		if ( in_array( $field_data['html_type'], $state_selects ) ) {
			$choices = CRM_Core_PseudoConstant::stateProvince();
		}

		// --<
		return $choices;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Select" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function select_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// ACF "Multi-Select".
		if ( $field['multiple'] == 1 ) {

			// Filter fields to include only Multi-Select types.
			$select_types = [ 'Multi-Select', 'Multi-Select Country', 'Multi-Select State/Province' ];

		// ACF "Autocomplete-Select". Sort of.
		} elseif ( $field['ui'] == 1 AND $field['ajax'] == 1 ) {

			// Filter fields to include only Autocomplete-Select.
			$select_types = [ 'Autocomplete-Select' ];

		// Otherwise, fall back.
		} else {

			// Filter fields to include only "Select" types.
			$select_types = [ 'Select', 'Select Country', 'Select State/Province' ];

		}

		// Filter fields to include only those which are compatible.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND in_array( $custom_field['data_type'], $this->data_types ) ) {
					if ( ! empty( $custom_field['html_type'] ) AND  in_array( $custom_field['html_type'], $select_types ) ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Get the choices for the Setting of a "Radio" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param str $custom_field_id The numeric ID of the CiviCRM Custom Field.
	 * @return array $choices The choices for the field.
	 */
	public function radio_choices_get( $custom_field_id ) {

		// Init return.
		$choices = [];

		// Get Custom Field data.
		$field_data = $this->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $choices;
		}

		// Bail if it's not a data type that can have a "Radio" sub-type.
		if ( ! in_array( $field_data['data_type'], $this->data_types ) ) {
			return $choices;
		}

		// Bail if it's not "Radio".
		if ( $field_data['html_type'] !== 'Radio' ) {
			return $choices;
		}

		// Get options.
		if ( ! empty( $field_data['option_group_id'] ) ) {
			$choices = CRM_Core_OptionGroup::valuesByID( absint( $field_data['option_group_id'] ) );
		}

		// --<
		return $choices;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Radio" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function radio_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only "Radio" HTML types.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND in_array( $custom_field['data_type'], $this->data_types ) ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Radio' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Get the choices for the Setting of a "Checkbox" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param str $custom_field_id The numeric ID of the CiviCRM Custom Field.
	 * @return array $choices The choices for the field.
	 */
	public function checkbox_choices_get( $custom_field_id ) {

		// Init return.
		$choices = [];

		// Get Custom Field data.
		$field_data = $this->plugin->civicrm->custom_field->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $choices;
		}

		// Bail if it's not "String".
		if ( $field_data['data_type'] !== 'String' ) {
			return $choices;
		}

		// Bail if it's not "Select".
		if ( $field_data['html_type'] !== 'CheckBox' ) {
			return $choices;
		}

		// Get options.
		if ( ! empty( $field_data['option_group_id'] ) ) {
			$choices = CRM_Core_OptionGroup::valuesByID( absint( $field_data['option_group_id'] ) );
		}

		// --<
		return $choices;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "CheckBox" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function checkbox_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only Boolean/Radio.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'String' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'CheckBox' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Get the Settings of a "Date" Field as required by a Custom Field.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The existing field data array.
	 * @param str $custom_field_id The numeric ID of the CiviCRM Custom Field.
	 * @return array $field The modified field data array.
	 */
	public function date_settings_get( $field, $custom_field_id ) {

		// Get Custom Field data.
		$field_data = $this->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $field;
		}

		// Bail if it's not Date.
		if ( $field_data['data_type'] !== 'Date' ) {
			return $field;
		}

		// Bail if it's not "Select Date".
		if ( $field_data['html_type'] !== 'Select Date' ) {
			return $field;
		}

		// Bail if the "Time Format" is set.
		if ( isset( $field_data['time_format'] ) ) {
			return $field;
		}

		// Get the mappings.
		$mappings = $this->plugin->mapper->date_mappings;

		// Get the ACF format.
		$acf_format = $mappings[$field_data['date_format']];

		// Set the date "format" attributes.
		$field['display_format'] = $acf_format;
		$field['return_format'] = $acf_format;

		// --<
		return $field;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Date" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function date_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only Date/Select Date.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Date' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Select Date' ) {
						if ( ! isset( $custom_field['time_format'] ) OR $custom_field['time_format'] == '0' ) {
							$filtered_fields[$custom_group_name][] = $custom_field;
						}
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Get the Settings of a "Date Time" Field as required by a Custom Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The existing field data array.
	 * @param str $custom_field_id The numeric ID of the CiviCRM Custom Field.
	 * @return array $field The modified field data array.
	 */
	public function date_time_settings_get( $field, $custom_field_id ) {

		// Get Custom Field data.
		$field_data = $this->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $field;
		}

		// Bail if it's not Date.
		if ( $field_data['data_type'] !== 'Date' ) {
			return $field;
		}

		// Bail if it's not "Select Date".
		if ( $field_data['html_type'] !== 'Select Date' ) {
			return $field;
		}

		// Bail if the "Time Format" is not set.
		if ( ! isset( $field_data['time_format'] ) OR $field_data['time_format'] == '0' ) {
			return $field;
		}

		// Get the date mappings.
		$date_mappings = $this->plugin->mapper->date_mappings;

		// Get the ACF format.
		$acf_format = $date_mappings[$field_data['date_format']];

		// Get the time mappings.
		$time_mappings = $this->plugin->mapper->time_mappings;

		// Append to the ACF format.
		if ( ! empty( $time_mappings[$field_data['time_format']] ) ) {
			$acf_format .= ' ' . $time_mappings[$field_data['time_format']];
		}

		// Set the date "format" attributes.
		$field['display_format'] = $acf_format;
		$field['return_format'] = $acf_format;

		// --<
		return $field;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Date Time" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function date_time_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only Date/Select Date.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Date' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Select Date' ) {
						if ( ! empty( $custom_field['time_format'] ) AND $custom_field['time_format'] != '0' ) {
							$filtered_fields[$custom_group_name][] = $custom_field;
						}
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Get the Settings of a "Text" Field as required by a Custom Field.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The existing field data array.
	 * @param str $custom_field_id The numeric ID of the CiviCRM Custom Field.
	 * @return array $field The modified field data array.
	 */
	public function text_settings_get( $field, $custom_field_id ) {

		// Get Custom Field data.
		$field_data = $this->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $field;
		}

		// Bail if it's not Alphanumeric.
		if ( $field_data['data_type'] !== 'String' ) {
			return $field;
		}

		// Bail if it's not Text.
		if ( $field_data['html_type'] !== 'Text' ) {
			return $field;
		}

		// Bail if there's no "text_length" attribute.
		if ( ! array_key_exists( 'text_length', $field_data ) ) {
			return $field;
		}

		// Set the "maxlength" attribute.
		$field['maxlength'] = $field_data['text_length'];

		// --<
		return $field;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Text" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function text_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only those of HTML type "Text".
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND in_array( $custom_field['data_type'], $this->data_types ) ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Text' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Wysiwyg" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function wysiwyg_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only Memo/RichTextEditor.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Memo' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'RichTextEditor' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "Textarea" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function textarea_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only Memo/TextArea.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Memo' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'TextArea' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "True/False" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function true_false_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only Boolean/Radio.
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Boolean' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Radio' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



	/**
	 * Filter the Custom Fields for the Setting of a "URL" Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $custom_fields The array of Custom Fields.
	 * @return array $filtered_fields The filtered Custom Fields.
	 */
	public function url_settings_filter( $field, $custom_fields ) {

		// Init return.
		$filtered_fields = [];

		// Filter fields to include only "Link".
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Link' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Link' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// --<
		return $filtered_fields;

	}



} // Class ends.




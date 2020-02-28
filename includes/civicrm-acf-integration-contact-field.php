<?php

/**
 * CiviCRM ACF Integration CiviCRM Contact Field Class.
 *
 * A class that encapsulates CiviCRM Contact Field functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Contact_Field {

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
	 * Common Contact Fields.
	 *
	 * These are mapped to their corresponding ACF Field types.
	 *
	 * The "display_name" Field is disabled for now - we need to decide if it
	 * should sync or whether the Post Title always maps to it.
	 *
	 * @since 0.3
	 * @access public
	 * @var array $contact_fields_common The common public Contact Fields.
	 */
	public $contact_fields_common = [
		//'display_name' => 'text',
		'nick_name' => 'text',
		'email' => 'text',
	];

	/**
	 * Contact Fields for Individuals.
	 *
	 * Mapped to their corresponding ACF Field types.
	 *
	 * @since 0.3
	 * @access public
	 * @var array $contact_fields_individual The public Contact Fields for Individuals.
	 */
	public $contact_fields_individual = [
		'prefix_id' => 'select',
		'first_name' => 'text',
		'last_name' => 'text',
		'middle_name' => 'text',
		'suffix_id' => 'select',
		'job_title' => 'text',
		'gender_id' => 'radio',
		'birth_date' => 'date_picker',
		'is_deceased' => 'true_false',
		'deceased_date' => 'date_picker',
		'employer_id' => 'civicrm_contact',
	];

	/**
	 * Contact Fields for Organisations.
	 *
	 * Mapped to their corresponding ACF Field types.
	 *
	 * @since 0.3
	 * @access public
	 * @var array $contact_fields_organization The public Contact Fields for Organisations.
	 */
	public $contact_fields_organization = [
		'legal_name' => 'text',
		'organization_name' => 'text',
		'sic_code' => 'text',
	];

	/**
	 * Contact Fields for Households.
	 *
	 * Mapped to their corresponding ACF Field types.
	 *
	 * @since 0.3
	 * @access public
	 * @var array $contact_fields_household The public Contact Fields for Households.
	 */
	public $contact_fields_household = [
		'household_name' => 'text',
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

		// Intercept Post updated (or created) from Contact events.
		add_action( 'civicrm_acf_integration_post_created', [ $this, 'post_edited' ], 10 );
		add_action( 'civicrm_acf_integration_post_edited', [ $this, 'post_edited' ], 10 );

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

		// Get the public Contact Fields for the top level type.
		$public_fields = $this->get_public( $args['contact_types'] );

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Contact Fields.
		if ( empty( $acf_fields['contact'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['contact'] AS $selector => $contact_field ) {

			// Skip if it's not a public Contact Field.
			if ( ! array_key_exists( $contact_field, $public_fields ) ) {
				continue;
			}

			// Does the mapped Contact Field exist?
			if ( isset( $args['objectRef']->$contact_field ) ) {

				// Modify value for ACF prior to update.
				$value = $this->value_get_for_acf(
					$args['objectRef']->$contact_field,
					$contact_field,
					$selector,
					$args['post_id']
				);

				// Update it.
				$this->plugin->acf->field->value_update( $selector, $value, $args['post_id'] );

			}

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the value of a Contact Field, formatted for ACF.
	 *
	 * @since 0.4.1
	 *
	 * @param mixed $value The Contact Field value.
	 * @param array $name The Contact Field name.
	 * @param str $selector The ACF Field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return mixed $value The formatted field value.
	 */
	public function value_get_for_acf( $value, $name, $selector, $post_id ) {

		// Bail if empty.
		if ( empty( $value ) ) {
			return $value;
		}

		// Bail if value is (string) 'null' which CiviCRM uses for some reason.
		if ( $value == 'null' ) {
			return '';
		}

		// Get the ACF type for this Contact Field.
		$type = $this->get_acf_type( $name );

		// Convert CiviCRM value to ACF value by Contact Field.
		switch( $type ) {

			// Unused at present.
			case 'select' :
			case 'checkbox' :

				// Convert if the value has the special CiviCRM array-like format.
				if ( false !== strpos( $value, CRM_Core_DAO::VALUE_SEPARATOR ) ) {
					$value = CRM_Utils_Array::explodePadded( $value );
				}

				break;

			// Used by "Birth Date" and "Deceased Date".
			case 'date_picker' :
			case 'date_time_picker' :

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

		// TODO: Filter here?

		/**
		 * When submitting the Contact "Edit" form in the CiviCRM back end, the
		 * email address is appended as an array. At other times, it is a string.
		 * We find the first "primary" email entry and use that.
		 */
		if ( $name == 'email' ) {

			// Maybe grab the email from the array.
			if ( is_array( $value ) ) {
				foreach( $value AS $email ) {
					if ( $email->is_primary == '1' ) {
						$value = $email->email;
						break;
					}
				}
			}

		}

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the "date format" for a given CiviCRM Contact Field.
	 *
	 * @since 0.4.1
	 *
	 * @param str $name The name of the Contact Field.
	 * @return str $format The date format.
	 */
	public function date_format_get( $name ) {

		// Init return.
		$format = '';

		// We only have a few to account for.
		$birth_fields = [ 'birth_date', 'deceased_date' ];

		// "Birth Date" and "Deceased Date" use the same preference.
		if ( in_array ( $name, $birth_fields ) ) {
			$format = CRM_Utils_Date::getDateFormat( 'birth' );
		}

		// If it's empty, fall back on CiviCRM-wide setting.
		if ( empty( $format ) ) {
			// No need yet - `getDateFormat()` already does this.
		}

		// --<
		return $format;

	}



	/**
	 * Get "age" as a string for a given date.
	 *
	 * @since 0.4.1
	 *
	 * @param str $date The date in CiviCRM-style "Ymdhis" format.
	 * @return str $age_string The age expressed as a string.
	 */
	public function date_age_get( $date ) {

		// Init return.
		$age_string = '';

		// CiviCRM has handy methods for this.
        $age_date = CRM_Utils_Date::customFormat( $date, '%Y%m%d' );
		$age = CRM_Utils_Date::calculateAge( $age_date );
		$years = CRM_Utils_Array::value( 'years', $age );
		$months = CRM_Utils_Array::value( 'months', $age );

		// Maybe construct string from years.
		if ( $years ) {
			$age_string = sprintf(
				_n( '%d year', '%d years', $years, 'civicrm-acf-integration' ),
				$years
			);
		}

		// Maybe construct string from months.
		if ( $months ) {
			$age_string = sprintf(
				_n( '%d month', '%d months', $months, 'civicrm-acf-integration' ),
				$months
			);
		}

		// --<
		return $age_string;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the "select" options for a given CiviCRM Contact Field.
	 *
	 * @since 0.4.1
	 *
	 * @param str $name The name of the Contact Field.
	 * @return array $options The array of field options.
	 */
	public function options_get( $name ) {

		// Init return.
		$options = [];

		// We only have a few to account for.

		// Individual Prefix.
		if ( $name == 'prefix_id' ) {
			$option_group = $this->option_group_get( 'individual_prefix' );
			if ( ! empty( $option_group ) ) {
				$options = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );
			}
		}

		// Individual Suffix.
		if ( $name == 'suffix_id' ) {
			$option_group = $this->option_group_get( 'individual_suffix' );
			if ( ! empty( $option_group ) ) {
				$options = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );
			}
		}

		// Gender.
		if ( $name == 'gender_id' ) {
			$option_group = $this->option_group_get( 'gender' );
			if ( ! empty( $option_group ) ) {
				$options = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );
			}
		}

		// --<
		return $options;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Option Group for a Contact Field.
	 *
	 * @since 0.4.1
	 *
	 * @param str $name The name of the option group.
	 * @return array $option_group The array of option group data.
	 */
	public function option_group_get( $name ) {

		// Init return.
		$options = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $options;
		}

		// Define query params.
		$params = [
			'name' => $name,
			'version' => 3,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionGroup', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $options;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_data;
		}

		// The result set should contain only one item.
		$options = array_pop( $result['values'] );

		// --<
		return $options;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Contact Fields for an ACF Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $contact_fields The array of Contact Fields.
	 */
	public function get_for_acf_field( $field ) {

		// Init return.
		$contact_fields = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $contact_fields;
		}

		// Get the Contact Type.
		$contact_type_key = $this->plugin->civicrm->contact_type->acf_field_key_get();
		$contact_type_id = $field_group[$contact_type_key];

		// Get Contact Type hierarchy.
		$contact_types = $this->civicrm->contact_type->hierarchy_get_by_id( $contact_type_id );

		// Get public fields of this type.
		$contact_fields = $this->data_get( $contact_types['type'], $field['type'], 'public' );

		// --<
		return $contact_fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Contact Field options for a given Field ID.
	 *
	 * @since 0.4.1
	 *
	 * @param str $name The name of the field.
	 * @return array $field The array of field data.
	 */
	public function get_by_name( $name ) {

		// Init return.
		$field = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $field;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'name' => $name,
			'action' => 'get',
		];

		// Call the API.
		$result = civicrm_api( 'Contact', 'getfield', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $field;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $field;
		}

		// The result set is the item.
		$field = $result['values'];

		// --<
		return $field;

	}



	/**
	 * Get the core Fields for a CiviCRM Contact Type.
	 *
	 * @since 0.3
	 *
	 * @param array $contact_type The Contact Type to query.
	 * @param str $field_type The type of ACF Field.
	 * @param str $filter The token by which to filter the array of fields.
	 * @return array $fields The array of field names.
	 */
	public function data_get( $contact_type = 'Individual', $field_type = '', $filter = 'none' ) {

		// Only do this once per Contact Type, Field Type and filter.
		static $pseudocache;
		if ( isset( $pseudocache[$filter][$contact_type][$field_type] ) ) {
			return $pseudocache[$filter][$contact_type][$field_type];
		}

		// Init return.
		$fields = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $fields;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Contact', 'getfields', $params );

		// Override return if we get some.
		if (
			$result['is_error'] == 0 AND
			isset( $result['values'] ) AND
			count( $result['values'] ) > 0
		) {

			// Check for no filter.
			if ( $filter == 'none' ) {

				// Grab all of them.
				$fields = $result['values'];

			// Check public filter.
			} elseif ( $filter == 'public' ) {

				// Init fields array.
				$contact_fields = [];

				// Check against different field sets per type.
				if ( $contact_type == 'Individual' ) {
					$contact_fields = $this->contact_fields_individual;
				}
				if ( $contact_type == 'Organization' ) {
					$contact_fields = $this->contact_fields_organization;
				}
				if ( $contact_type == 'Household' ) {
					$contact_fields = $this->contact_fields_household;
				}

				// Combine these with commons fields.
				$contact_fields = array_merge( $contact_fields, $this->contact_fields_common );

				// Skip all but those defined in our Contact Fields arrays.
				$public_fields = [];
				foreach ( $result['values'] AS $key => $value ) {
					if ( array_key_exists( $value['name'], $contact_fields ) ) {
						$public_fields[] = $value;
					}
				}

				// Skip all but those mapped to the type of ACF Field.
				foreach ( $public_fields AS $key => $value ) {
					if ( $field_type == $contact_fields[$value['name']] ) {
						$fields[] = $value;
					}
				}

			}

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$filter][$contact_type][$field_type] ) ) {
			$pseudocache[$filter][$contact_type][$field_type] = $fields;
		}

		// --<
		return $fields;

	}



	/**
	 * Get the Fields for a CiviCRM Contact Type.
	 *
	 * @since 0.4.1
	 *
	 * @param array $types The Contact Type(s) to query.
	 * @return array $fields The array of field names.
	 */
	public function get_public( $types = [ 'Individual' ] ) {

		// Init return.
		$contact_fields = [];

		// Check against different field sets per type.
		if ( in_array( 'Individual', $types ) ) {
			$contact_fields = $this->contact_fields_individual;
		}
		if ( in_array( 'Organization', $types ) ) {
			$contact_fields = $this->contact_fields_organization;
		}
		if ( in_array( 'Household', $types ) ) {
			$contact_fields = $this->contact_fields_household;
		}

		// Combine these with commons fields.
		$contact_fields = array_merge( $contact_fields, $this->contact_fields_common );

		// --<
		return $contact_fields;

	}



	/**
	 * Get the Fields for an ACF Field and mapped to a CiviCRM Contact Type.
	 *
	 * @since 0.4.1
	 *
	 * @param array $types The Contact Type(s) to query.
	 * @param str $type The type of ACF Field.
	 * @return array $fields The array of field names.
	 */
	public function get_by_acf_type( $types = [ 'Individual' ], $type = '' ) {

		// Init return.
		$contact_fields = [];

		// Get the public fields defined in this class.
		$public_fields = $this->get_public( $types );

		// Skip all but those mapped to the type of ACF Field.
		foreach ( $public_fields AS $key => $value ) {
			if ( $type == $value ) {
				$contact_fields[$key] = $value;
			}
		}

		// --<
		return $contact_fields;

	}



	/**
	 * Get the ACF Field Type for a Contact Field.
	 *
	 * @since 0.4.1
	 *
	 * @param str $name The name of the Contact Field.
	 * @return array $fields The array of field names.
	 */
	public function get_acf_type( $name = '' ) {

		// Init return.
		$type = false;

		// Combine different arrays.
		$contact_fields = $this->contact_fields_individual +
						  $this->contact_fields_organization +
						  $this->contact_fields_household +
						  $this->contact_fields_common;

		// if the key exists, return the value - which is the ACF Type.
		if ( array_key_exists( $name, $contact_fields ) ) {
			$type = $contact_fields[$name];
		}

		// --<
		return $type;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the choices for the Setting of a "Select" Field.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The field data array.
	 * @param str $contact_field_name The CiviCRM Contact Field name.
	 * @return array $choices The choices for the field.
	 */
	public function select_choices_get( $contact_field_name ) {

		// Init return.
		$choices = [];

		// Get the array of options for this Contact Field.
		$choices = $this->options_get( $contact_field_name );

		// --<
		return $choices;

	}



	/**
	 * Get the choices for the Setting of a "Radio" Field.
	 *
	 * @since 0.4.1
	 *
	 * @param str $contact_field_name The CiviCRM Contact Field name.
	 * @return array $choices The choices for the field.
	 */
	public function radio_choices_get( $contact_field_name ) {

		// Init return.
		$choices = [];

		// Get the array of options for this Contact Field.
		$choices = $this->plugin->civicrm->contact_field->options_get( $contact_field_name );

		// --<
		return $choices;

	}



	/**
	 * Get the Settings of a "Date" Field as required by a Contact Field.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The field data array.
	 * @param str $contact_field_name The CiviCRM Contact Field name.
	 * @return array $choices The choices for the field.
	 */
	public function date_settings_get( $field, $contact_field_name ) {

		// Get Contact Field data.
		$format = $this->date_format_get( $contact_field_name );

		// Get the ACF format.
		$acf_format = $this->plugin->mapper->date_mappings[$format];

		// Set the date "format" attributes.
		$field['display_format'] = $acf_format;
		$field['return_format'] = $acf_format;

		// --<
		return $field;

	}



	/**
	 * Get the Settings of a "Text" Field as required by a Contact Field.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The field data array.
	 * @param str $contact_field_name The CiviCRM Contact Field name.
	 * @return array $choices The choices for the field.
	 */
	public function text_settings_get( $field, $contact_field_name ) {

		// Get Contact Field data.
		$field_data = $this->plugin->civicrm->contact_field->get_by_name( $contact_field_name );

		// Set the "maxlength" attribute.
		if ( ! empty( $field_data['maxlength'] ) ) {
			$field['maxlength'] = $field_data['maxlength'];
		}

		// --<
		return $field;

	}



} // Class ends.




<?php
/**
 * CiviCRM Contact Field Class.
 *
 * Handles CiviCRM Contact Field functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Contact Field Class.
 *
 * A class that encapsulates CiviCRM Contact Field functionality.
 *
 * @since 0.3
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
		'nick_name' => 'text',
		'image_URL' => 'image',
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

		// Intercept Post created, updated (or synced) from Contact events.
		add_action( 'civicrm_acf_integration_post_created', [ $this, 'post_edited' ], 10 );
		add_action( 'civicrm_acf_integration_post_edited', [ $this, 'post_edited' ], 10 );
		add_action( 'civicrm_acf_integration_post_contact_sync_to_post', [ $this, 'contact_sync_to_post' ], 10 );

		// Intercept Contact Image delete.
		add_action( 'civicrm_postSave_civicrm_contact', [ $this, 'image_deleted' ], 10 );
		add_action( 'delete_attachment', [ $this, 'image_attachment_deleted' ], 10 );

		// TODO: Add hooks to Relationships to detect Employer changes via that route.

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
	public function contact_sync_to_post( $args ) {

		// Get Employer ID for this Contact.
		$employer_id = CRM_Core_DAO::getFieldValue( 'CRM_Contact_DAO_Contact', $args['objectId'], 'employer_id' );

		// If we get one, add it.
		if ( ! isset( $args['objectRef']->employer_id ) ) {
			$args['objectRef']->employer_id = $employer_id;
		}

		// Re-use Post Edited method.
		$this->post_edited( $args );

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
	public function post_edited( $args ) {

		// Get originating Entity.
		$originating_entity = $this->plugin->mapper->entity_get();

		// Get the Contact Type hierarchy.
		$hierarchy = $this->plugin->civicrm->contact_type->hierarchy_get_for_contact( $args['objectRef'] );

		// Get the public Contact Fields for the top level type.
		$public_fields = $this->get_public( $hierarchy );

		// Get the ACF Fields for this ACF "Post ID".
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
	 * @param mixed $post_id The ACF "Post ID".
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

				// Date Picker test.
				if ( $acf_setting['type'] == 'date_picker' ) {

					// Contact edit passes a Y-m-d format, so test for that.
					$datetime = DateTime::createFromFormat( 'Y-m-d', $value );

					// Contact create passes a different format, so test for that.
					if ( $datetime === false ) {
						$datetime = DateTime::createFromFormat( 'YmdHis', $value );
					}

					// Convert to ACF format.
					$value = $datetime->format( 'Ymd' );

				// Date & Time Picker test.
				} elseif ( $acf_setting['type'] == 'date_time_picker' ) {
					$datetime = DateTime::createFromFormat( 'YmdHis', $value );
					$value = $datetime->format( 'Y-m-d H:i:s' );
				}

				break;

			// Used by "Contact Image".
			case 'image' :

				// Delegate to method, expect an Attachment ID.
				$value = $this->image_value_get_for_acf( $value, $name, $selector, $post_id );

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

		// Maybe construct string for less than a month.
		if ( empty( $years ) AND $months === 0 ) {
			$age_string = __( 'Under a month', 'civicrm-acf-integration' );
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

		// Skip if this is not a Contact Field Group.
		$is_contact_field_group = $this->civicrm->contact->is_contact_field_group( $field_group );
		if ( $is_contact_field_group !== false ) {

			// Loop through the Post Types.
			foreach( $is_contact_field_group AS $post_type_name ) {

				// Get the Contact Type ID.
				$contact_type_id = $this->civicrm->contact_type->id_get_for_post_type( $post_type_name );

				// Get Contact Type hierarchy.
				$contact_types = $this->civicrm->contact_type->hierarchy_get_by_id( $contact_type_id );

				// Get public fields of this type.
				$contact_fields_for_type = $this->data_get( $contact_types['type'], $field['type'], 'public' );

				// Merge with return array.
				$contact_fields = array_merge( $contact_fields, $contact_fields_for_type );

			}

		}

		/**
		 * Filter the Contact Fields.
		 *
		 * @since 0.8
		 *
		 * @param array $contact_fields The existing array of Contact Fields.
		 * @param array $field_group The ACF Field Group data array.
		 * @param array $field The ACF Field data array.
		 * @return array $contact_fields The modified array of Contact Fields.
		 */
		$contact_fields = apply_filters(
			'civicrm_acf_integration_contact_field_get_for_acf_field',
			$contact_fields, $field_group, $field
		);

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



	/**
	 * Modify the Settings of an "Image" Field as required by a Contact Field.
	 *
	 * The only modification at the moment is to "derestrict" the library that
	 * the Field can access. This is done so that multiple Posts can share the
	 * same Attachment - useful for situations where a Contact has multiple
	 * Contact Types that are mapped to Custom Post Types.
	 *
	 * @since 0.8.1
	 *
	 * @param array $field The field data array.
	 * @param str $contact_field_name The CiviCRM Contact Field name.
	 * @return array $choices The choices for the field.
	 */
	public function image_settings_get( $field, $contact_field_name ) {

		// Set Field source library.
		$field['library'] = 'all';

		// --<
		return $field;

	}



	/**
	 * Get the value of an "Image" Field as required by an ACF Field.
	 *
	 * @since 0.8.1
	 *
	 * @param mixed $value The Contact Field value (the Image URL).
	 * @param array $name The Contact Field name.
	 * @param str $selector The ACF Field selector.
	 * @param mixed $post_id The ACF "Post ID".
	 * @return mixed $value The formatted field value.
	 */
	public function image_value_get_for_acf( $value, $name, $selector, $post_id ) {

		// Grab the raw data (Attachment ID) from the ACF Field.
		$existing = get_field( $selector, $post_id, false );

		// Assume no sync necessary.
		$sync = false;

		// If there's no ACF data.
		if ( empty( $existing ) ) {

			// We're good to sync.
			$sync = true;

		} else {

			// Grab the the full size Image data.
			$url = wp_get_attachment_image_url( (int) $existing, 'full' );

			// If the URL has changed.
			if ( ! empty( $url ) AND $url != $value ) {

				// Sync the new image.
				$sync = true;

			} else {

				// The ID is the existing value.
				$id = $existing;

			}

		}

		// Maybe transfer the CiviCRM Contact Image to WordPress.
		if ( $sync === true ) {

			// Get Contact ID for this ACF "Post ID".
			$contact_id = $this->plugin->acf->field->query_contact_id( $post_id );

			// Can't proceed if there's no Contact ID.
			if ( $contact_id === false ) {
				return '';
			}

			// Get full Contact data.
			$contact = $this->plugin->civicrm->contact->get_by_id( $contact_id );

			/*
			 * Decode the current Image URL.
			 *
			 * We have to do this because Contact Images may have been uploaded
			 * from a Profile embedded via a Shortcode. Since CiviCRM always runs
			 * Contact Image URLs through htmlentities() before saving, the URLs
			 * get "double-encoded" when they are parsed by `redirect_canonical()`
			 * and result in 404s.
			 *
			 * This is only a problem when using Profiles via Shortcodes.
			 *
			 * @see CRM_Contact_BAO_Contact::processImageParams()
			 */
			$url = html_entity_decode( $contact['image_URL'] );

			// Maybe fix the following function.
			add_filter( 'attachment_url_to_postid', [ $this, 'image_url_to_post_id_helper' ], 10, 2 );

			// First check for an existing Attachment ID.
			$possible_id = attachment_url_to_postid( $url );

			// Remove the fix.
			remove_filter( 'attachment_url_to_postid', [ $this, 'image_url_to_post_id_helper' ], 10 );

			// If no Attachment ID is found.
			if ( $possible_id === 0 ) {

				// Grab the filename as the "title" if we can.
				if ( false === strpos( $url, 'photo=' ) ) {
					$title = __( 'CiviCRM Contact Image', 'civicrm-acf-integration' );
				} else {
					$title = explode( 'photo=', $url )[1];
				}

				// Only assign to a Post if the ACF "Post ID" is numeric.
				if ( ! is_numeric( $post_id ) ) {
					$target_post_id = null;
				} else {
					$target_post_id = $post_id;
				}

				// Possibly include the required files.
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				// Transfer the CiviCRM Contact Image to WordPress and grab ID.
				$id = media_sideload_image( $url, $target_post_id, $title, 'id' );

				// If there's an error.
				if ( is_wp_error( $id ) ) {

					/*
					 * It could be that the Contact Image URL is messed up because
					 * it has been uploaded via a Profile form in a Shortcode.
					 *
					 * Reconstruct the URL via the Base Page if we can.
					 */

					// Bail if there is no filename to grab.
					if ( false === strpos( $url, 'photo=' ) ) {
						return '';
					}

					// Grab the filename.
					$filename = explode( 'photo=', $url )[1];

					// Retrieve the Base Page setting.
					$basepage_slug = civicrm_api( 'Setting', 'getvalue', [
						'version' => 3,
						'name' => 'wpBasePage',
						'group' => 'CiviCRM Preferences',
					] );

					// Query for the Base Page.
					$pages = get_posts( [
						'post_type' => 'page',
						'name' => strtolower( $basepage_slug ),
						'post_status' => 'publish',
						'posts_per_page' => 1,
					] );

					// Bail if the Base Page was not found.
					if ( empty( $pages ) OR ! is_array( $pages ) ) {
						return '';
					}

					// Grab what should be the only item.
					$basepage = array_pop( $pages );

					// Get the Base Page URL.
					$basepage_url = trailingslashit( get_permalink( $basepage ) );

					// Build URL to Image file.
					$url = $basepage_url . 'contact/imagefile/?photo=' . $filename;

					// Transfer the CiviCRM Contact Image to WordPress and grab ID.
					$id = media_sideload_image( $url, $target_post_id, $title, 'id' );

					// If there's still an error.
					if ( is_wp_error( $id ) ) {

						// Log as much as we can.
						$e = new \Exception();
						$trace = $e->getTraceAsString();
						error_log( print_r( [
							'method' => __METHOD__,
							'error' => $id,
							'value' => $value,
							'name' => $name,
							'selector' => $selector,
							'post_id' => $post_id,
							'existing' => $existing,
							'contact' => $contact,
							//'backtrace' => $trace,
						], true ) );

						// Empty return.
						return '';

					}

				}

				// Grab the the full size Image data.
				$url = wp_get_attachment_image_url( (int) $id, 'full' );

				// Remove all internal CiviCRM hooks.
				$this->plugin->mapper->hooks_civicrm_remove();

				/**
				 * Broadcast that we're about to reverse-sync to a Contact.
				 *
				 * @since 0.8.1
				 *
				 * @param $contact_data The array of Contact data.
				 */
				do_action( 'cai/contact_field/reverse_sync/pre' );

				// Bare-bones data.
				$contact_data = [
					'id' => $contact_id,
					'image_URL' => $url,
				];

				// Save the Attachment URL back to the Contact.
				$result = $this->civicrm->contact->update( $contact_data );

				/**
				 * Broadcast that we have reverse-synced to a Contact.
				 *
				 * @since 0.8.1
				 *
				 * @param $contact_data The array of Contact data.
				 */
				do_action( 'cai/contact_field/reverse_sync/post', $contact_data );

				// Restore all internal CiviCRM hooks.
				$this->plugin->mapper->hooks_civicrm_add();

			} else {

				// Let's use this Attachment ID.
				$id = $possible_id;

			}

		}

		// Get the Attachment for the ID we've determined.
		$attachment = acf_get_attachment( $id );

		// The value in ACF is the Attachment ID.
		$value = $attachment['ID'];

		// --<
		return $value;

	}



	/**
	 * Tries to convert an Attachment URL (for intermediate/edited sized image) into a Post ID.
	 *
	 * Formatted version of the following Gist:
	 *
	 * @see https://gist.github.com/pbiron/d72a5d3b63e7077df767735464b2769c
	 *
	 * Produces incorrect results with the following sequence prior to WordPress 5.3.1:
	 *
	 * 1) Set thumbnail site to 150x150;
	 * 2) Upload foo-150x150.jpg;
	 * 3) Upload foo.jpg;
	 * 4) Call attachment_url_to_post_id( 'https://host/wp-content/uploads/foo-150x150.jpg' )
	 *
	 * @see https://core.trac.wordpress.org/ticket/44095
	 *
	 * Produces incorrect results after the following sequence:
	 *
	 * 1) Set thumbnail site to 150x150;
	 * 2) Upload a 300x300 image foo.jpg;
	 * 3) Edit foo.jpg and scale to 200x200;
	 * 4) Regenerate intermediate sized images
	 *    (e.g. with https://wordpress.org/plugins/regenerate-thumbnails/)
	 * 5) Call attachment_url_to_post_id( 'https://host/wp-content/uploads/foo-150x150.jpg' )
	 *
	 * @see https://core.trac.wordpress.org/ticket/44127
	 *
	 * @since 0.8.1
	 *
	 * @param str $url The URL to resolve.
	 * @return int The found Post ID, or 0 on failure.
	 */
	public function image_url_to_post_id_helper( $post_id, $url ) {

		global $wpdb;

 		// Bail if a Post ID was found.
 		if ( $post_id ) {
 			return $post_id;
 		}

 		// Start by setting up a few vars the same way attachment_url_to_postid() does.
		$dir = wp_get_upload_dir();
		$path = $url;

		$site_url = parse_url( $dir['url'] );
		$image_path = parse_url( $path );

		// Force the protocols to match if needed.
		if ( isset( $image_path['scheme'] ) AND ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
			$path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
		}

		if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
			$path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
		}

		$basename = wp_basename( $path );
		$dirname = dirname( $path );

		/*
		 * The "LIKE" we search for is the serialized form of $basename to reduce
		 * the number of false positives we have to deal with.
		 */
		$sql = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta
			 WHERE meta_key IN ( '_wp_attachment_metadata', '_wp_attachment_backup_sizes' ) AND meta_value LIKE %s",
			'%' . serialize( $basename ) . '%'
		);

		$results = $wpdb->get_results( $sql );
		foreach( $results AS $row ) {

			if ( '_wp_attachment_metadata' === $row->meta_key ) {

				$meta = maybe_unserialize( $row->meta_value );
				if ( dirname( $meta['file'] ) === $dirname AND in_array( $basename, wp_list_pluck( $meta['sizes'], 'file' ) ) ) {
					// URL is for a registered intermediate size.
					$post_id = $row->post_id;
					break;
				}

			} else {

				// See if URL is for a "backup" of an edited image.
				$backup_sizes = maybe_unserialize( $row->meta_value );

				if ( in_array( $basename, wp_list_pluck( $backup_sizes, 'file' ) ) ) {

					/*
					 * URL is possibly for a "backup" of an edited image.
					 * get the meta for the "original" attachment and perform the equivalent
					 * test we did above for '_wp_attachment_metadata' === $row->meta_key
					 */
					$sql = $wpdb->prepare(
						"SELECT meta_value FROM $wpdb->postmeta
						 WHERE post_id = %d AND meta_key = '_wp_attachment_metadata'",
						$row->post_id
					);

					$meta = maybe_unserialize( $wpdb->get_var( $sql ) );
					if ( isset( $meta['file'] ) AND dirname( $meta['file'] ) === $dirname ) {
						// URL is for a "backup" of an edited image.
						$post_id = $row->post_id;
						break;
					}

				}

			}

		}

		// --<
		return $post_id;

	}



	/**
	 * Callback for the Contact postSave hook.
	 *
	 * Since neither "civicrm_pre" nor "civicrm_post" fire when a Contact Image
	 * is deleted via the "Edit Contact" screen, this callback attempts to
	 * identify when this happens and then acts accordingly.
	 *
	 * @since 0.8.1
	 *
	 * @param object $objectRef The DAO object.
	 */
	public function image_deleted( $objectRef ) {

		// Bail if not Contact save operation.
		if ( ! ( $objectRef instanceof CRM_Contact_BAO_Contact ) ) {
			return;
		}

		// Bail if no Contact ID.
		if ( empty( $objectRef->id ) ) {
			return;
		}

		// Bail if image_URL isn't the string 'null'.
		if ( $objectRef->image_URL !== 'null' ) {
			return;
		}

		// Bail if GET doesn't contain the path we want.
		if ( empty( $_GET['q'] ) OR $_GET['q'] != 'civicrm/contact/image' ) {
			return;
		}

		// Bail if GET doesn't contain the matching Contact ID.
		if ( empty( $_GET['cid'] ) OR $_GET['cid'] != $objectRef->id ) {
			return;
		}

		// Bail if GET doesn't contain the delete action.
		if ( empty( $_GET['action'] ) OR $_GET['action'] != 'delete' ) {
			return;
		}

		// Bail if GET doesn't contain the confirmed flag.
		if ( empty( $_GET['confirmed'] ) OR $_GET['confirmed'] != 1 ) {
			return;
		}

		// Get the full Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $objectRef->id );

		// Bail if something went wrong.
		if ( $contact === false ) {
			return;
		}

		// We need to pass an instance of CRM_Contact_DAO_Contact.
		$object = new CRM_Contact_DAO_Contact();
		$object->id = $objectRef->id;

		// Trigger the sync process via the Mapper.
		$this->plugin->mapper->contact_edited( 'edit', $contact['contact_type'], $objectRef->id, $object );

	}



	/**
	 * Fires just before an Attachment is deleted.
	 *
	 * ACF Image Fields store the Attachment ID, so when an Attachment is deleted
	 * (and depending on the return format) nothing bad happens. CiviCRM Contact
	 * Images are stored as URLs - so when the actual file is missing, we get a
	 * 404 and a broken image icon.
	 *
	 * This callback tries to mitigate this by searching for Contacts that have
	 * the Contact Image that's being deleted and triggers the sync process for
	 * those that are found by deleting their Image URL.
	 *
	 * @since 0.8.1
	 *
	 * @param int $post_id The numeric ID of the Attachment.
	 */
	public function image_attachment_deleted( $post_id ) {

		// Grab the the full size Image URL.
		$image_url = wp_get_attachment_image_url( $post_id, 'full' );

		// Bail if the Image URL is empty.
		if ( empty( $image_url ) ) {
			return;
		}

		// Search for Contacts.
		$contacts = $this->civicrm->contact->get_by_image( $image_url );

		// Bail if there aren't any.
		if ( empty( $contacts ) ) {
			return;
		}

		// Process all of them.
		foreach( $contacts AS $contact ) {

			// Bare-bones data.
			$contact_data = [
				'id' => $contact['contact_id'],
				'image_URL' => '',
			];

			// Clear the Image URL for the Contact.
			$result = $this->civicrm->contact->update( $contact_data );

		}

	}



} // Class ends.




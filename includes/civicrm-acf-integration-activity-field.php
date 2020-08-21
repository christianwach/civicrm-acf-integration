<?php
/**
 * CiviCRM Activity Field Class.
 *
 * Handles CiviCRM Activity Field functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.7.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Activity Field Class.
 *
 * A class that encapsulates CiviCRM Activity Field functionality.
 *
 * @since 0.7.3
 */
class CiviCRM_ACF_Integration_CiviCRM_Activity_Field {

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
	 * Built-in Activity Fields.
	 *
	 * These are mapped to their corresponding ACF Field types.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var array $activity_fields The public Activity Fields.
	 */
	public $activity_fields = [
		'created_date' => 'date_time_picker',
		'modified_date' => 'date_time_picker',
		'activity_date_time' => 'date_time_picker',
		'status_id' => 'select',
		'priority_id' => 'select',
		'engagement_level' => 'select',
		'duration' => 'text',
		'location' => 'text',
		'source_contact_id' => 'civicrm_activity_creator',
		'target_contact_id' => 'civicrm_activity_target',
		'assignee_contact_id' => 'civicrm_activity_assignee',
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

		// Intercept Post created, updated (or synced) from Activity events.
		add_action( 'civicrm_acf_integration_post_activity_created', [ $this, 'post_edited' ], 10 );
		add_action( 'civicrm_acf_integration_post_activity_edited', [ $this, 'post_edited' ], 10 );
		add_action( 'civicrm_acf_integration_post_activity_sync', [ $this, 'activity_sync_to_post' ], 10 );

		// Maybe sync the various Activity "Date" Fields to ACF Fields attached to the WordPress Post.
		add_action( 'civicrm_acf_integration_activity_acf_fields_saved', [ $this, 'maybe_sync_fields' ], 10, 1 );

		// Some Activity "Text" Fields need their own validation.
		add_filter( 'acf/validate_value/type=text', [ $this, 'value_validate' ], 10, 4 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Validate the content of a Field.
	 *
	 * Some Activity Fields require validation.
	 *
	 * @since 0.7.3
	 *
	 * @param bool $valid The existing valid status.
	 * @param mixed $value The value of the Field.
	 * @param array $field The Field data array.
	 * @param string $input The input element's name attribute.
	 * @return bool|str $valid A string to display a custom error message, bool otherwise.
	 */
	public function value_validate( $valid, $value, $field, $input ) {

		// Bail if it's not required and is empty.
		if ( $field['required'] == '0' AND empty( $value ) ) {
			return $valid;
		}

		// Get the mapped Activity Field name if present.
		$activity_field_name = $this->plugin->civicrm->activity->activity_field_name_get( $field );

		// Bail if we don't have one.
		if ( $activity_field_name === false ) {
			return $valid;
		}

		// Validate depending on the field name.
		switch ( $activity_field_name ) {

			case 'duration' :
				// Must be an integer.
				if ( ! ctype_digit( $value ) ) {
					$valid = __( 'Must be an integer.', 'civicrm-acf-integration' );
				}
				break;

		}

		// --<
		return $valid;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a Post has been updated from an Activity via the Mapper.
	 *
	 * Sync any associated ACF Fields mapped to built-in Activity Fields.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of CiviCRM Activity and WordPress Post params.
	 */
	public function activity_sync_to_post( $args ) {

		// Re-use Post Edited method.
		$this->post_edited( $args );

	}



	/**
	 * Intercept when a Post has been updated from an Activity via the Mapper.
	 *
	 * Sync any associated ACF Fields mapped to built-in Activity Fields.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of CiviCRM Activity and WordPress Post params.
	 */
	public function post_edited( $args ) {

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Activity Fields.
		if ( empty( $acf_fields['activity'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['activity'] AS $selector => $activity_field ) {

			// Skip if it's not a public Activity Field.
			if ( ! array_key_exists( $activity_field, $this->activity_fields ) ) {
				continue;
			}

			// Does the mapped Activity Field exist?
			if ( isset( $args['objectRef']->$activity_field ) ) {

				// Modify value for ACF prior to update.
				$value = $this->value_get_for_acf(
					$args['objectRef']->$activity_field,
					$activity_field,
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
	 * Get the value of an Activity Field, formatted for ACF.
	 *
	 * @since 0.7.3
	 *
	 * @param mixed $value The Activity Field value.
	 * @param array $name The Activity Field name.
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

		// Get the ACF type for this Activity Field.
		$type = $this->get_acf_type( $name );

		// Convert CiviCRM value to ACF value by Activity Field.
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

					// Activity edit passes a Y-m-d format, so test for that.
					$datetime = DateTime::createFromFormat( 'Y-m-d', $value );

					// Activity create passes a different format, so test for that.
					if ( $datetime === false ) {
						$datetime = DateTime::createFromFormat( 'YmdHis', $value );
					}

					// Convert to ACF format.
					$value = $datetime->format( 'Ymd' );

				// Date & Time Picker test.
				} elseif ( $acf_setting['type'] == 'date_time_picker' ) {

					// Activity edit passes a YmdHis format, so test for that.
					$datetime = DateTime::createFromFormat( 'YmdHis', $value );

					// Activity API passes a different format, so test for that.
					if ( $datetime === false ) {
						$datetime = DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
					}

					// Convert to ACF format.
					$value = $datetime->format( 'Y-m-d H:i:s' );

				}

				break;

		}

		// TODO: Filter here?

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the "select" options for a given CiviCRM Activity Field.
	 *
	 * @since 0.7.3
	 *
	 * @param str $name The name of the Activity Field.
	 * @return array $options The array of field options.
	 */
	public function options_get( $name ) {

		// Init return.
		$options = [];

		// We only have a few to account for.

		// Status ID.
		if ( $name == 'status_id' ) {
			$option_group = $this->option_group_get( 'activity_status' );
			if ( ! empty( $option_group ) ) {
				$options = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );
			}
		}

		// Priority ID.
		if ( $name == 'priority_id' ) {
			$option_group = $this->option_group_get( 'priority' );
			if ( ! empty( $option_group ) ) {
				$options = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );
			}
		}

		// Engagement Level.
		if ( $name == 'engagement_level' ) {
			$option_group = $this->option_group_get( 'engagement_index' );
			if ( ! empty( $option_group ) ) {
				$options = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );
			}
		}

		// --<
		return $options;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Option Group for an Activity Field.
	 *
	 * @since 0.7.3
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
			return $activity_data;
		}

		// The result set should contain only one item.
		$options = array_pop( $result['values'] );

		// --<
		return $options;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Activity Fields for an ACF Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $activity_fields The array of Activity Fields.
	 */
	public function get_for_acf_field( $field ) {

		// Init return.
		$activity_fields = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $activity_fields;
		}

		// Bail if this is not an Activity Field Group.
		$is_activity_field_group = $this->civicrm->activity->is_activity_field_group( $field_group );
		if ( $is_activity_field_group === false ) {
			return $activity_fields;
		}

		// Loop through the Post Types.
		foreach( $is_activity_field_group AS $post_type_name ) {

			// Get public fields of this type.
			$activity_fields_for_type = $this->data_get( $field['type'], 'public' );

			// Merge with return array.
			$activity_fields = array_merge( $activity_fields, $activity_fields_for_type );

		}

		// --<
		return $activity_fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Activity Field options for a given Field ID.
	 *
	 * @since 0.7.3
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
		$result = civicrm_api( 'Activity', 'getfield', $params );

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
	 * Get the core Fields for a CiviCRM Activity Type.
	 *
	 * @since 0.7.3
	 *
	 * @param str $field_type The type of ACF Field.
	 * @param str $filter The token by which to filter the array of fields.
	 * @return array $fields The array of field names.
	 */
	public function data_get( $field_type = '', $filter = 'none' ) {

		// Only do this once per Field Type and filter.
		static $pseudocache;
		if ( isset( $pseudocache[$filter][$field_type] ) ) {
			return $pseudocache[$filter][$field_type];
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
		$result = civicrm_api( 'Activity', 'getfields', $params );

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

				// Skip all but those defined in our Activity Fields array.
				$public_fields = [];
				foreach ( $result['values'] AS $key => $value ) {
					if ( array_key_exists( $value['name'], $this->activity_fields ) ) {
						$public_fields[] = $value;
					}
				}

				// Skip all but those mapped to the type of ACF Field.
				foreach ( $public_fields AS $key => $value ) {
					if ( $field_type == $this->activity_fields[$value['name']] ) {
						$fields[] = $value;
					}
				}

			}

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$filter][$field_type] ) ) {
			$pseudocache[$filter][$field_type] = $fields;
		}

		// --<
		return $fields;

	}


	/**
	 * Get the Fields for an ACF Field and mapped to a CiviCRM Activity Type.
	 *
	 * @since 0.7.3
	 *
	 * @param str $type The type of ACF Field.
	 * @return array $fields The array of field names.
	 */
	public function get_by_acf_type( $type = '' ) {

		// Init return.
		$activity_fields = [];

		// Skip all but those mapped to the type of ACF Field.
		foreach ( $this->activity_fields AS $key => $value ) {
			if ( $type == $value ) {
				$activity_fields[$key] = $value;
			}
		}

		// --<
		return $activity_fields;

	}



	/**
	 * Get the ACF Field Type for an Activity Field.
	 *
	 * @since 0.7.3
	 *
	 * @param str $name The name of the Activity Field.
	 * @return array $fields The array of field names.
	 */
	public function get_acf_type( $name = '' ) {

		// Init return.
		$type = false;

		// If the key exists, return the value - which is the ACF Type.
		if ( array_key_exists( $name, $this->activity_fields ) ) {
			$type = $this->activity_fields[$name];
		}

		// --<
		return $type;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the choices for the Setting of a "Select" Field.
	 *
	 * @since 0.7.3
	 *
	 * @param str $activity_field_name The CiviCRM Activity Field name.
	 * @return array $choices The choices for the field.
	 */
	public function select_choices_get( $activity_field_name ) {

		// Init return.
		$choices = [];

		// Get the array of options for this Activity Field.
		$choices = $this->options_get( $activity_field_name );

		// --<
		return $choices;

	}



	/**
	 * Get the choices for the Setting of a "Radio" Field.
	 *
	 * @since 0.7.3
	 *
	 * @param str $activity_field_name The CiviCRM Activity Field name.
	 * @return array $choices The choices for the field.
	 */
	public function radio_choices_get( $activity_field_name ) {

		// Init return.
		$choices = [];

		// Get the array of options for this Activity Field.
		$choices = $this->plugin->civicrm->activity_field->options_get( $activity_field_name );

		// --<
		return $choices;

	}



	/**
	 * Get the Settings of a "DateTime" Field as required by an Activity Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The existing field data array.
	 * @param str $activity_field_name The CiviCRM Activity Field name.
	 * @return array $field The modified field data array.
	 */
	public function date_time_settings_get( $field, $activity_field_name ) {

		// Try and get CiviCRM format.
		//$civicrm_format = $this->date_time_format_get( $activity_field_name );

		// Set just the "Display Format" attribute.
		$field['display_format'] = 'Y-m-d H:i:s';

		// --<
		return $field;

	}



	/**
	 * Get the CiviCRM "DateTime format" for a given CiviCRM Activity Field.
	 *
	 * There is such a horrible mismatch between CiviCRM datetime formats and
	 * PHP datetime formats that I've given up trying to translate them.
	 *
	 * @since 0.7.3
	 *
	 * @param str $name The name of the Activity Field.
	 * @return str $format The DateTime format.
	 */
	public function date_time_format_get( $name ) {

		// Init return.
		$format = '';

		// We only have a few to account for.
		$date_fields = [ 'created_date', 'modified_date', 'activity_date_time' ];

		// If it's one of our fields.
		if ( in_array( $name, $date_fields ) ) {

			// Get the "Activity Date Time" preference.
			$format = CRM_Utils_Date::getDateFormat( 'activityDateTime' );

			// Override if we get the default.
			$config = CRM_Core_Config::singleton();
			if ( $config->dateInputFormat == $format ) {
				$format = '';
			}

		}

		// If it's empty, fall back to a sensible CiviCRM-formatted setting.
		if ( empty( $format ) ) {
			$format = 'yy-mm-dd';
		}

		// --<
		return $format;

	}



	/**
	 * Get the Settings of a "Text" Field as required by an Activity Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The field data array.
	 * @param str $activity_field_name The CiviCRM Activity Field name.
	 * @return array $choices The choices for the field.
	 */
	public function text_settings_get( $field, $activity_field_name ) {

		// Get Activity Field data.
		$field_data = $this->plugin->civicrm->activity_field->get_by_name( $activity_field_name );

		// Set the "maxlength" attribute.
		if ( ! empty( $field_data['maxlength'] ) ) {
			$field['maxlength'] = $field_data['maxlength'];
		}

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Maybe sync the Activity "Date" Fields to the ACF Fields on a WordPress Post.
	 *
	 * Activity Fields to maintain sync with:
	 *
	 * - The ACF "Activity Date Time" Field
	 * - The ACF "Created Date" Field
	 * - The ACF "Modified Date" Field
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of WordPress params.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function maybe_sync_fields( $args ) {

		// Bail if there's no Activity ID.
		if ( empty( $args['activity_id'] ) ) {
			return;
		}

		// Get the full Activity data.
		$activity = $this->plugin->civicrm->activity->get_by_id( $args['activity_id'] );

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $args['post']->ID ) ) {
			return;
		}

		// Let's make an array of params.
		$params = [
			'op' => 'edit',
			'objectName' => 'Activity',
			'objectId' => $args['activity_id'],
			'objectRef' => (object) $activity,
		];

		// Remove WordPress callbacks to prevent recursion.
		$this->plugin->mapper->hooks_wordpress_remove();

		// Update the Post.
		$this->plugin->post->activity_edited( $params );

		// Reinstate WordPress callbacks.
		$this->plugin->mapper->hooks_wordpress_add();

	}



} // Class ends.




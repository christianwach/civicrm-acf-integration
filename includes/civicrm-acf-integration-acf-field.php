<?php

/**
 * CiviCRM ACF Integration ACF Field Class.
 *
 * A class that encapsulates ACF Field functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_ACF_Field {

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
	 * @var object $acf The parent object.
	 */
	public $acf;



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
		$this->acf = $parent;

		// Init when this plugin is loaded.
		add_action( 'civicrm_acf_integration_acf_loaded', [ $this, 'register_hooks' ] );

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.3
	 */
	public function register_hooks() {

		// Add fields.
		//add_action( 'acf/init', [ $this, 'fields_add' ] );

		// TODO: Add constant that toggles between "acf/load_field" and "acf/update_field_group" methods.

		// Add setting to various Fields.
		add_action( 'acf/render_field_settings/type=true_false', [ $this, 'true_false_setting_add' ] );
		add_action( 'acf/render_field_settings/type=wysiwyg', [ $this, 'wysiwyg_setting_add' ] );
		add_action( 'acf/render_field_settings/type=textarea', [ $this, 'textarea_setting_add' ] );
		add_action( 'acf/render_field_settings/type=url', [ $this, 'url_setting_add' ] );
		add_action( 'acf/render_field_settings/type=email', [ $this, 'email_setting_add' ] );

		// Customise "Google Map" Fields.
		add_action( 'acf/render_field_settings/type=google_map', [ $this, 'google_map_setting_add' ] );
		add_action( 'acf/render_field/type=google_map', [ $this, 'google_map_styles_add' ] );

		// Customise "Select" Fields.
		add_action( 'acf/render_field_settings/type=select', [ $this, 'select_setting_add' ] );
		//add_filter( 'acf/load_field/type=select', [ $this, 'select_setting_modify' ] );
		//add_filter( 'acf/update_field/type=select', [ $this, 'select_field_update' ] );
		add_filter( 'acf/validate_value/type=select', [ $this, 'value_validate' ], 10, 4 );

		// Customise "Radio" Fields.
		add_action( 'acf/render_field_settings/type=radio', [ $this, 'radio_setting_add' ] );
		//add_filter( 'acf/load_field/type=radio', [ $this, 'radio_setting_modify' ] );
		add_filter( 'acf/validate_value/type=radio', [ $this, 'value_validate' ], 10, 4 );

		// Customise "CheckBox" Fields.
		add_action( 'acf/render_field_settings/type=checkbox', [ $this, 'checkbox_setting_add' ] );
		//add_filter( 'acf/load_field/type=checkbox', [ $this, 'checkbox_setting_modify' ] );

		// Customise "Date" Fields.
		add_action( 'acf/render_field_settings/type=date_picker', [ $this, 'date_picker_setting_add' ] );
		//add_filter( 'acf/load_field/type=date_picker', [ $this, 'date_picker_setting_modify' ] );
		add_filter( 'acf/load_value/type=date_picker', [ $this, 'date_picker_value_modify' ], 10, 3 );

		// Customise "Date Time" Fields.
		add_action( 'acf/render_field_settings/type=date_time_picker', [ $this, 'date_time_picker_setting_add' ] );
		//add_filter( 'acf/load_field/type=date_time_picker', [ $this, 'date_time_picker_setting_modify' ] );
		add_filter( 'acf/load_value/type=date_time_picker', [ $this, 'date_time_picker_value_modify' ], 10, 3 );

		// Customise "Text" Fields.
		add_action( 'acf/render_field_settings/type=text', [ $this, 'text_setting_add' ] );
		//add_filter( 'acf/load_field/type=text', [ $this, 'text_setting_modify' ] );
		add_filter( 'acf/validate_value/type=text', [ $this, 'value_validate' ], 10, 4 );

		// Intercept prepare Field event.
		//add_action( 'acf/prepare_field', [ $this, 'prepare' ] );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all mapped ACF Fields attached to a Post.
	 *
	 * We have to do this because both `get_fields()` and `get_field_objects()`
	 * DO NOT return the full set - only those with values that have been saved
	 * at one time or another and therefore exist as `post_meta`.
	 *
	 * As a result, this is not a reliable way to get ALL fields for a Post.
	 *
	 * Instead, we need to find all the Field Groups for a Post, then find
	 * all the Fields attached to the Field Group, then filter those so that
	 * only ones that are mapped to CiviCRM remain.
	 *
	 * @since 0.3
	 *
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $fields The mapped ACF Fields for this post.
	 */
	public function fields_get_for_post( $post_id ) {

		// Only do this once per Contact Type and mode.
		static $pseudocache;
		if ( isset( $pseudocache[$post_id] ) ) {
			return $pseudocache[$post_id];
		}

		// Init return.
		$acf_fields = [];

		// Get all Field Groups for this Post.
		$acf_field_groups = acf_get_field_groups( [ 'post_id' => $post_id ] );

		// Build our equivalent array to that returned by `get_fields()`.
		foreach( $acf_field_groups AS $acf_field_group ) {

			// Get all the fields in this Field Group.
			$fields_in_group = acf_get_fields( $acf_field_group['ID'] );

			// Add their Field "name" to the return.
			foreach( $fields_in_group AS $field_in_group ) {

				// Get the CiviCRM Custom Field and Contact Field.
				$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field_in_group );
				$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field_in_group );

				// Add if it has a reference to a CiviCRM Field.
				if ( ! empty( $custom_field_id ) OR ! empty( $contact_field_name ) ) {
					if ( ! empty( $custom_field_id ) ) {
						$acf_fields['custom'][$field_in_group['name']] = $custom_field_id;
					} else {
						$acf_fields['contact'][$field_in_group['name']] = $contact_field_name;
					}
				}

				/**
				 * Filter the mapped ACF Fields attached to a Post.
				 *
				 * Used internally by:
				 *
				 * - Relationship
				 * - Address
				 *
				 * @since 0.4.5
				 *
				 * @param array $acf_fields The existing ACF Fields array.
				 * @param array $field_in_group The ACF Field.
				 * @param int $post_id The numeric ID of the WordPress Post.
				 * @return array $acf_fields The modified ACF Fields array.
				 */
				$acf_fields = apply_filters( 'civicrm_acf_integration_fields_get_for_post', $acf_fields, $field_in_group, $post_id );

			}

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$post_id] ) ) {
			$pseudocache[$post_id] = $acf_fields;
		}

		// --<
		return $acf_fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Fields.
	 *
	 * @since 0.3
	 */
	public function fields_add() {

		/*
		// Add the "CiviCRM Contact Type" field.
		$contact_type = $this->civicrm_contact_type_get();
		acf_add_local_field( $contact_type );
		*/

		/*
		// Add the "CiviCRM Contact Field" field.
		$contact_field = $this->field_civicrm_contact_field_get();
		acf_add_local_field( $contact_field );
		*/

		/*
		// Add the "CiviCRM Custom Field" field.
		$custom_field = $this->plugin->civicrm->contact->acf_field_get();
		acf_add_local_field( $custom_field );
		*/

	}



	/**
	 * Return the "CiviCRM Contact Type" Field.
	 *
	 * @since 0.3
	 *
	 * @return array $field The Field data array.
	 */
	public function civicrm_contact_type_get() {

		// Get Contact Types.
		$contact_types = $this->plugin->civicrm->contact_type->types_get_nested();

		// Get mappings.
		$mappings = $this->plugin->mapping->mappings_for_contact_types_get();

		/*
		 * Do we want to allow top-level Contact Types to be synced?
		 *
		 * It would probably be sensible to restrict mappable Contact Types to
		 * subtypes, but there's no way to make select options "disabled" in ACF
		 * so it would be hard to show the Contact Type hierarchy.
		 */

		// TODO: Use OptGroups?

		// Build choices array for dropdown.
		$choices = [];
		foreach( $contact_types AS $contact_type ) {

			// Always add top-level Contact Types.
			$choices[$contact_type['id']] = $contact_type['name'];

			// Add subtypes if they are mapped.
			if ( ! empty( $contact_type['children'] ) ) {
				foreach( $contact_type['children'] AS $child ) {
					if ( isset( $mappings[$child['id']] ) ) {
						$choices[$child['id']] = '-- ' . $child['label'];
					}
				}
			}

		}

		// Get Contact Type key.
		$contact_type_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Define field.
		$field = [
			'key' => $contact_type_key,
			'label' => __( 'CiviCRM Contact Type', 'civicrm-acf-integration' ),
			'name' => $contact_type_key,
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Contact Type that Fields in this Field Group should refer to. (Optional)', 'civicrm-acf-integration' ),
			'default_value' => '',
			'placeholder' => '',
			'allow_null' => 1,
			'multiple' => 0,
			'ui' => 0,
			'required' => 0,
			'return_format' => 'value',
			'parent' => $this->acf->field_group->placeholder_group_get(),
			'choices' => $choices,
		];

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update the value of an ACF Field.
	 *
	 * @since 0.3
	 *
	 * @param str $selector The field name or key.
	 * @param mixed $value The value to save in the database.
	 * @param int $post_id The numeric value of the WordPress Post.
	 */
	public function value_update( $selector, $value, $post_id ) {

		// Pass through to ACF.
		$success = update_field( $selector, $value, $post_id );

	}



	/**
	 * Validate the content of a Field.
	 *
	 * Unlike in ACF, CiviCRM "Text", "Select" and "Radio" Fields can be of
	 * various kinds. We need to provide validation for the matching data types
	 * here before sync can take place.
	 *
	 * @since 0.3
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

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Bail if we don't have one.
		if ( $custom_field_id === false ) {
			return $valid;
		}

		// Get Custom Field data.
		$field_data = $this->plugin->civicrm->custom_field->get_by_id( $custom_field_id );

		// Bail if we don't get any.
		if ( $field_data === false ) {
			return $valid;
		}

		// Validate depending on the "data_type".
		switch ( $field_data['data_type'] ) {

			case 'String' :
				// Anything goes - except the length may be wrong.
				break;

			case 'Int' :
				// Must be an integer.
				if ( ! ctype_digit( $value ) ) {
					$valid = __( 'Must be an integer.', 'civicrm-acf-integration' );
				}
				break;

			case 'Float' :
				// Must be a number.
				if ( ! is_numeric( $value ) ) {
					$valid = __( 'Must be a number.', 'civicrm-acf-integration' );
				}
				break;

			case 'Money' :

				// Must be a number.
				if ( ! is_numeric( $value ) ) {
					$valid = __( 'Must be a valid money format.', 'civicrm-acf-integration' );
				}

				// Round the number.
				$rounded = round( $value, 2 );

				// Must be not have more than 2 decimal places.
				if ( $rounded != $value ) {
					$valid = __( 'Only two decimal places please.', 'civicrm-acf-integration' );
				}

				break;

		}

		// --<
		return $valid;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the value of an ACF Field formatted for CiviCRM.
	 *
	 * @since 0.2
	 *
	 * @param str $type The ACF Field type.
	 * @param mixed $value The ACF field value.
	 * @return mixed $value The field value formatted for CiviCRM.
	 */
	public function value_get_for_civicrm( $type, $value = 0 ) {

		// Set appropriate value per field type.
		switch( $type ) {

	 		// Parse the value of a "True/False" Field.
			case 'true_false' :
				$value = $this->true_false_value_get( $value );
				break;

			// Other Field Types may require parsing - add them here.

		}

		// --<
		return $value;

	}



	/**
	 * Get the value of a "True/False" Field formatted for CiviCRM.
	 *
	 * @since 0.2
	 *
	 * @param int|null $value The field value, or empty when "false".
	 * @return str $value The "Yes/No" value expressed as "1" or "0".
	 */
	public function true_false_value_get( $value = '0' ) {

		// Convert 1 to string.
		if ( $value == 1 ) {
			$value = '1';
		}

		// Convert empty value.
		if ( empty( $value ) OR $value === 0 ) {
			$value = '0';
		}

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "True/False" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function true_false_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->true_false_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Select" Fields.
	 *
	 * The following is weak in that the ACF Field has to be set up with these
	 * settings for the mapping to function.
	 *
	 * Ideally, once the mapping has been established, the ACF Field should
	 * be amended to match the kind of CiviCRM Custom Field. This could be
	 * done by allowing all Select-type Custom Fields to be chosen when adding
	 * an ACF Field, then post-processing when the type of Custom Field is
	 * known.
	 *
	 * For now the mapping is:
	 *
	 * Multi-Select: Requires "Select multiple values?" to be selected.
	 * Autocomplete-Select: Requires both "Stylised UI" and "Use AJAX to lazy load choices?"
	 * Select: Fallback when the above are not selected.
	 *
	 * @todo Better sync between these types.
	 *
	 * @since 0.3
	 *
	 * @param array $field The ACF Field data array.
	 */
	public function select_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->select_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Maybe modify the Setting of a "Select" Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function select_setting_modify( $field ) {

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Check for Contact Field if we don't have a Custom Field.
		if ( $custom_field_id === false ) {

			// Get the mapped Contact Field name if present.
			$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field );

			// Bail if we don't have one.
			if ( $contact_field_name === false ) {
				return $field;
			}

			// Get keyed array of settings.
			$choices = $this->plugin->civicrm->contact_field->select_choices_get( $contact_field_name );

			// "Prefix" and "Suffix" are optional.
			$field['allow_null'] = 1;

		} else {

			// Get keyed array of settings.
			$choices = $this->plugin->civicrm->custom_field->select_choices_get( $custom_field_id );

		}

		// Overwrite choices.
		if ( ! empty( $choices ) ) {
			$field['choices'] = $choices;
		}

		// --<
		return $field;

	}



	/**
	 * Maybe modify the Setting of a "Select" Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function select_field_update( $field ) {

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Alphanumeric Radio" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function radio_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->radio_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Maybe modify the Setting of an "Alphanumeric Radio" Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function radio_setting_modify( $field ) {

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Check for Contact Field if we don't have a Custom Field.
		if ( $custom_field_id === false ) {

			// Get the mapped Contact Field name if present.
			$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field );

			// Bail if we don't have one.
			if ( $contact_field_name === false ) {
				return $field;
			}

			// Get keyed array of Contact Field settings.
			$choices = $this->plugin->civicrm->contact_field->radio_choices_get( $contact_field_name );

			// "Prefix" and "Suffix" are optional.
			$field['allow_null'] = 1;

		} else {

			// Get keyed array of Custom Field settings.
			$choices = $this->plugin->civicrm->custom_field->radio_choices_get( $custom_field_id );

		}

		// Overwrite choices.
		if ( ! empty( $choices ) ) {
			$field['choices'] = $choices;
		}

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "CheckBox" Fields.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function checkbox_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->checkbox_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Maybe modify the Setting of a "CheckBox" Field.
	 *
	 * There are no Contact Fields of type "CheckBox", so we only need to check
	 * for Custom Fields.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function checkbox_setting_modify( $field ) {

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Bail if we don't have one.
		if ( $custom_field_id === false ) {
			return $field;
		}

		// Get keyed array of Custom Field settings.
		$choices = $this->plugin->civicrm->custom_field->checkbox_choices_get( $custom_field_id );

		// Overwrite choices.
		if ( ! empty( $choices ) ) {
			$field['choices'] = $choices;
		}

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Wysiwyg" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function wysiwyg_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->wysiwyg_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Text Area" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function textarea_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->textarea_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Date" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function date_picker_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->date_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Maybe modify the Setting of a "Date" Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function date_picker_setting_modify( $field ) {

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Check for Contact Field if we don't have a Custom Field.
		if ( $custom_field_id === false ) {

			// Get the mapped Contact Field name if present.
			$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field );

			// Bail if we don't have one.
			if ( $contact_field_name === false ) {
				return $field;
			}

			// Apply settings.
			$field = $this->plugin->civicrm->contact_field->date_settings_get( $field, $contact_field_name );

		} else {

			// Apply settings.
			$field = $this->plugin->civicrm->custom_field->date_settings_get( $field, $custom_field_id );

		}

		// --<
		return $field;

	}



	/**
	 * Maybe modify the value of a "Date" Field.
	 *
	 * @since 0.3
	 *
	 * @param str $value The field value.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function date_picker_value_modify( $value, $post_id, $field ) {

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Date Time" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function date_time_picker_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->date_time_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Maybe modify the Setting of a "Date Time" Field.
	 *
	 * There are no Contact Fields of type "Date Time", so we only need to check
	 * for Custom Fields.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function date_time_picker_setting_modify( $field ) {

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Bail if we don't have one.
		if ( $custom_field_id === false ) {
			return $field;
		}

		// Apply settings.
		$field = $this->plugin->civicrm->custom_field->date_time_settings_get( $field, $custom_field_id );

		// --<
		return $field;

	}



	/**
	 * Maybe modify the value of a "Date Time" Field.
	 *
	 * @since 0.3
	 *
	 * @param str $value The field value.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function date_time_picker_value_modify( $value, $post_id, $field ) {

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "URL" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function url_setting_add( $field ) {

		// Get the Website Fields for this CiviCRM Contact Type.
		$website_fields = $this->plugin->civicrm->website->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->url_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $website_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->website->acf_field_get( $filtered_fields, $website_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Email" Field Settings.
	 *
	 * @since 0.5
	 *
	 * @param array $field The field data array.
	 */
	public function email_setting_add( $field ) {

		// Get the Email Fields for this CiviCRM Contact Type.
		$email_fields = $this->plugin->civicrm->email->get_for_acf_field( $field );

		// Bail if there are no fields.
		if ( empty( $email_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->email->acf_field_get( $email_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Google Map" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function google_map_setting_add( $field ) {

		// Get the Address Fields for this CiviCRM Contact Type.
		$address_fields = $this->plugin->civicrm->address->get_for_acf_field( $field );

		// Bail if there are no fields.
		if ( empty( $address_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->address->acf_field_get( $address_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Add CSS when "Google Map" Field is loaded.
	 *
	 * @since 0.4.5
	 *
	 * @param array $field The field data array.
	 */
	public function google_map_styles_add( $field ) {

		// Get address key.
		$key = $this->plugin->civicrm->address->acf_field_key_get();

		// Bail if it's not a linked field.
		if ( empty( $field[$key] ) ) {
			return;
		}

		// Since Google Maps fields are CiviCRM -> ACF only, hide search.
		$style = '<style type="text/css">' .
			'#' . $field['id'] . '.acf-google-map .title { display: none; }' .
		'</style>';

		// Write to page.
		echo $style;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to "Text" Field Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The field data array.
	 */
	public function text_setting_add( $field ) {

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->text_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Maybe modify the Setting of a "Text" Field.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function text_setting_modify( $field ) {

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $field );

		// Check for Contact Field if we don't have a Custom Field.
		if ( $custom_field_id === false ) {

			// Get the mapped Contact Field name if present.
			$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field );

			// Bail if we don't have one.
			if ( $contact_field_name === false ) {
				return $field;
			}

			// Apply settings.
			$field = $this->plugin->civicrm->contact_field->text_settings_get( $field, $contact_field_name );

		} else {

			// Apply settings.
			$field = $this->plugin->civicrm->custom_field->text_settings_get( $field, $custom_field_id );

		}

		// --<
		return $field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Prepare a Field based on our Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function prepare( $field ) {

		// Bail early if no 'admin_only' setting.
		if ( empty( $field['admin_only'] ) ) {
			return $field;
		}

		// Return false if is not admin (removes field)
		if ( ! current_user_can( 'administrator' ) ) {
			return false;
		}

		// --<
		return $field;

	}



} // Class ends.




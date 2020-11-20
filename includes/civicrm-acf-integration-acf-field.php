<?php
/**
 * ACF Field Class.
 *
 * Handles ACF Field functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration ACF Field Class.
 *
 * A class that encapsulates ACF Field functionality.
 *
 * @since 0.3
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

		// Add setting to various Fields.
		add_action( 'acf/render_field_settings/type=true_false', [ $this, 'true_false_setting_add' ] );
		add_action( 'acf/render_field_settings/type=wysiwyg', [ $this, 'wysiwyg_setting_add' ] );
		add_action( 'acf/render_field_settings/type=textarea', [ $this, 'textarea_setting_add' ] );
		add_action( 'acf/render_field_settings/type=url', [ $this, 'url_setting_add' ] );
		add_action( 'acf/render_field_settings/type=email', [ $this, 'email_setting_add' ] );

		// Customise "Google Map" Fields.
		add_action( 'acf/render_field_settings/type=google_map', [ $this, 'google_map_setting_add' ] );
		add_action( 'acf/render_field/type=google_map', [ $this, 'google_map_styles_add' ] );
		add_action( 'acf/load_value/type=google_map', [ $this, 'google_map_value_modify' ], 10, 3 );
		add_action( 'acf/update_value/type=google_map', [ $this, 'google_map_value_modify' ], 10, 3 );

		// Customise "Select" Fields.
		add_action( 'acf/render_field_settings/type=select', [ $this, 'select_setting_add' ] );
		add_filter( 'acf/validate_value/type=select', [ $this, 'value_validate' ], 10, 4 );

		// Customise "Radio" Fields.
		add_action( 'acf/render_field_settings/type=radio', [ $this, 'radio_setting_add' ] );
		add_filter( 'acf/validate_value/type=radio', [ $this, 'value_validate' ], 10, 4 );

		// Customise "CheckBox" Fields.
		add_action( 'acf/render_field_settings/type=checkbox', [ $this, 'checkbox_setting_add' ] );

		// Customise "Date" Fields.
		add_action( 'acf/render_field_settings/type=date_picker', [ $this, 'date_picker_setting_add' ] );
		add_filter( 'acf/load_value/type=date_picker', [ $this, 'date_picker_value_modify' ], 10, 3 );

		// Customise "Date Time" Fields.
		add_action( 'acf/render_field_settings/type=date_time_picker', [ $this, 'date_time_picker_setting_add' ] );
		add_filter( 'acf/load_value/type=date_time_picker', [ $this, 'date_time_picker_value_modify' ], 10, 3 );

		// Customise "Text" Fields.
		add_action( 'acf/render_field_settings/type=text', [ $this, 'text_setting_add' ] );
		add_filter( 'acf/validate_value/type=text', [ $this, 'value_validate' ], 10, 4 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the type of WordPress Entity that a Field refers to.
	 *
	 * @see https://www.advancedcustomfields.com/resources/get_fields/
	 *
	 * @since 0.8
	 *
	 * @param int|str $post_id The ACF "Post ID" parameter.
	 * @return str The type of WordPress Entity that a Field refers to.
	 */
	public function entity_type_get( $post_id ) {

		// If numeric, it's a Post.
		if ( is_numeric( $post_id ) ) {
			return 'post';
		}

		// Does it refer to a WordPress User?
		if ( false !== strpos( $post_id, 'user_' ) ) {
			return 'user';
		}

		// Does it refer to a WordPress Taxonomy?
		if ( false !== strpos( $post_id, 'category_' ) ) {
			return 'category';
		}

		// Does it refer to a WordPress Term?
		if ( false !== strpos( $post_id, 'term_' ) ) {
			return 'term';
		}

		// Does it refer to a WordPress Comment?
		if ( false !== strpos( $post_id, 'comment_' ) ) {
			return 'comment';
		}

		// Does it refer to an ACF Options Page?
		if ( $post_id === 'options' ) {
			return 'options';
		}

		// Does it refer to an ACF Option?
		if ( $post_id === 'option' ) {
			return 'option';
		}

		// Fallback.
		return 'unknown';

	}



	/**
	 * Query for the Contact ID that this ACF "Post ID" is mapped to.
	 *
	 * We have to query like this because the ACF "Post ID" is actually only a
	 * Post ID if it's an integer. Other string values indicate other WordPress
	 * Entities, some of which may be handled by other plugins.
	 *
	 * @see https://www.advancedcustomfields.com/resources/get_fields/
	 *
	 * @since 0.8
	 *
	 * @param bool $post_id The ACF "Post ID".
	 * @return int|bool $contact_id The mapped Contact ID, or false if not mapped.
	 */
	public function query_contact_id( $post_id ) {

		// Init return.
		$contact_id = false;

		// Get the WordPress Entity.
		$entity = $this->entity_type_get( $post_id );

		/**
		 * Query for the Contact ID that this ACF "Post ID" is mapped to.
		 *
		 * This filter sends out a request for other classes to respond with a
		 * Contact ID if they detect that this ACF "Post ID" maps to one.
		 *
		 * Internally, this is used by:
		 *
		 * @see CiviCRM_ACF_Integration_Custom_CiviCRM_Contact_ID_Field::load_value()
		 *
		 * @since 0.8
		 *
		 * @param bool $contact_id False, since we're asking for a Contact ID.
		 * @param int|str $post_id The ACF "Post ID".
		 * @param str $entity The kind of WordPress Entity.
		 * @return int|bool $contact_id The mapped Contact ID, or false if not mapped.
		 */
		$contact_id = apply_filters( 'civicrm_acf_integration_query_contact_id', $contact_id, $post_id, $entity );

		// --<
		return $contact_id;

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
	 * @param int|str $post_id The ACF "Post ID".
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

		// Get Entity reference.
		$entity = $this->entity_type_get( $post_id );

		// TODO: Make this a filter...

		// Easy if it's a Post.
		if ( $entity === 'post' ) {
			$params = [
				'post_id' => $post_id,
			];
		}

		// If it's a User, we support the Edit Form.
		if ( $entity === 'user' ) {
			//$tmp = explode( '_', $post_id );
			$params = [
				//'user_id' => $tmp[1],
				'user_form' => 'edit',
			];
		}

		// Get all Field Groups for this ACF "Post ID".
		$acf_field_groups = acf_get_field_groups( $params );

		// Build our equivalent array to that returned by `get_fields()`.
		foreach( $acf_field_groups AS $acf_field_group ) {

			// Get all the fields in this Field Group.
			$fields_in_group = acf_get_fields( $acf_field_group['ID'] );

			// Add their Field "name" to the return.
			foreach( $fields_in_group AS $field_in_group ) {

				// Get the CiviCRM Custom Field and add if it has a reference to a CiviCRM Field.
				$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field_in_group );
				if ( ! empty( $custom_field_id ) ) {
					$acf_fields['custom'][$field_in_group['name']] = $custom_field_id;
				}

				// Get the CiviCRM Contact Field and add if it has a reference to a CiviCRM Field.
				$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field_in_group );
				if ( ! empty( $contact_field_name ) ) {
					$acf_fields['contact'][$field_in_group['name']] = $contact_field_name;
				}

				// Get the CiviCRM Activity Field and add if it has a reference to a CiviCRM Field.
				$activity_field_name = $this->plugin->civicrm->activity->activity_field_name_get( $field_in_group );
				if ( ! empty( $activity_field_name ) ) {
					$acf_fields['activity'][$field_in_group['name']] = $activity_field_name;
				}

				/**
				 * Filter the mapped ACF Fields attached to a Post.
				 *
				 * Used internally by:
				 *
				 * - Relationship
				 * - Address
				 * - Email
				 * - Website
				 * - Phone
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
	 * Update the value of an ACF Field.
	 *
	 * @since 0.3
	 *
	 * @param str $selector The field name or key.
	 * @param mixed $value The value to save in the database.
	 * @param int|str $post_id The ACF "Post ID".
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
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

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

		// Get the Activity Fields for this ACF Field.
		$activity_fields = $this->plugin->civicrm->activity_field->get_for_acf_field( $field );

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Bail if there are conflicting fields.
		if ( ! empty( $contact_fields ) AND ! empty( $activity_fields ) ) {
			return;
		}

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->select_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) AND empty( $activity_fields ) ) {
			return;
		}

		// Get Setting field based on Entity.
		if ( ! empty( $activity_fields ) ) {
			$setting = $this->plugin->civicrm->activity->acf_field_get( $filtered_fields, $activity_fields );
		} else {
			$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );
		}

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

		// Skip if the CiviCRM Field key isn't there or isn't populated.
		$key = $this->plugin->civicrm->acf_field_key_get();
		if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
			return;
		}

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

		// Check for a Custom Field.
		if ( $custom_field_id !== false ) {

			// Get keyed array of settings.
			$choices = $this->plugin->civicrm->custom_field->select_choices_get( $custom_field_id );

		} else {

			// Get the mapped Contact Field name if present.
			$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $field );

			// Bail if we don't have one.
			if ( $contact_field_name !== false ) {

				// Get keyed array of settings.
				$choices = $this->plugin->civicrm->contact_field->select_choices_get( $contact_field_name );

				// "Prefix" and "Suffix" are optional.
				$field['allow_null'] = 1;

			} else {

				// Get the mapped Activity Field name if present.
				$activity_field_name = $this->plugin->civicrm->activity->activity_field_name_get( $field );

				// Bail if we don't have one.
				if ( $activity_field_name !== false ) {

					// Get keyed array of settings.
					$choices = $this->plugin->civicrm->activity_field->select_choices_get( $activity_field_name );

					// These are all optional.
					$field['allow_null'] = 1;

				}

			}

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

		// Skip if the CiviCRM Field key isn't there or isn't populated.
		$key = $this->plugin->civicrm->acf_field_key_get();
		if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
			return;
		}

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

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

		// Skip if the CiviCRM Field key isn't there or isn't populated.
		$key = $this->plugin->civicrm->acf_field_key_get();
		if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
			return;
		}

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

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

		// Skip if the CiviCRM Field key isn't there or isn't populated.
		$key = $this->plugin->civicrm->acf_field_key_get();
		if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
			return;
		}

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

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
	 * @param int|str $post_id The ACF "Post ID".
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

		// Get the Activity Fields for this ACF Field.
		$activity_fields = $this->plugin->civicrm->activity_field->get_for_acf_field( $field );

		// Get the Contact Fields for this ACF Field.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Bail if there are conflicting fields.
		if ( ! empty( $contact_fields ) AND ! empty( $activity_fields ) ) {
			return;
		}

		// Get the Custom Fields for this ACF Field.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this ACF Field.
		$filtered_fields = $this->plugin->civicrm->custom_field->date_time_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) AND empty( $activity_fields ) ) {
			return;
		}

		// Get Setting field based on Entity.
		if ( ! empty( $activity_fields ) ) {
			$setting = $this->plugin->civicrm->activity->acf_field_get( $filtered_fields, $activity_fields );
		} else {
			$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );
		}

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

		// Skip if the CiviCRM Field key isn't there or isn't populated.
		$key = $this->plugin->civicrm->acf_field_key_get();
		if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
			return;
		}

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

		// Apply settings if we have one.
		if ( $custom_field_id !== false ) {
			$field = $this->plugin->civicrm->custom_field->date_time_settings_get( $field, $custom_field_id );
		}

		// Check Activity settings if we have one.
		if ( $custom_field_id === false ) {

			// Get the mapped Activity Field name if present.
			$activity_field_name = $this->plugin->civicrm->activity->activity_field_name_get( $field );

			// Bail if we don't have one.
			if ( $activity_field_name !== false ) {
				$field = $this->plugin->civicrm->activity_field->date_time_settings_get( $field, $activity_field_name );
			}

		}

		// --<
		return $field;

	}



	/**
	 * Maybe modify the value of a "Date Time" Field.
	 *
	 * @since 0.3
	 *
	 * @param str $value The field value.
	 * @param int|str $post_id The ACF "Post ID".
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

		/*
		// Get "Disable Edit" Setting field.
		$edit_setting = $this->plugin->civicrm->address->acf_field_edit_get( $address_fields );

		// Now add it.
		acf_render_field_setting( $field, $edit_setting );
		*/

	}



	/**
	 * Maybe modify the Setting of a "Google Map" Field.
	 *
	 * Only the Primary Address can be editable in the ACF Field because it is
	 * the only CiviCRM Address that is guaranteed to be unique. There can be
	 * multiple Addresses with the same Location Type but only one that is the
	 * Primary Address.
	 *
	 * @since 0.8
	 *
	 * @param array $field The existing field data array.
	 * @return array $field The modified field data array.
	 */
	public function google_map_setting_modify( $field ) {

		// Bail if it's not a linked field.
		$key = $this->plugin->civicrm->address->acf_field_key_get();
		if ( empty( $field[$key] ) ) {
			return;
		}

		// Get the "Make Read Only" key.
		$edit_key = $this->plugin->civicrm->address->acf_field_key_edit_get();

		// Always set to default if not set.
		if ( ! isset( $field[$edit_key] ) ) {
			$field[$edit_key] = 1;
		}

		// Always set to true if not a "Primary" Address.
		if ( $field[$key] != 'primary' ) {
			$field[$edit_key] = 1;
		}

		// --<
		return $field;

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

		// Get the "Make Read Only" key.
		$edit_key = $this->plugin->civicrm->address->acf_field_key_edit_get();

		// Only skip if it's explicitly *not* set to "Read Only".
		if ( isset( $field[$edit_key] ) AND $field[$edit_key] !== 1 ) {
			return;
		}

		// Hide search bar when "Read Only". Yeah I know it's a hack.
		$style = '<style type="text/css">' .
			'#' . $field['id'] . '.acf-google-map .title { display: none; }' .
		'</style>';

		// Write to page.
		echo $style;

	}



	/**
	 * Maybe modify the value of a "Google Map" Field.
	 *
	 * This merely ensures that we have an array to work with.
	 *
	 * @since 0.8
	 *
	 * @param mixed $value The existing value.
	 * @param int $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	 */
	public function google_map_value_modify( $value, $post_id, $field ) {

		// Make sure we have an array.
		if ( empty( $value ) AND ! is_array( $value ) ) {
			$value = [];
		}

		// --<
		return $value;

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

		// Get the Activity Fields for this ACF Field.
		$activity_fields = $this->plugin->civicrm->activity_field->get_for_acf_field( $field );

		// Get the Contact Fields for this CiviCRM Contact Type.
		$contact_fields = $this->plugin->civicrm->contact_field->get_for_acf_field( $field );

		// Bail if there are conflicting fields.
		if ( ! empty( $contact_fields ) AND ! empty( $activity_fields ) ) {
			return;
		}

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_acf_field( $field );

		// Filter the Custom Fields for this CiviCRM Contact Type.
		$filtered_fields = $this->plugin->civicrm->custom_field->text_settings_filter( $field, $custom_fields );

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) AND empty( $contact_fields ) AND empty( $activity_fields ) ) {
			return;
		}

		// Get Setting field based on Entity.
		if ( ! empty( $activity_fields ) ) {
			$setting = $this->plugin->civicrm->activity->acf_field_get( $filtered_fields, $activity_fields );
		} else {
			$setting = $this->plugin->civicrm->contact->acf_field_get( $filtered_fields, $contact_fields );
		}

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

		// Skip if the CiviCRM Field key isn't there or isn't populated.
		$key = $this->plugin->civicrm->acf_field_key_get();
		if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
			return;
		}

		// Get the mapped Custom Field ID if present.
		$custom_field_id = $this->plugin->civicrm->custom_field->custom_field_id_get( $field );

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



} // Class ends.




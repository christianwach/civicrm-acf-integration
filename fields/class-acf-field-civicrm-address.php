<?php
/**
 * ACF "CiviCRM Address Field" Class.
 *
 * Provides a "CiviCRM Address Field" Custom ACF Field in ACF 5+.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration Custom ACF Field Type - CiviCRM Address Field.
 *
 * A class that encapsulates a "CiviCRM Address Field" Custom ACF Field in ACF 5+.
 *
 * @since 0.8.2
 */
class CiviCRM_ACF_Integration_Custom_CiviCRM_Address_Field extends acf_field {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Advanced Custom Fields object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $cpt The Advanced Custom Fields object.
	 */
	public $acf;

	/**
	 * CiviCRM Utilities object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $civicrm The CiviCRM Utilities object.
	 */
	public $civicrm;

	/**
	 * Field Type name.
	 *
	 * Single word, no spaces. Underscores allowed.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var str $name The Field Type name.
	 */
	public $name = 'civicrm_address';

	/**
	 * Field Type label.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Multiple words, can include spaces, visible when selecting a field type.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var str $label The Field Type label.
	 */
	public $label = '';

	/**
	 * Field Type category.
	 *
	 * Choose between the following categories:
	 *
	 * basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
	 *
	 * @since 0.8.2
	 * @access public
	 * @var str $label The Field Type category.
	 */
	public $category = 'CiviCRM';

	/**
	 * Field Type defaults.
	 *
	 * Array of default settings which are merged into the field object.
	 * These are used later in settings.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array $defaults The Field Type defaults.
	 */
	public $defaults = [];

	/**
	 * Field Type settings.
	 *
	 * Contains "version", "url" and "path" as references for use with assets.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array $settings The Field Type settings.
	 */
	public $settings = [
		'version' => CIVICRM_ACF_INTEGRATION_VERSION,
		'url' => CIVICRM_ACF_INTEGRATION_URL,
		'path' => CIVICRM_ACF_INTEGRATION_PATH,
	];

	/**
	 * Field Type translations.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Array of strings that are used in JavaScript. This allows JS strings
	 * to be translated in PHP and loaded via:
	 *
	 * var message = acf._e( 'civicrm_contact', 'error' );
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array $l10n The Field Type translations.
	 */
	public $l10n = [];



	/**
	 * Sets up the Field Type.
	 *
	 * @since 0.8.2
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to parent.
		$this->acf = $parent;

		// Store reference to CiviCRM Utilities.
		$this->civicrm = $this->plugin->civicrm;

		$this->label = __( 'CiviCRM Address', 'civicrm-acf-integration' );

		// Define translations.
		$this->l10n = [];

		// Call parent.
		parent::__construct();

	}



	/**
	 * Create extra Settings for this Field Type.
	 *
	 * These extra Settings will be visible when editing a Field.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field being edited.
	 */
	public function render_field_settings( $field ) {

		// Define setting field.
		$setting = [
			'label' => __( 'CiviCRM Address ID', 'civicrm-acf-integration' ),
			'name' => 'show_address_id',
			'type' => 'true_false',
			'ui' => 1,
			'ui_on_text' => __( 'Show', 'civicrm-acf-integration' ),
			'ui_off_text' => __( 'Hide', 'civicrm-acf-integration' ),
			'default_value' => 0,
			'required' => 0,
		];

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Creates the HTML interface for this Field Type.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field being rendered.
	 */
	public function render_field( $field ) {

		// Change Field into a repeater field.
		$field['type'] = 'repeater';

		// Render.
		acf_render_field( $field );

	}



	/**
	 * Prepare this Field Type for display.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The Field being rendered.
	 */
	public function prepare_field( $field ) {

		// Bail when Address ID should be shown.
		if ( ! empty( $field['show_address_id'] ) ) {
			return $field;
		}

		// Add hidden class to element.
		$field['wrapper']['class'] .= ' address_id_hidden';

		// --<
		return $field;

	}



	/**
	 * This action is called in the "admin_enqueue_scripts" action on the edit
	 * screen where this Field is edited.
	 *
	 * Use this action to add CSS and JavaScript to assist your
	 * render_field_settings() action.
	 *
	 * @since 0.8.2
	public function field_group_admin_enqueue_scripts() {

	}
	 */



	/**
	 * This action is called in the "admin_head" action on the edit screen where
	 * this Field is edited.
	 *
	 * Use this action to add CSS and JavaScript to assist your
	 * render_field_settings() action.
	 *
	 * @since 0.8.2
	public function field_group_admin_head() {

	}
	 */



	/**
	 * This method is called in the "admin_enqueue_scripts" action on the edit
	 * screen where this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.8.2
	 */
	public function input_admin_enqueue_scripts() {

		// Enqueue our JavaScript.
		wp_enqueue_script(
			'acf-input-' . $this->name,
			plugins_url( 'assets/js/civicrm-address-field.js', CIVICRM_ACF_INTEGRATION_FILE ),
			[ 'acf-pro-input' ],
			CIVICRM_ACF_INTEGRATION_VERSION // Version.
		);

	}



	/**
	 * This method is called in the admin_head action on the edit screen where
	 * this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.8.2
	 */
	public function input_admin_head() {

		echo '
		<style type="text/css">
			/* Hide Address ID block */
			.address_id_hidden div[data-key="field_address_id"] {
				display: none;
			}
		</style>
		';

	}



	/**
	 * This filter is applied to the $value after it is loaded from the database.
	 *
	 * @since 0.8.2
	 *
	 * @param mixed $value The value found in the database.
	 * @param int $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	 */
	public function load_value( $value, $post_id, $field ) {

		// Make sure we have an array.
		if ( empty( $value ) AND ! is_array( $value ) ) {
			$value = [];
		}

		// Process the data if it's an array.
		if ( is_array( $value ) ) {

			// Strip keys and re-index.
			$value = array_values( $value );

		}

		// --<
		return $value;

	}



	/**
	 * This filter is applied to the $value before it is saved in the database.
	 *
	 * @since 0.8.2
	 *
	 * @param mixed $value The value found in the database.
	 * @param int $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	 */
	public function update_value( $value, $post_id, $field ) {

		// Make sure we have an array.
		if ( empty( $value ) AND ! is_array( $value ) ) {
			$value = [];
		}

		// --<
		return $value;

	}



	/**
	 * This filter is applied to the value after it is loaded from the database
	 * and before it is returned to the template.
	 *
	 * @since 0.8.2
	 *
	 * @param mixed $value The value which was loaded from the database.
	 * @param mixed $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	public function format_value( $value, $post_id, $field ) {

		// Bail early if no value.
		if ( empty( $value ) ) {
			return $value;
		}

		// Apply setting.
		if ( $field['font_size'] > 12 ) {

			// format the value
			// $value = 'something';

		}

		// --<
		return $value;

	}
	 */



	/**
	 * This filter is used to perform validation on the value prior to saving.
	 *
	 * All values are validated regardless of the field's required setting.
	 * This allows you to validate and return messages to the user if the value
	 * is not correct.
	 *
	 * @since 0.8.2
	 *
	 * @param bool $valid The validation status based on the value and the field's required setting.
	 * @param mixed $value The $_POST value.
	 * @param array $field The field array holding all the field options.
	 * @param str $input The corresponding input name for $_POST value.
	 * @return bool|str $valid False if not valid, or string for error message.
	 */
	public function validate_value( $valid, $value, $field, $input ) {

		// Bail if it's not required and is empty.
		if ( $field['required'] == '0' AND empty( $value ) ) {
			return $valid;
		}

		// Grab just the Primary values.
		$primary_values = wp_list_pluck( $value, 'field_address_primary' );

		// Sanitise array contents.
		array_walk( $primary_values, function( &$item ) {
			$item = (int) trim( $item );
		} );

		// Check that we have a Primary Address.
		if ( ! in_array( 1, $primary_values ) ) {
			$valid = __( 'Please select a Primary Address', 'civicrm-acf-integration' );
			return $valid;
		}

		// Grab just the Location Type IDs.
		$location_type_ids = wp_list_pluck( $value, 'field_address_location_type' );

		// Sanitise array contents.
		array_walk( $location_type_ids, function( &$item ) {
			$item = (int) trim( $item );
		} );

		// Check that we have unique Location Types.
		if ( ! empty( array_diff_key( $location_type_ids, array_unique( $location_type_ids ) ) ) ) {
			$valid = __( 'You can only have one Address per Location Type', 'civicrm-acf-integration' );
			return $valid;
		}

		// --<
		return $valid;

	}



	/**
	 * This action is fired after a value has been deleted from the database.
	 *
	 * Please note that saving a blank value is treated as an update, not a delete.
	 *
	 * @since 0.8.2
	 *
	 * @param int $post_id The Post ID from which the value was deleted.
	 * @param str $key The meta key which the value was deleted.
	public function delete_value( $post_id, $key ) {

	}
	 */



	/**
	 * This filter is applied to the Field after it is loaded from the database.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The field array holding all the field options.
	 * @return array $field The modified field data.
	 */
	public function load_field( $field ) {

		// Cast min/max as integer.
		$field['min'] = (int) $field['min'];
		$field['max'] = (int) $field['max'];

		// Init Subfields.
		$sub_fields = [];

		// Maybe append to Field.
		if ( ! empty( $field['sub_fields'] ) ) {

			// Validate field first.
			foreach( $field['sub_fields'] AS $sub_field ) {
				$sub_fields[] = acf_validate_field( $sub_field );
			}

		}

		// Overwrite subfields.
		$field['sub_fields'] = $sub_fields;

		// --<
		return $field;

	}



	/**
	 * This filter is applied to the Field before it is saved to the database.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The field array holding all the field options.
	 * @return array $field The modified field data.
	 */
	public function update_field( $field ) {

		// Modify the Field with our settings.
		$field = $this->modify_field( $field );

		// --<
		return $field;

	}



	/**
	 * This action is fired after a Field is deleted from the database.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The field array holding all the field options.
	public function delete_field( $field ) {

	}
	 */



	/**
	 * Modify the Field with defaults and Subfield definitions.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The field array holding all the field options.
	 * @return array $subfields The subfield array.
	 */
	public function modify_field( $field ) {

		/*
		 * Set the max value to match the max in CiviCRM.
		 *
		 * @see civicrm/templates/CRM/Contact/Form/Inline/Address.tpl:22
		 */
		$field['max'] = 5;
		$field['min'] = 0;

		// Set sensible defaults.
		$field['layout'] = 'block';
		$field['button_label'] = __( 'Add Address', 'civicrm-acf-integration' );
		$field['collapsed'] = 'field_address_location_type';
		$field['prefix'] = '';

		// Set wrapper class.
		$field['wrapper']['class'] = 'civicrm_address';

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $field;
		}

		// Get CiviCRM config.
		$config = CRM_Core_Config::singleton();

		// Get Locations.
		$location_types = $this->civicrm->address->location_types_get();

		// Build Location Types choices array for dropdown.
		$locations = [];
		foreach( $location_types AS $location_type ) {
			$locations[$location_type['id']] = esc_attr( $location_type['display_name'] );
		}

		// Define Location field.
		$location = [
			'key' => 'field_address_location_type',
			'label' => __( 'Location Type', 'civicrm-acf-integration' ),
			'name' => 'location_type',
			'type' => 'select',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 1,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '60',
				'class' => 'civicrm_address_location_type',
				'id' => '',
			],
			'choices' => $locations,
			'default_value' => false,
			'allow_null' => 0,
			'multiple' => 0,
			'ui' => 0,
			'return_format' => 'value',
			'ajax' => 0,
			'placeholder' => '',
			'prefix' => '',
		];

		// Define Is Primary field.
		$primary = [
			'key' => 'field_address_primary',
			'label' => __( 'Is Primary', 'civicrm-acf-integration' ),
			'name' => 'is_primary',
			'type' => 'radio',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '20',
				'class' => 'civicrm_address_primary',
				'id' => '',
			],
			'choices' => [
				1 => __( 'Primary', 'civicrm-acf-integration' ),
			],
			'allow_null' => 1,
			'other_choice' => 0,
			'default_value' => '',
			'layout' => 'vertical',
			'return_format' => 'value',
			'save_other_choice' => 0,
			'prefix' => '',
		];

		// Define Is Billing field.
		$billing = [
			'key' => 'field_address_billing',
			'label' => __( 'Is Billing', 'civicrm-acf-integration' ),
			'name' => 'is_billing',
			'type' => 'checkbox',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '20',
				'class' => 'civicrm_address_billing',
				'id' => '',
			],
			'choices' => [
				1 => __( 'Billing', 'civicrm-acf-integration' ),
			],
			'allow_custom' => 0,
			'default_value' => [],
			'layout' => 'vertical',
			'toggle' => 0,
			'return_format' => 'value',
			'save_custom' => 0,
		];

		// Define Street Address field.
		$street_address = [
			'key' => 'field_address_street_address',
			'label' => __( 'Street Address', 'civicrm-acf-integration' ),
			'name' => 'street_address',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define Supplemental Address 1 field.
		$supplemental_address_1 = [
			'key' => 'field_address_supplemental_address_1',
			'label' => __( 'Supplemental Address 1', 'civicrm-acf-integration' ),
			'name' => 'supplemental_address_1',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define Supplemental Address 2 field.
		$supplemental_address_2 = [
			'key' => 'field_address_supplemental_address_2',
			'label' => __( 'Supplemental Address 2', 'civicrm-acf-integration' ),
			'name' => 'supplemental_address_2',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define Supplemental Address 3 field.
		$supplemental_address_3 = [
			'key' => 'field_address_supplemental_address_3',
			'label' => __( 'Supplemental Address 3', 'civicrm-acf-integration' ),
			'name' => 'supplemental_address_3',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define City field.
		$city = [
			'key' => 'field_address_city',
			'label' => __( 'City', 'civicrm-acf-integration' ),
			'name' => 'city',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '60',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define Post Code field.
		$post_code = [
			'key' => 'field_address_postal_code',
			'label' => __( 'Post Code', 'civicrm-acf-integration' ),
			'name' => 'postal_code',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '40',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// $params['country'] = CRM_Core_PseudoConstant::country($params['country_id']);

		// Define Country field.
		$country_id = [
			'key' => 'field_address_country_id',
			'label' => __( 'Country', 'civicrm-acf-integration' ),
			'name' => 'country_id',
			'type' => 'select',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '50',
				'class' => '',
				'id' => '',
			],
			'choices' => CRM_Core_PseudoConstant::country(),
			'default_value' => $config->defaultContactCountry,
			'allow_null' => 1,
			'multiple' => 0,
			'ui' => 1,
			'ajax' => 0,
			'return_format' => 'value',
			'placeholder' => '',
		];

		// Define State/Province field.
		$state_province_id = [
			'key' => 'field_address_state_province_id',
			'label' => __( 'State/Province', 'civicrm-acf-integration' ),
			'name' => 'state_province_id',
			'type' => 'select',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '50',
				'class' => '',
				'id' => '',
			],
			'choices' => CRM_Core_PseudoConstant::stateProvince(),
			'default_value' => false,
			'allow_null' => 1,
			'multiple' => 0,
			'ui' => 1,
			'ajax' => 0,
			'return_format' => 'value',
			'placeholder' => '',
		];

		// Define Latitude field.
		$geo_code_1 = [
			'key' => 'field_address_geo_code_1',
			'label' => __( 'Latitude', 'civicrm-acf-integration' ),
			'name' => 'geo_code_1',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '35',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define Longitude field.
		$geo_code_2 = [
			'key' => 'field_address_geo_code_2',
			'label' => __( 'Longitude', 'civicrm-acf-integration' ),
			'name' => 'geo_code_2',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '35',
				'class' => '',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
		];

		// Define Override automatic geocoding field.
		$manual_geo_code = [
			'key' => 'field_address_manual_geo_code',
			'label' => __( 'Override automatic geocoding', 'civicrm-acf-integration' ),
			'name' => 'manual_geo_code',
			'type' => 'checkbox',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '30',
				'class' => '',
				'id' => '',
			],
			'choices' => [
				1 => __( 'Override', 'civicrm-acf-integration' ),
			],
			'allow_custom' => 0,
			'default_value' => [],
			'layout' => 'vertical',
			'toggle' => 0,
			'return_format' => 'value',
			'save_custom' => 0,
		];

		// Define hidden CiviCRM Address ID field.
		$address_id = [
			'readonly' => true,
			'key' => 'field_address_id',
			'label' => __( 'CiviCRM ID', 'civicrm-acf-integration' ),
			'name' => 'civicrm_address_id',
			'type' => 'number',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => 'civicrm_address_id',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'min' => '',
			'max' => '',
			'step' => '',
			'prefix' => '',
		];

		// Add Subfields.
		$field['sub_fields'] = [
			$location, $primary, $billing,
			$street_address,
			$supplemental_address_1,
			$supplemental_address_2,
			$supplemental_address_3,
			$city, $post_code,
			$country_id, $state_province_id,
			$geo_code_1, $geo_code_2, $manual_geo_code,
			$address_id,
		];

		// --<
		return $field;

	}



} // Class ends.




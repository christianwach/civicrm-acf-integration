<?php
/**
 * ACF "CiviCRM Multiple Record Set Field" Class.
 *
 * Provides a "CiviCRM Multiple Record Set Field" Custom ACF Field in ACF 5+.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration Custom ACF Field Type - CiviCRM Multiple Record Set Field.
 *
 * A class that encapsulates a "CiviCRM Multiple Record Set Field" Custom ACF Field in ACF 5+.
 *
 * @since 0.8
 */
class CiviCRM_ACF_Integration_Custom_CiviCRM_Multiple_Record_Set extends acf_field {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.8
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Advanced Custom Fields object.
	 *
	 * @since 0.8
	 * @access public
	 * @var object $cpt The Advanced Custom Fields object.
	 */
	public $acf;

	/**
	 * CiviCRM Utilities object.
	 *
	 * @since 0.8
	 * @access public
	 * @var object $civicrm The CiviCRM Utilities object.
	 */
	public $civicrm;

	/**
	 * Field Type name.
	 *
	 * Single word, no spaces. Underscores allowed.
	 *
	 * @since 0.8
	 * @access public
	 * @var str $name The Field Type name.
	 */
	public $name = 'civicrm_multiset';

	/**
	 * Field Type label.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Multiple words, can include spaces, visible when selecting a field type.
	 *
	 * @since 0.8
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
	 * @since 0.8
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
	 * @since 0.8
	 * @access public
	 * @var array $defaults The Field Type defaults.
	 */
	public $defaults = [];

	/**
	 * Field Type settings.
	 *
	 * Contains "version", "url" and "path" as references for use with assets.
	 *
	 * @since 0.8
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
	 * @since 0.8
	 * @access public
	 * @var array $l10n The Field Type translations.
	 */
	public $l10n = [];



	/**
	 * Sets up the Field Type.
	 *
	 * @since 0.8
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

		$this->label = __( 'CiviCRM Multiple Record Set', 'civicrm-acf-integration' );

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
	 * @since 0.8
	 *
	 * @param array $field The Field being edited.
	public function render_field_settings( $field ) {

	}
	 */



	/**
	 * Creates the HTML interface for this Field Type.
	 *
	 * @since 0.8
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
	 * This action is called in the "admin_enqueue_scripts" action on the edit
	 * screen where this Field is edited.
	 *
	 * Use this action to add CSS and JavaScript to assist your
	 * render_field_settings() action.
	 *
	 * @since 0.8
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
	 * @since 0.8
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
	 * @since 0.8
	 */
	public function input_admin_enqueue_scripts() {

		// Enqueue our JavaScript.
		wp_enqueue_script(
			'acf-input-' . $this->name,
			plugins_url( 'assets/js/civicrm-multiset-field.js', CIVICRM_ACF_INTEGRATION_FILE ),
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
	 * @since 0.8
	 */
	public function input_admin_head() {

		echo '
		<style type="text/css">
			/* Hide Repeater column */
			th[data-key="field_multiset_id"],
			td.civicrm_multiset_id
			{
				display: none;
			}
		</style>
		';

	}



	/**
	 * This filter is applied to the $value after it is loaded from the database.
	 *
	 * @since 0.8
	 *
	 * @param mixed $value The value found in the database.
	 * @param int $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	 */
	public function load_value( $value, $post_id, $field ) {

		// Strip keys and re-index.
		if ( is_array( $value ) ) {
			$value = array_values( $value );
		}

		// --<
		return $value;

	}



	/**
	 * This filter is applied to the $value before it is saved in the database.
	 *
	 * @since 0.8
	 *
	 * @param mixed $value The value found in the database.
	 * @param int $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	public function update_value( $value, $post_id, $field ) {

		// --<
		return $value;

	}
	 */



	/**
	 * This filter is appied to the value after it is loaded from the database
	 * and before it is returned to the template.
	 *
	 * @since 0.8
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
	 * @since 0.8
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

		// --<
		return $valid;

	}



	/**
	 * This action is fired after a value has been deleted from the database.
	 *
	 * Please note that saving a blank value is treated as an update, not a delete.
	 *
	 * @since 0.8
	 *
	 * @param int $post_id The Post ID from which the value was deleted.
	 * @param str $key The meta key which the value was deleted.
	public function delete_value( $post_id, $key ) {

	}
	 */



	/**
	 * This filter is applied to the Field after it is loaded from the database.
	 *
	 * @since 0.8
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
	 * @since 0.8
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
	 * @since 0.8
	 *
	 * @param array $field The field array holding all the field options.
	public function delete_field( $field ) {

	}
	 */



	/**
	 * Modify the Field with defaults and Subfield definitions.
	 *
	 * @since 0.8
	 *
	 * @param array $field The field array holding all the field options.
	 * @return array $subfields The subfield array.
	 */
	public function modify_field( $field ) {

		/*
		 * Set the max value to match the "max_multiple" value of the Custom Group
		 * in CiviCRM. This may not exist, in which case it is unlimited.
		 *
		 * @see civicrm/CRM/Core/BAO/CustomGroup.php
		 */
		$field['max'] = 0;
		$field['min'] = 0;

		/*
		 * Set the layout to match the "style" value of the Custom Group in CiviCRM.
		 *
		 * @see civicrm/CRM/Core/BAO/CustomGroup.php
		 */
		$field['layout'] = 'table';

		// Set sensible defaults.
		$field['button_label'] = __( 'Add Record Set', 'civicrm-acf-integration' );
		$field['collapsed'] = '';
		$field['prefix'] = '';

		// Set wrapper class.
		$field['wrapper']['class'] = 'civicrm_multiset';

		/*
		// Define Multiple Record Set "Name" subfield.
		$number = [
			'key' => 'field_multiset_name',
			'label' => __( 'Multiple Record Set', 'civicrm-acf-integration' ),
			'name' => 'multiset_name',
			'type' => 'text',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '30',
				'class' => 'civicrm_multiset_name',
				'id' => '',
			],
			'default_value' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'maxlength' => '',
			'prefix' => '',
		];

		// Get Locations.
		$location_types = $this->civicrm->multiset->location_types_get();

		// Build Location Types choices array for dropdown.
		$locations = [];
		foreach( $location_types AS $location_type ) {
			$locations[$location_type['id']] = esc_attr( $location_type['display_name'] );
		}

		// Define Location field.
		$location = [
			'key' => 'field_multiset_location',
			'label' => __( 'Location', 'civicrm-acf-integration' ),
			'name' => 'location',
			'type' => 'select',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
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

		// Define Provider field.
		$provider = [
			'key' => 'field_multiset_provider',
			'label' => __( 'Provider', 'civicrm-acf-integration' ),
			'name' => 'provider',
			'type' => 'select',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => '',
				'id' => '',
			],
			'choices' => $this->civicrm->multiset->multiset_providers_get(),
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
			'key' => 'field_multiset_primary',
			'label' => __( 'Is Primary', 'civicrm-acf-integration' ),
			'name' => 'is_primary',
			'type' => 'radio',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '10',
				'class' => 'civicrm_multiset_primary',
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

		// Define hidden CiviCRM Multiple Record Set ID field.
		$multiset_id = [
			'readonly' => true,
			'key' => 'field_multiset_id',
			'label' => __( 'CiviCRM ID', 'civicrm-acf-integration' ),
			'name' => 'civicrm_multiset_id',
			'type' => 'number',
			'parent' => $field['key'],
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => [
				'width' => '',
				'class' => 'civicrm_multiset_id',
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
		$field['sub_fields'] = [ $number, $location, $provider, $primary, $im_id ];
		*/

		// --<
		return $field;

	}



} // Class ends.




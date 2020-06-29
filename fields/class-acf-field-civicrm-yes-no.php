<?php
/**
 * ACF "CiviCRM Yes/No Field" Class.
 *
 * Provides a "CiviCRM Yes/No Field" Custom ACF Field in ACF 5+.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.4.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration Custom ACF Field Type - CiviCRM Yes/No Field.
 *
 * A class that encapsulates a "CiviCRM Yes/No" Custom ACF Field in ACF 5+.
 *
 * @since 0.4.1
 */
class CiviCRM_ACF_Integration_Custom_CiviCRM_Yes_No extends acf_field {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Advanced Custom Fields object.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var object $cpt The Advanced Custom Fields object.
	 */
	public $acf;

	/**
	 * CiviCRM Utilities object.
	 *
	 * @since 0.6.4
	 * @access public
	 * @var object $civicrm The CiviCRM Utilities object.
	 */
	public $civicrm;

	/**
	 * Field Type name.
	 *
	 * Single word, no spaces. Underscores allowed.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var str $name The Field Type name.
	 */
	public $name = 'civicrm_yes_no';

	/**
	 * Field Type label.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Multiple words, can include spaces, visible when selecting a field type.
	 *
	 * @since 0.4.1
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
	 * @since 0.4.1
	 * @access public
	 * @var str $label The Field Type category.
	 */
	public $category = 'choice';

	/**
	 * Field Type defaults.
	 *
	 * Array of default settings which are merged into the field object.
	 * These are used later in settings.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var array $defaults The Field Type defaults.
	 */
	public $defaults = [
		'choices' => [],
		'default_value' => '2', // '1' = Yes, '0' = No.
		'allow_null' => 0,
		'return_format' => 'value',
	];

	/**
	 * Field Type settings.
	 *
	 * Contains "version", "url" and "path" as references for use with assets.
	 *
	 * @since 0.4.1
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
	 * @since 0.4.1
	 * @access public
	 * @var array $l10n The Field Type translations.
	 */
	public $l10n = [];



	/**
	 * Sets up the Field Type.
	 *
	 * @since 0.4.1
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to ACF Utilities.
		$this->acf = $parent;

		// Store reference to CiviCRM Utilities.
		$this->civicrm = $this->plugin->civicrm;

		// Define label.
		$this->label = __( 'CiviCRM Yes / No', 'civicrm-acf-integration' );

		// Define translations.
		$this->l10n = [
			// Example message.
			'error'	=> __( 'Error! Please enter a higher value.', 'civicrm-acf-integration' ),
		];

		// Define choices.
		$this->defaults['choices'] = [
			'1' => __( 'Yes', 'civicrm-acf-integration' ),
			'0' => __( 'No', 'civicrm-acf-integration' ),
			'2' => __( 'Unknown', 'civicrm-acf-integration' ),
		];

		// Call parent.
		parent::__construct();

	}



	/**
	 * Create extra Settings for this Field Type.
	 *
	 * These extra Settings will be visible when editing a Field.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The Field being edited.
	 */
	public function render_field_settings( $field ) {

		// Get the Custom Fields for this CiviCRM Contact Type.
		$custom_fields = $this->civicrm->custom_field->get_for_acf_field( $field );

		// Filter fields to include only "Yes/No".
		$filtered_fields = [];
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			foreach( $custom_group AS $custom_field ) {
				if ( ! empty( $custom_field['data_type'] ) AND $custom_field['data_type'] == 'Boolean' ) {
					if ( ! empty( $custom_field['html_type'] ) AND $custom_field['html_type'] == 'Radio' ) {
						$filtered_fields[$custom_group_name][] = $custom_field;
					}
				}
			}
		}

		// Bail if there are no fields.
		if ( empty( $filtered_fields ) ) {
			return;
		}

		// Get Setting field.
		$setting = $this->civicrm->contact->acf_field_get( $filtered_fields );

		// Now add it.
		acf_render_field_setting( $field, $setting );

	}



	/**
	 * Creates the HTML interface for this Field Type.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The Field being rendered.
	 */
	public function render_field( $field ) {

		// Change Field into a checkbox.
		$field['type'] = 'radio';
		$field['allow_null'] = 0;

		// Define choices.
		$field['choices'] = $this->defaults['choices'];

		// Init list definition.
		$ul = [
			'class' => 'acf-radio-list acf-hl',
			'data-allow_null' => $field['allow_null'],
		];

		// Select value.
		$value = strval( $field['value'] );

		// Set checked item flag, override if already saved.
		$checked = $this->defaults['default_value'];
		if ( isset( $field['choices'][$value] ) ) {
			$checked = $value;
		}

		// Ensure we have a string.
		$checked = strval( $checked );

		// Hidden input.
		$html = acf_get_hidden_input( [ 'name' => $field['name'] ] );

		// Open list.
		$html .= '<ul ' . acf_esc_attr( $ul ) . '>';

		// Init counter.
		$i = 0;

		// Loop through choices.
		foreach( $field['choices'] as $value => $label ) {

			// Ensure value is a string.
			$value = strval( $value );

			// Define input attributes.
			$atts = [
				'type' => 'radio',
				'id' => $field['id'],
				'name' => $field['name'],
				'value' => $value,
			];

			// Maybe set checked.
			$class = '';
			if ( $value === $checked ) {
				$atts['checked'] = 'checked';
				$class = ' class="selected"';
			}

			// Bump counter.
			$i++;
			if ( $i > 1 ) {
				$atts['id'] .= '-' . $value;
			}

			// Append radio button.
			$html .= '<li><label' . $class . '><input ' . acf_esc_attr( $atts ) . '/>' . $label . '</label></li>';

		}

		// Close list.
		$html .= '</ul>';

		// Print to screen.
		echo $html;

	}



	/**
	 * This method is called in the "admin_enqueue_scripts" action on the edit
	 * screen where this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.4.1
	public function input_admin_enqueue_scripts() {

	}
	 */



	/**
	 * This method is called in the admin_head action on the edit screen where
	 * this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.4.1
	public function input_admin_head() {

	}
	 */



	/**
 	 * This method is called once on the 'input' page between the head and footer.
	 *
	 * There are 2 situations where ACF did not load during the
	 * 'acf/input_admin_enqueue_scripts' and 'acf/input_admin_head' actions
	 * because ACF did not know it was going to be used. These situations are
	 * seen on comments / user-edit forms on the front end. This function will
	 * always be called, and includes $args that related to the current screen
	 * such as $args['post_id'].
	 *
	 * @since 0.4.1
	 *
	 * @param array $args The arguments related to the current screen.
	public function input_form_data( $args ) {

	}
	 */



	/**
	 * This action is called in the "admin_footer" action on the edit screen
	 * where this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.4.1
	public function input_admin_footer() {

	}
	 */



	/**
	 * This action is called in the "admin_enqueue_scripts" action on the edit
	 * screen where this Field is edited.
	 *
	 * Use this action to add CSS and JavaScript to assist your
	 * render_field_options() action.
	 *
	 * @since 0.4.1
	public function field_group_admin_enqueue_scripts() {

	}
	 */



	/**
	 * This action is called in the "admin_head" action on the edit screen where
	 * this Field is edited.
	 *
	 * Use this action to add CSS and JavaScript to assist your
	 * render_field_options() action.
	 *
	 * @since 0.4.1
	public function field_group_admin_head() {

	}
	 */



	/**
	 * This filter is applied to the $value after it is loaded from the database.
	 *
	 * @since 0.4.1
	 *
	 * @param mixed $value The value found in the database.
	 * @param int $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	 */
	public function load_value( $value, $post_id, $field ) {

		// Must be single value.
		if ( is_array( $value ) ) {
			$value = array_pop( $value );
		}

		// --<
		return $value;

	}



	/**
	 * This filter is applied to the $value before it is saved in the database.
	 *
	 * @since 0.4.1
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
	 * @since 0.4.1
	 *
	 * @param mixed $value The value which was loaded from the database.
	 * @param mixed $post_id The Post ID from which the value was loaded.
	 * @param array $field The field array holding all the field options.
	 * @return mixed $value The modified value.
	 */
	public function format_value( $value, $post_id, $field ) {

		// Format the value.
		$value = acf_get_field_type( 'select' )->format_value( $value, $post_id, $field );

		// --<
		return $value;

	}



	/**
	 * This filter is used to perform validation on the value prior to saving.
	 *
	 * All values are validated regardless of the field's required setting.
	 * This allows you to validate and return messages to the user if the value
	 * is not correct.
	 *
	 * @since 0.4.1
	 *
	 * @param bool $valid The validation status based on the value and the field's required setting.
	 * @param mixed $value The $_POST value.
	 * @param array $field The field array holding all the field options.
	 * @param str $input The corresponding input name for $_POST value.
	 * @return bool|str $valid False if not valid, or string for error message.
	public function validate_value( $valid, $value, $field, $input ){

		// Basic usage.
		if ( $value < $field['custom_minimum_setting'] ) {
			$valid = false;
		}

		// Advanced usage.
		if ( $value < $field['custom_minimum_setting'] ) {
			$valid = __( 'The value is too little!', 'civicrm-acf-integration' ),
		}

		// --<
		return $valid;

	}
	 */



	/**
	 * This action is fired after a value has been deleted from the database.
	 *
	 * Please note that saving a blank value is treated as an update, not a delete.
	 *
	 * @since 0.4.1
	 *
	 * @param int $post_id The Post ID from which the value was deleted.
	 * @param str $key The meta key which the value was deleted.
	public function delete_value( $post_id, $key ) {

	}
	 */



	/**
	 * This filter is applied to the Field after it is loaded from the database.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The field array holding all the field options.
	 * @return array $field The modified field data.
	public function load_field( $field ) {

		// --<
		return $field;

	}
	 */



	/**
	 * This filter is applied to the Field before it is saved to the database.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The field array holding all the field options.
	 * @return array $field The modified field data.
	public function update_field( $field ) {

		// --<
		return $field;

	}
	 */



	/**
	 * This action is fired after a Field is deleted from the database.
	 *
	 * @since 0.4.1
	 *
	 * @param array $field The field array holding all the field options.
	public function delete_field( $field ) {

	}
	 */



} // Class ends.




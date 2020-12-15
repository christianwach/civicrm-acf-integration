<?php
/**
 * CiviCRM ACF Integration Phone Shortcode Class.
 *
 * Provides a Shortcode for rendering CiviCRM Phone records.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * Custom Shortcodes Class.
 *
 * A class that encapsulates a Shortcode  rendering CiviCRM Phone records.
 *
 * @since 0.8.2
 */
class CiviCRM_ACF_Integration_Shortcode_Phone {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * Phone object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var object $phone The Phone object.
	 */
	public $phone;

	/**
	 * Shortcode name.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var str $tag The Shortcode name.
	 */
	public $tag = 'cai_phone';



	/**
	 * Constructor.
	 *
	 * @since 0.8.2
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to CiviCRM object.
		$this->civicrm = $parent->civicrm;

		// Store reference to Phone object.
		$this->phone = $parent;

		// Init when the CiviCRM Phone object is loaded.
		add_action( 'civicrm_acf_integration_civicrm_phone_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.8.2
	 */
	public function register_hooks() {

		// Register Shortcode
		add_action( 'init', [ $this, 'shortcode_register' ] );

		// Shortcake compatibility.
		add_action( 'register_shortcode_ui', [ $this, 'shortcake' ] );

	}



	// -------------------------------------------------------------------------



	/**
	 * Register our Shortcode.
	 *
	 * @since 0.8.2
	 */
	public function shortcode_register() {

		// Register our Shortcode and its callback.
		add_shortcode( $this->tag, [ $this, 'shortcode_render' ] );

	}



	/**
	 * Render the Shortcode.
	 *
	 * @since 0.8.2
	 *
	 * @param array $attr The saved Shortcode attributes.
	 * @param str $content The enclosed content of the Shortcode.
	 * @param str $tag The Shortcode which invoked the callback.
	 * @return str $content The HTML-formatted Shortcode content.
	 */
	public function shortcode_render( $attr, $content = '', $tag = '' ) {

		// Return something else for feeds.
		if ( is_feed() ) {
			return '<p>' . __( 'Visit the website to see the Phone record.', 'civicrm-acf-integration' ) . '</p>';
		}

		// Default Shortcode attributes.
		$defaults = [
			'field' => '',
			'location_type' => null,
			'phone_type' => null,
			'style' => 'list',
			'post_id' => null,
		];

		// Get parsed attributes.
		$atts = shortcode_atts( $defaults, $attr, $tag );

		// If there's no ACF Field attribute, show a message.
		if ( empty( $atts['field'] ) ) {
			return '<p>' . __( 'Please include an ACF Field attribute.', 'civicrm-acf-integration' ) . '</p>';
		}

		// Get content from theme function.
		$content = cacf_get_phone_numbers_by_type_ids(
			$atts['location_type'], $atts['phone_type'], $atts['style'], $atts['field'], $atts['post_id']
		);

		// --<
		return $content;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add compatibility with Shortcake.
	 *
	 * @since 0.8.2
	 */
	public function shortcake() {

		// For now, let's be extra-safe and bail if not present.
		if ( ! function_exists( 'shortcode_ui_register_for_shortcode' ) ) {
			return;
		}

		// Add styles for TinyMCE editor.
		//add_filter( 'mce_css', [ $this, 'shortcake_styles' ] );

		// ACF Field selector.
		$field = [
			'label' => __( 'ACF Field', 'civicrm-acf-integration' ),
			'attr'  => 'field',
			'type'  => 'text',
			'description' => __( 'Please enter an ACF Field selector.', 'civicrm-acf-integration' ),
		];

		// Location Types select.
		$location_types = [
			'label' => __( 'Location Type', 'civicrm-acf-integration' ),
			'attr'  => 'location_type',
			'type'  => 'select',
			'options' => $this->shortcake_select_location_types_get(),
			'description' => __( 'Please select a Location Type.', 'civicrm-acf-integration' ),
		];

		// Phone Types select.
		$phone_types = [
			'label' => __( 'Phone Type (optional)', 'civicrm-acf-integration' ),
			'attr'  => 'phone_type',
			'type'  => 'select',
			'options' => $this->shortcake_select_phone_types_get(),
			'description' => __( 'Please select a Phone Type.', 'civicrm-acf-integration' ),
		];

		// Render style select.
		$style = [
			'label' => __( 'Style', 'civicrm-acf-integration' ),
			'attr'  => 'style',
			'type'  => 'select',
			'options' => [
				'list' => __( 'List', 'civicrm-acf-integration' ),
				'commas' => __( 'Comma-separated', 'civicrm-acf-integration' ),
			],
			'description' => __( 'Please choose list or comma-separated output.', 'civicrm-acf-integration' ),
		];

		// Get all used Post Types.
		$mapped_post_types = $this->plugin->mapping->mappings_get_all();

		// WordPress Post ID.
		$post_id = [
			'label' => __( 'Post (optional)', 'civicrm-acf-integration' ),
			'attr'  => 'post_id',
			'type'  => 'post_select',
			'query'  => [ 'post_type' => array_values( $mapped_post_types ) ],
			'description' => __( 'Please select a Post.', 'civicrm-acf-integration' ),
		];

		// Build Settings array.
		$settings = [

			// Window title.
			'label' => esc_html__( 'CiviCRM Phone', 'civicrm-acf-integration' ),

			// Icon.
			'listItemImage' => 'dashicons-phone',

			// Limit to synced CPTs only?
			//'post_type' => array_values( $mapped_post_types ),

			// Window elements.
			'attrs' => [
				$field,
				$location_types,
				$phone_types,
				$style,
				$post_id,
			],

		];

		// Register Shortcake options.
		shortcode_ui_register_for_shortcode( $this->tag, $settings );

	}



	/**
	 * Add stylesheet to TinyMCE when Shortcake is active.
	 *
	 * @since 0.8.2
	 *
	 * @param str $mce_css The existing list of stylesheets that TinyMCE will load.
	 * @return str $mce_css The modified list of stylesheets that TinyMCE will load.
	 */
	public function shortcake_styles( $mce_css ) {

		// Add our styles to TinyMCE.
		$mce_css .= ', ' . CIVICRM_ACF_INTEGRATION_PATH . 'assets/css/cai-shortcode-phone.css';

		// --<
		return $mce_css;

	}



	/**
	 * Get Location Types select array for Shortcake registration.
	 *
	 * @since 0.8.2
	 *
	 * @return array $options The properly formatted array for the select.
	 */
	public function shortcake_select_location_types_get() {

		// Init return.
		$options = [ '' => __( 'Select a Location Type', 'civicrm-acf-integration' ) ];

		// Get Locations.
		$location_types = $this->phone->location_types_get();

		// Build Location Types choices array for dropdown.
		foreach( $location_types AS $location_type ) {
			$options[$location_type['id']] = esc_attr( $location_type['display_name'] );
		}

		// --<
		return $options;

	}



	/**
	 * Get Phone Types select array for Shortcake registration.
	 *
	 * @since 0.8.2
	 *
	 * @return array $options The properly formatted array for the select.
	 */
	public function shortcake_select_phone_types_get() {

		// Init return.
		$options = [ '' => __( 'Select a Phone Type', 'civicrm-acf-integration' ) ];

		// Add Phone Types.
		$options = $options + $this->phone->phone_types_get();

		// --<
		return $options;

	}



} // Class ends.




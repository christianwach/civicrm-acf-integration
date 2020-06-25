<?php
/**
 * ACF Class.
 *
 * Handles general ACF functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration ACF Class.
 *
 * A class that encapsulates ACF functionality.
 *
 * @since 0.1
 */
class CiviCRM_ACF_Integration_ACF {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * ACF Field Group object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $field_group The ACF Field Group object.
	 */
	public $field_group;

	/**
	 * ACF Field object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $field The ACF Field object.
	 */
	public $field;



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Bail if ACF isn't found.
		if ( ! function_exists( 'acf' ) ) {
			return;
		}

		// Store reference to plugin.
		$this->plugin = $plugin;

		// Init when this plugin is loaded.
		add_action( 'civicrm_acf_integration_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.3
	 */
	public function initialise() {

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.3
		 */
		do_action( 'civicrm_acf_integration_acf_loaded' );

	}



	/**
	 * Include files.
	 *
	 * @since 0.3
	 */
	public function include_files() {

		// Include class files.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-acf-field-group.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-acf-field.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.3
	 */
	public function setup_objects() {

		// Init Field Group object.
		$this->field_group = new CiviCRM_ACF_Integration_ACF_Field_Group( $this );

		// Init Field object.
		$this->field = new CiviCRM_ACF_Integration_ACF_Field( $this );

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.3
	 */
	public function register_hooks() {

		// Include any Field Types that we have defined.
		add_action( 'acf/include_field_types', [ $this, 'include_field_types' ] );

	}



	/**
	 * Include Field Types for ACF5.
	 *
	 * @since 0.3.1
	 */
	public function include_field_types( $version ) {

		// Include class files.
		include CIVICRM_ACF_INTEGRATION_PATH . 'fields/class-acf-field-civicrm-contact-id.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'fields/class-acf-field-civicrm-contact.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'fields/class-acf-field-civicrm-yes-no.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'fields/class-acf-field-civicrm-relationship.php';

		// Create fields.
		new CiviCRM_ACF_Integration_Custom_CiviCRM_Contact_ID_Field( $this );
		new CiviCRM_ACF_Integration_Custom_CiviCRM_Contact_Field( $this );
		new CiviCRM_ACF_Integration_Custom_CiviCRM_Yes_No( $this );
		new CiviCRM_ACF_Integration_Custom_CiviCRM_Relationship( $this );

	}



} // Class ends.




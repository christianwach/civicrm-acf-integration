<?php
/**
 * CiviCRM Class.
 *
 * Handles general CiviCRM functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Class.
 *
 * A class that encapsulates CiviCRM functionality.
 *
 * @since 0.1
 */
class CiviCRM_ACF_Integration_CiviCRM {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM Contact Type object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $contact_type The CiviCRM Contact Type object.
	 */
	public $contact_type;

	/**
	 * CiviCRM Contact object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $contact The CiviCRM Contact object.
	 */
	public $contact;

	/**
	 * CiviCRM Group object.
	 *
	 * @since 0.6.4
	 * @access public
	 * @var object $group The CiviCRM Group object.
	 */
	public $group;

	/**
	 * CiviCRM Email object.
	 *
	 * @since 0.4.5
	 * @access public
	 * @var object $email The CiviCRM Email object.
	 */
	public $email;

	/**
	 * CiviCRM Website object.
	 *
	 * @since 0.4.5
	 * @access public
	 * @var object $website The CiviCRM Website object.
	 */
	public $website;

	/**
	 * CiviCRM Phone object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $phone The CiviCRM Phone object.
	 */
	public $phone;

	/**
	 * CiviCRM Instant Messenger object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $phone The CiviCRM Instant Messenger object.
	 */
	public $im;

	/**
	 * CiviCRM Contact Field object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $contact_field The CiviCRM Contact Field object.
	 */
	public $contact_field;

	/**
	 * CiviCRM Custom Group object.
	 *
	 * @since 0.8
	 * @access public
	 * @var object $custom_group The CiviCRM Custom Group object.
	 */
	//public $custom_group;

	/**
	 * CiviCRM Custom Field object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $custom_field The CiviCRM Custom Field object.
	 */
	public $custom_field;

	/**
	 * CiviCRM Relationship object.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var object $relationship The CiviCRM Relationship object.
	 */
	public $relationship;

	/**
	 * CiviCRM Address object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var object $address The CiviCRM Address object.
	 */
	public $address;

	/**
	 * CiviCRM Activity Type object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $activity_type The CiviCRM Activity Type object.
	 */
	public $activity_type;

	/**
	 * CiviCRM Activity object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $activity The CiviCRM Activity object.
	 */
	public $activity;

	/**
	 * CiviCRM Activity Field object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $activity_field The CiviCRM Activity Field object.
	 */
	public $activity_field;

	/**
	 * "CiviCRM Field" field key in the ACF Field data.
	 *
	 * This "top level" field key is common to "Contact" and "Activity" Entities
	 * where the field name prefix distiguishes the target Entity.
	 *
	 * @see self::custom_field_prefix()
	 * @see self::contact_field_prefix()
	 * @see self::activity_field_prefix()
	 *
	 * @since 0.7.3
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Field" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_custom_field';



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Bail if CiviCRM isn't found.
		if ( ! function_exists( 'civi_wp' ) ) {
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
	 * @since 0.2.1
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
		 * @since 0.2.1
		 */
		do_action( 'civicrm_acf_integration_civicrm_loaded' );

	}



	/**
	 * Include files.
	 *
	 * @since 0.1
	 */
	public function include_files() {

		// Include class files.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-civicrm-base.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-contact-type.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-contact.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-contact-field.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-civicrm-group.php';
		//include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-custom-group.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-custom-field.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-relationship.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-address.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-email.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-website.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-phone.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-im.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-activity-type.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-activity.php';
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-activity-field.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.1
	 */
	public function setup_objects() {

		// Init Contact Type.
		$this->contact_type = new CiviCRM_ACF_Integration_CiviCRM_Contact_Type( $this );

		// Init Contact and Contact Fields.
		$this->contact = new CiviCRM_ACF_Integration_CiviCRM_Contact( $this );
		$this->contact_field = new CiviCRM_ACF_Integration_CiviCRM_Contact_Field( $this );

		// Init Group.
		$this->group = new CiviCRM_ACF_Integration_CiviCRM_Group( $this );

		// Init Other Entities.
		//$this->custom_group = new CiviCRM_ACF_Integration_CiviCRM_Custom_Group( $this );
		$this->custom_field = new CiviCRM_ACF_Integration_CiviCRM_Custom_Field( $this );
		$this->relationship = new CiviCRM_ACF_Integration_CiviCRM_Relationship( $this );
		$this->address = new CiviCRM_ACF_Integration_CiviCRM_Address( $this );
		$this->email = new CiviCRM_ACF_Integration_CiviCRM_Email( $this );
		$this->website = new CiviCRM_ACF_Integration_CiviCRM_Website( $this );
		$this->phone = new CiviCRM_ACF_Integration_CiviCRM_Phone( $this );
		$this->im = new CiviCRM_ACF_Integration_CiviCRM_Instant_Messenger( $this );

		// Init Activity Type.
		$this->activity_type = new CiviCRM_ACF_Integration_CiviCRM_Activity_Type( $this );

		// Init Activity and Activity Fields.
		$this->activity = new CiviCRM_ACF_Integration_CiviCRM_Activity( $this );
		$this->activity_field = new CiviCRM_ACF_Integration_CiviCRM_Activity_Field( $this );

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Trace database operations.
		//add_action( 'civicrm_pre', [ $this, 'trace_pre' ], 10, 4 );
		//add_action( 'civicrm_post', [ $this, 'trace_post' ], 10, 4 );

	}



	/**
	 * Initialise CiviCRM if necessary.
	 *
	 * @since 0.1
	 *
	 * @return bool $initialised True if CiviCRM initialised, false otherwise.
	 */
	public function is_initialised() {

		// Init only when CiviCRM is fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) return false;
		if ( ! CIVICRM_INSTALLED ) return false;

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) return false;

		// Try and initialise CiviCRM.
		return civi_wp()->initialize();

	}



	/**
	 * Check a CiviCRM permission.
	 *
	 * @since 0.3
	 *
	 * @param str $permission The permission string.
	 * @return bool $permitted True if allowed, false otherwise.
	 */
	public function check_permission( $permission ) {

		// Always deny if CiviCRM is not active.
		if ( ! $this->is_initialised() ) {
			return false;
		}

		// Deny by default.
		$permitted = false;

		// Check CiviCRM permissions.
		if ( CRM_Core_Permission::check( $permission ) ) {
			$permitted = true;
		}

		/**
		 * Return permission but allow overrides.
		 *
		 * @since 0.3
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 * @return bool $permitted True if allowed, false otherwise.
		 */
		return apply_filters( 'civicrm_acf_integration_permitted', $permitted, $permission );

	}



	/**
	 * Get a CiviCRM admin link.
	 *
	 * @since 0.3
	 *
	 * @param str $path The CiviCRM path.
	 * @param str $params The CiviCRM parameters.
	 * @return string $link The URL of the CiviCRM page.
	 */
	public function get_link( $path = '', $params = null ) {

		// Init link.
		$link = '';

		// Init CiviCRM or bail.
		if ( ! $this->is_initialised() ) {
			return $link;
		}

		// Use CiviCRM to construct link.
		$link = CRM_Utils_System::url(
			$path, // Path to the resource.
			$params, // Params to pass to resource.
			true, // Force an absolute link.
			null, // Fragment (#anchor) to append.
			true, // Encode special HTML characters.
			false, // CMS front end.
			true // CMS back end.
		);

		// --<
		return $link;

	}



	// -------------------------------------------------------------------------



	/**
	 * Getter method for the "CiviCRM Field" key.
	 *
	 * @since 0.7.3
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Field" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	/**
	 * Get ACF Field setting prefix that distinguishes Custom Fields from Contact Fields.
	 *
	 * @since 0.6.4
	 *
	 * @return string $custom_field_prefix The prefix of the "CiviCRM Field" value.
	 */
	public function custom_field_prefix() {

		// --<
		return $this->custom_field->custom_field_prefix;

	}



	/**
	 * Get ACF Field setting prefix that distinguishes Contact Fields from Custom Fields.
	 *
	 * @since 0.6.4
	 *
	 * @return string $contact_field_prefix The prefix of the "CiviCRM Field" value.
	 */
	public function contact_field_prefix() {

		// --<
		return $this->contact->contact_field_prefix;

	}



	/**
	 * Get ACF Field setting prefix that distinguishes Activity Fields from Custom Fields.
	 *
	 * @since 0.7.3
	 *
	 * @return string $activity_field_prefix The prefix of the "CiviCRM Field" value.
	 */
	public function activity_field_prefix() {

		// --<
		return $this->activity->activity_field_prefix;

	}



	// -------------------------------------------------------------------------



	/**
	 * Utility for tracing calls to hook_civicrm_pre.
	 *
	 * @since 0.1.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function trace_pre( $op, $objectName, $objectId, $objectRef ) {

		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
			//'backtrace' => $trace,
		], true ) );

	}



	/**
	 * Utility for tracing calls to hook_civicrm_post.
	 *
	 * @since 0.1.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function trace_post( $op, $objectName, $objectId, $objectRef ) {

		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
			//'backtrace' => $trace,
		], true ) );

	}



	// -------------------------------------------------------------------------



	/**
	 * Utility for de-nullifying CiviCRM data.
	 *
	 * @since 0.7.3
	 *
	 * @param mixed $value The existing value.
	 * @return mixed $value The cleaned value.
	 */
	public function denullify( $value ) {

		// Catch inconsistent CiviCRM "empty-ish" values.
		if ( empty( $value ) OR $value == 'null' OR $value == 'NULL' ) {
			$value = '';
		}

		// --<
		return $value;

	}



} // Class ends.




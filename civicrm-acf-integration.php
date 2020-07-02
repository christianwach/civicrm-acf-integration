<?php /*
--------------------------------------------------------------------------------
Plugin Name: CiviCRM ACF Integration
Plugin URI: https://github.com/christianwach/civicrm-acf-integration
Description: Enables integration between CiviCRM Entities and WordPress Entities using Advanced Custom Fields.
Version: 0.7
Author: Christian Wach
Author URI: https://haystack.co.uk
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: civicrm-acf-integration
Domain Path: /languages
Depends: CiviCRM
--------------------------------------------------------------------------------
*/



// Set our version here.
define( 'CIVICRM_ACF_INTEGRATION_VERSION', '0.7' );

// Store reference to this file.
if ( ! defined( 'CIVICRM_ACF_INTEGRATION_FILE' ) ) {
	define( 'CIVICRM_ACF_INTEGRATION_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'CIVICRM_ACF_INTEGRATION_URL' ) ) {
	define( 'CIVICRM_ACF_INTEGRATION_URL', plugin_dir_url( CIVICRM_ACF_INTEGRATION_FILE ) );
}
// Store PATH to this plugin's directory.
if ( ! defined( 'CIVICRM_ACF_INTEGRATION_PATH' ) ) {
	define( 'CIVICRM_ACF_INTEGRATION_PATH', plugin_dir_path( CIVICRM_ACF_INTEGRATION_FILE ) );
}



/**
 * CiviCRM ACF Integration Plugin Class.
 *
 * A class that encapsulates plugin functionality.
 *
 * @since 0.1
 */
class CiviCRM_ACF_Integration {

	/**
	 * Admin Utilities object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $civicrm The Admin Utilities object.
	 */
	public $admin;

	/**
	 * CiviCRM Utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $civicrm The CiviCRM Utilities object.
	 */
	public $civicrm;

	/**
	 * WordPress Post Type Utilities object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var object $post_type The Post Type Utilities object.
	 */
	public $post_type;

	/**
	 * WordPress Post Utilities object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var object $post The Post Utilities object.
	 */
	public $post;

	/**
	 * Advanced Custom Fields object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $cpt The Advanced Custom Fields object.
	 */
	public $acf;

	/**
	 * Mapping object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $mapping The Mapping object.
	 */
	public $mapping;

	/**
	 * Mapper object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $mapper The Mapper object.
	 */
	public $mapper;



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Initialise on "plugins_loaded".
		add_action( 'plugins_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Only do this once.
		static $done;
		if ( isset( $done ) AND $done === true ) {
			return;
		}

		// Load translation.
		$this->translation();

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		/**
		 * Broadcast that this plugin is now loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'civicrm_acf_integration_loaded' );

		// We're done.
		$done = true;

	}



	/**
	 * Enable translation.
	 *
	 * @since 0.1
	 */
	public function translation() {

		// Load translations.
		load_plugin_textdomain(
			'civicrm-acf-integration', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( CIVICRM_ACF_INTEGRATION_FILE ) ) . '/languages/' // Relative path to files.
		);

	}



	/**
	 * Include files.
	 *
	 * @since 0.1
	 */
	public function include_files() {

		// Include functions.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-functions.php';

		// Include Admin class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-admin.php';

		// Include CiviCRM class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-civicrm.php';

		// Include Post Type class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-post-type.php';

		// Include Post class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-post.php';

		// Include ACF class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-acf.php';

		// Include Mapping class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-mapping.php';

		// Include Mapper class.
		include CIVICRM_ACF_INTEGRATION_PATH . 'includes/civicrm-acf-integration-mapper.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.1
	 */
	public function setup_objects() {

		// Init Admin object.
		$this->admin = new CiviCRM_ACF_Integration_Admin( $this );

		// Init CiviCRM object.
		$this->civicrm = new CiviCRM_ACF_Integration_CiviCRM( $this );

		// Init Post Type object.
		$this->post_type = new CiviCRM_ACF_Integration_Post_Type( $this );

		// Init Post object.
		$this->post = new CiviCRM_ACF_Integration_Post( $this );

		// Init ACF object.
		$this->acf = new CiviCRM_ACF_Integration_ACF( $this );

		// Init Mapping object.
		$this->mapping = new CiviCRM_ACF_Integration_Mapping( $this );

		// Init Mapper object.
		$this->mapper = new CiviCRM_ACF_Integration_Mapper( $this );

	}



	/**
	 * Perform plugin activation tasks.
	 *
	 * @since 0.1
	 */
	public function activate() {

		// Maybe init.
		$this->initialise();

	}



	/**
	 * Perform plugin deactivation tasks.
	 *
	 * @since 0.1
	 */
	public function deactivate() {

		// Maybe init.
		$this->initialise();

	}



}



/**
 * Utility to get a reference to this plugin.
 *
 * @since 0.1
 *
 * @return CiviCRM_ACF_Integration $civicrm_acf_integration The plugin reference.
 */
function civicrm_acf_integration() {

	// Store instance in static variable.
	static $civicrm_acf_integration = false;

	// Maybe return instance.
	if ( false === $civicrm_acf_integration ) {
		$civicrm_acf_integration = new CiviCRM_ACF_Integration();
	}

	// --<
	return $civicrm_acf_integration;

}



// Initialise plugin now.
civicrm_acf_integration();

// Activation.
//register_activation_hook( __FILE__, [ civicrm_acf_integration(), 'activate' ] );

// Deactivation.
//register_deactivation_hook( __FILE__, [ civicrm_acf_integration(), 'deactivate' ] );

// Uninstall uses the 'uninstall.php' method.
// See: http://codex.wordpress.org/Function_Reference/register_uninstall_hook




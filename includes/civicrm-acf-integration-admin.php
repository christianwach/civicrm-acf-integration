<?php
/**
 * Admin Class.
 *
 * Handles general plugin admin functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration Admin Class
 *
 * A class that encapsulates Admin functionality.
 *
 * @since 0.2
 */
class CiviCRM_ACF_Integration_Admin {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * The installed version of the plugin.
	 *
	 * @since 0.5.1
	 * @access public
	 * @var str $plugin_version The plugin version.
	 */
	public $plugin_version;

	/**
	 * Settings data.
	 *
	 * @since 0.2
	 * @access public
	 * @var array $settings The plugin settings data.
	 */
	public $settings = [];



	/**
	 * Constructor.
	 *
	 * @since 0.2
	 *
	 * @param object $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Store reference to plugin.
		$this->plugin = $plugin;

		// Init when this plugin is loaded.
		add_action( 'civicrm_acf_integration_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.2
	 */
	public function initialise() {

		// Assign installed plugin version.
		$this->plugin_version = $this->site_option_get( 'civicrm_acf_integration_version', false );

		// Do upgrade tasks.
		$this->upgrade_tasks();

		// Store version for later reference if there has been a change.
		if ( $this->plugin_version != CIVICRM_ACF_INTEGRATION_VERSION ) {
			$this->site_option_set( 'civicrm_acf_integration_version', CIVICRM_ACF_INTEGRATION_VERSION );
		}

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Perform tasks if an upgrade is required.
	 *
	 * @since 0.5.1
	 */
	public function upgrade_tasks() {

		// If this is a new install (or an upgrade from a version prior to 0.5.1).
		if ( $this->plugin_version === false ) {

			// Upgrade the legacy mappings array.
			$this->plugin->mapping->mappings_upgrade();

		}

		/*
		// For future upgrades, use something like the following.
		if ( version_compare( CIVICRM_ACF_INTEGRATION_VERSION, '0.5.4', '>=' ) ) {
			// Do something
		}
		*/

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.2
	 */
	public function register_hooks() {

	}



	// -------------------------------------------------------------------------



	/**
	 * Test existence of a specified option.
	 *
	 * @since 0.2
	 *
	 * @param str $option_name The name of the option.
	 * @return bool $exists Whether or not the option exists.
	 */
	public function site_option_exists( $option_name = '' ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to site_option_exists()', 'civicrm-acf-integration' ) );
		}

		// Test by getting option with unlikely default.
		if ( $this->site_option_get( $option_name, 'fenfgehgefdfdjgrkj' ) == 'fenfgehgefdfdjgrkj' ) {
			return false;
		} else {
			return true;
		}

	}



	/**
	 * Return a value for a specified option.
	 *
	 * @since 0.2
	 *
	 * @param str $option_name The name of the option.
	 * @param str $default The default value of the option if it has no value.
	 * @return mixed $value the value of the option.
	 */
	public function site_option_get( $option_name = '', $default = false ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to site_option_get()', 'civicrm-acf-integration' ) );
		}

		// Get option.
		$value = get_site_option( $option_name, $default );

		// --<
		return $value;

	}



	/**
	 * Set a value for a specified option.
	 *
	 * @since 0.2
	 *
	 * @param str $option_name The name of the option.
	 * @param mixed $value The value to set the option to.
	 * @return bool $success True if the value of the option was successfully updated.
	 */
	public function site_option_set( $option_name = '', $value = '' ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to site_option_set()', 'civicrm-acf-integration' ) );
		}

		// Update option.
		return update_site_option( $option_name, $value );

	}



	/**
	 * Delete a specified option.
	 *
	 * @since 0.2
	 *
	 * @param str $option_name The name of the option.
	 * @return bool $success True if the option was successfully deleted.
	 */
	public function site_option_delete( $option_name = '' ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to site_option_delete()', 'civicrm-acf-integration' ) );
		}

		// Delete option.
		return delete_site_option( $option_name );

	}



} // Class ends.




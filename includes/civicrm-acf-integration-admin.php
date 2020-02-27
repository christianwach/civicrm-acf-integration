<?php

/**
 * CiviCRM ACF Integration Admin Class
 *
 * A class that encapsulates Admin functionality.
 *
 * @package CiviCRM_ACF_Integration
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

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.2
	 */
	public function register_hooks() {

	}



} // Class ends.




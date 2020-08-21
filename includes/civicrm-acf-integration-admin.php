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
	 * How many items to process per AJAX request.
	 *
	 * @since 0.6.4
	 * @access public
	 * @var object $step_counts The array of item counts to process per AJAX request.
	 */
	public $step_counts = array(
		'contact_post_types' => 10, // Number of Contact Posts per WordPress Post Type.
		'contact_types' => 10, // Number of Contacts per CiviCRM Contact Type.
		'groups' => 10, // Number of Group Members per CiviCRM Group.
		'activity_post_types' => 10, // Number of Activity Posts per WordPress Post Type.
		'activity_types' => 10, // Number of Activities per CiviCRM Activity Type.
	);



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

		// Is this the back end?
		if ( ! is_admin() ) {
			return;
		}

		// Add AJAX handler.
		add_action( 'wp_ajax_sync_acf_and_civicrm', [ $this, 'sync_acf_and_civicrm' ] );

		// Add menu item(s) to WordPress admin menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 1 );

		// Add AJAX handlers.
		add_action( 'wp_ajax_sync_posts_to_contacts', array( $this, 'stepped_sync_posts_to_contacts' ) );
		add_action( 'wp_ajax_sync_contacts_to_posts', array( $this, 'stepped_sync_contacts_to_posts' ) );
		add_action( 'wp_ajax_sync_groups_to_terms', array( $this, 'stepped_sync_groups_to_terms' ) );
		add_action( 'wp_ajax_sync_posts_to_activities', array( $this, 'stepped_sync_posts_to_activities' ) );
		add_action( 'wp_ajax_sync_activities_to_posts', array( $this, 'stepped_sync_activities_to_posts' ) );

	}



	// -------------------------------------------------------------------------



	/**
	 * Add our admin page(s) to the WordPress admin menu.
	 *
	 * @since 0.6.4
	 */
	public function admin_menu() {

		// We must be network admin in Multisite.
		if ( is_multisite() AND ! is_super_admin() ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add our "Manual Sync" page to the Settings menu.
		$this->sync_page = add_options_page(
			__( 'CiviCRM ACF Integration', 'civicrm-acf-integration' ),
			__( 'CiviCRM ACF Integration', 'civicrm-acf-integration' ),
			'manage_options',
			'civicrm_acf_integration_sync',
			[ $this, 'page_manual_sync' ]
		);

		// Add styles and scripts only on our "Manual Sync" page.
		// @see wp-admin/admin-header.php
		add_action( 'admin_print_styles-' . $this->sync_page, [ $this, 'admin_styles' ] );
		add_action( 'admin_print_scripts-' . $this->sync_page, [ $this, 'admin_scripts' ] );
		//add_action( 'admin_head-' . $this->sync_page, [ $this, 'admin_head' ], 50 );

		// Try and update options.
		$this->settings_update_router();

	}



	/**
	 * Enqueue any styles needed by our Sync page.
	 *
	 * @since 0.6.4
	 */
	public function admin_styles() {

		// Enqueue CSS.
		wp_enqueue_style(
			'cai-admin-style',
			CIVICRM_ACF_INTEGRATION_URL . 'assets/css/manual-sync.css',
			null,
			CIVICRM_ACF_INTEGRATION_VERSION,
			'all' // Media.
		);

	}



	/**
	 * Enqueue any scripts needed by our Sync page.
	 *
	 * @since 0.6.4
	 */
	public function admin_scripts() {

		// Enqueue Javascript.
		wp_enqueue_script(
			'cai-admin-script',
			CIVICRM_ACF_INTEGRATION_URL . 'assets/js/manual-sync.js',
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ],
			CIVICRM_ACF_INTEGRATION_VERSION
		);

		// Get all Post Types mapped to Contacts.
		$mapped_contact_post_types = $this->plugin->post_type->get_mapped( 'contact' );

		// Loop through them and get the data we want.
		$contact_post_types = [];
		foreach( $mapped_contact_post_types AS $contact_post_type ) {
			$contact_post_types[$contact_post_type->name] = [
				'label' => esc_html( $contact_post_type->label ),
				'count' => $this->plugin->post_type->post_count( $contact_post_type->name ),
			];
		}

		// Get all mapped Contact Types.
		$mapped_contact_types = $this->plugin->civicrm->contact_type->get_mapped();

		// Loop through them and get the data we want.
		$contact_types = [];
		foreach( $mapped_contact_types AS $contact_type ) {
			$contact_types[$contact_type['id']] = [
				'label' => esc_html( $contact_type['label'] ),
				'count' => $this->plugin->civicrm->contact_type->contact_count( $contact_type['id'] ),
			];
		}

		// Get all mapped Groups.
		$mapped_groups = $this->plugin->civicrm->group->groups_get_mapped();

		// Loop through them and get the data we want.
		$groups = [];
		foreach( $mapped_groups AS $group ) {
			$groups[$group['id']] = [
				'label' => esc_html( $group['title'] ),
				'count' => $this->plugin->civicrm->group->group_contact_count( $group['id'] ),
			];
		}

		// Get all Post Types mapped to Activities.
		$mapped_activity_post_types = $this->plugin->post_type->get_mapped( 'activity' );

		// Loop through them and get the data we want.
		$activity_post_types = [];
		foreach( $mapped_activity_post_types AS $activity_post_type ) {
			$activity_post_types[$activity_post_type->name] = [
				'label' => esc_html( $activity_post_type->label ),
				'count' => $this->plugin->post_type->post_count( $activity_post_type->name ),
			];
		}

		// Get all mapped Activity Types.
		$mapped_activity_types = $this->plugin->civicrm->activity_type->get_mapped();

		// Loop through them and get the data we want.
		$activity_types = [];
		foreach( $mapped_activity_types AS $activity_type ) {
			$activity_types[$activity_type['value']] = [
				'label' => esc_html( $activity_type['label'] ),
				'count' => $this->plugin->civicrm->activity_type->activity_count( $activity_type['value'] ),
			];
		}

		// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'contact_post_types' => $contact_post_types,
			'contact_types' => $contact_types,
			'groups' => $groups,
			'activity_post_types' => $activity_post_types,
			'activity_types' => $activity_types,
			'step_contact_post_types' => $this->step_counts['contact_post_types'],
			'step_contact_types' => $this->step_counts['contact_types'],
			'step_groups' => $this->step_counts['groups'],
			'step_activity_post_types' => $this->step_counts['activity_post_types'],
			'step_activity_types' => $this->step_counts['activity_types'],
		];

		// Init localisation.
		$localisation = [];

		// Add Contact Post Types localisation.
		$localisation['contact_post_types'] = [
			'total' => __( 'Posts to sync: {{total}}', 'civicrm-acf-integration' ),
			'current' => __( 'Processing posts {{from}} to {{to}}', 'civicrm-acf-integration' ),
			'complete' => __( 'Processing posts {{from}} to {{to}} complete', 'civicrm-acf-integration' ),
			'count' => count( $contact_post_types ),
		];

		// Add Contact Types localisation.
		$localisation['contact_types'] = [
			'total' => __( 'Contacts to sync: {{total}}', 'civicrm-acf-integration' ),
			'current' => __( 'Processing contacts {{from}} to {{to}}', 'civicrm-acf-integration' ),
			'complete' => __( 'Processing contacts {{from}} to {{to}} complete', 'civicrm-acf-integration' ),
			'count' => count( $contact_types ),
		];

		// Add Groups localisation.
		$localisation['groups'] = [
			'total' => __( 'Group members to sync: {{total}}', 'civicrm-acf-integration' ),
			'current' => __( 'Processing group members {{from}} to {{to}}', 'civicrm-acf-integration' ),
			'complete' => __( 'Processing group members {{from}} to {{to}} complete', 'civicrm-acf-integration' ),
			'count' => count( $groups ),
		];

		// Add Activity Post Types localisation.
		$localisation['activity_post_types'] = [
			'total' => __( 'Posts to sync: {{total}}', 'civicrm-acf-integration' ),
			'current' => __( 'Processing posts {{from}} to {{to}}', 'civicrm-acf-integration' ),
			'complete' => __( 'Processing posts {{from}} to {{to}} complete', 'civicrm-acf-integration' ),
			'count' => count( $activity_post_types ),
		];

		// Add Activity Types localisation.
		$localisation['activity_types'] = [
			'total' => __( 'Activitys to sync: {{total}}', 'civicrm-acf-integration' ),
			'current' => __( 'Processing activities {{from}} to {{to}}', 'civicrm-acf-integration' ),
			'complete' => __( 'Processing activities {{from}} to {{to}} complete', 'civicrm-acf-integration' ),
			'count' => count( $activity_types ),
		];

		// Add common localisation.
		$localisation['common'] = [
			'done' => __( 'All done!', 'civicrm-acf-integration' ),
		];

		// Localisation array.
		$vars = [
			'settings' => $settings,
			'localisation' => $localisation,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'cai-admin-script',
			'CiviCRM_ACF_Integration_Sync_Vars',
			$vars
		);

	}



	/**
	 * Get the URL for the form action.
	 *
	 * @since 0.6.4
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function admin_form_url_get() {

		// Sanitise admin page url.
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );
		if ( $url_array ) {
			$target_url = htmlentities( $url_array[0] . '&updated=true' );
		}

		// --<
		return $target_url;

	}



	// -------------------------------------------------------------------------



	/**
	 * Show our "Manual Sync" page.
	 *
	 * @since 0.6.4
	 */
	public function page_manual_sync() {

		// We must be network admin in multisite.
		if ( is_multisite() AND ! is_super_admin() ) {
			wp_die( __( 'You do not have permission to access this page.', 'civicrm-acf-integration' ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'civicrm-acf-integration' ) );
		}

		// Get all Post Types mapped to Contacts.
		$mapped_contact_post_types = $this->plugin->post_type->get_mapped( 'contact' );

		// Loop through them and get the data we want.
		$contact_post_types = [];
		foreach( $mapped_contact_post_types AS $contact_post_type ) {
			$contact_post_types[$contact_post_type->name] = $contact_post_type->label;
		}

		// Get all mapped Contact Types.
		$mapped_contact_types = $this->plugin->civicrm->contact_type->get_mapped();

		// Loop through them and get the data we want.
		$contact_types = [];
		foreach( $mapped_contact_types AS $contact_type ) {
			$contact_types[$contact_type['id']] = $contact_type['label'];
		}

		// Get all mapped Groups.
		$mapped_groups = $this->plugin->civicrm->group->groups_get_mapped();

		// Loop through them and get the data we want.
		$groups = [];
		foreach( $mapped_groups AS $group ) {
			$groups[$group['id']] = $group['title'];
		}

		// Get all Post Types mapped to Activities.
		$mapped_activity_post_types = $this->plugin->post_type->get_mapped( 'activity' );

		// Loop through them and get the data we want.
		$activity_post_types = [];
		foreach( $mapped_activity_post_types AS $activity_post_type ) {
			$activity_post_types[$activity_post_type->name] = $activity_post_type->label;
		}

		// Get all mapped Activity Types.
		$mapped_activity_types = $this->plugin->civicrm->activity_type->get_mapped();

		// Loop through them and get the data we want.
		$activity_types = [];
		foreach( $mapped_activity_types AS $activity_type ) {
			$activity_types[$activity_type['value']] = $activity_type['label'];
		}

		// Include template file.
		include( CIVICRM_ACF_INTEGRATION_PATH . 'assets/templates/wordpress/manual-sync.php' );

	}



	// -------------------------------------------------------------------------



	/**
	 * Route settings updates to relevant methods.
	 *
	 * @since 0.6.4
	 */
	public function settings_update_router() {

		// Get all Post Types mapped to Contacts.
		$mapped_contact_post_types = $this->plugin->post_type->get_mapped( 'contact' );

		// Loop through them and get the data we want.
		$contact_post_types = [];
		foreach( $mapped_contact_post_types AS $contact_post_type ) {
			$contact_post_types[$contact_post_type->name] = 'cai_post_to_contact_' . $contact_post_type->name . '_stop';
		}

		// Get all mapped Contact Types.
		$mapped_contact_types = $this->plugin->civicrm->contact_type->get_mapped();

		// Loop through them and get the data we want.
		$contact_types = [];
		foreach( $mapped_contact_types AS $contact_type ) {
			$contact_types[$contact_type['id']] = 'cai_contact_to_post_' . $contact_type['id'] . '_stop';
		}

		// Get all mapped Groups.
		$mapped_groups = $this->plugin->civicrm->group->groups_get_mapped();

		// Loop through them and get the data we want.
		$groups = [];
		foreach( $mapped_groups AS $group ) {
			$groups[$group['id']] = 'cai_group_to_term_' . $group['id'] . '_stop';
		}

		// Get all Post Types mapped to Activities.
		$mapped_activity_post_types = $this->plugin->post_type->get_mapped( 'activity' );

		// Loop through them and get the data we want.
		$activity_post_types = [];
		foreach( $mapped_activity_post_types AS $activity_post_type ) {
			$activity_post_types[$activity_post_type->name] = 'cai_post_to_activity_' . $activity_post_type->name . '_stop';
		}

		// Get all mapped Activity Types.
		$mapped_activity_types = $this->plugin->civicrm->activity_type->get_mapped();

		// Loop through them and get the data we want.
		$activity_types = [];
		foreach( $mapped_activity_types AS $activity_type ) {
			$activity_types[$activity_type['id']] = 'cai_activity_to_post_' . $activity_type['id'] . '_stop';
		}

		// Init stop, continue and sync flags.
		$stop = false;
		$continue = false;
		$sync_type = false;
		$entity_id = false;

		// Find out if a Contact Post Type button has been pressed.
		foreach( $contact_post_types AS $contact_post_type => $stop_code ) {

			// Define replacements.
			$replacements = [ 'cai_post_to_contact_', '_stop' ];

			// Was a "Stop Sync" button pressed?
			if ( isset( $_POST[$stop_code] ) ) {
				$stop = $stop_code;
				$sync_type = 'contact_post_type';
				$entity_id = str_replace( $replacements, '', $stop_code );
				break;
			}

			// Was a "Sync Now" or "Continue Sync" button pressed?
			$button = str_replace( '_stop', '', $stop_code );
			if ( isset( $_POST[$button] ) ) {
				$continue = $button;
				$sync_type = 'contact_post_type';
				$entity_id = str_replace( $replacements, '', $stop_code );
				break;
			}

		}

		// Find out if a Contact Type button has been pressed.
		if ( $stop === false AND $continue === false ) {
			foreach( $contact_types AS $contact_type_id => $stop_code ) {

				// Define replacements.
				$replacements = [ 'cai_contact_to_post_', '_stop' ];

				// Was a "Stop Sync" button pressed?
				if ( isset( $_POST[$stop_code] ) ) {
					$stop = $stop_code;
					$sync_type = 'contact_type';
					$entity_id = str_replace( $replacements, '', $stop_code );
					break;
				}

				// Was a "Sync Now" or "Continue Sync" button pressed?
				$button = str_replace( '_stop', '', $stop_code );
				if ( isset( $_POST[$button] ) ) {
					$continue = $button;
					$sync_type = 'contact_type';
					$entity_id = str_replace( $replacements, '', $stop_code );
					break;
				}

			}
		}

		// Find out if a Group "Stop Sync" button has been pressed.
		if ( $stop === false ) {
			foreach( $groups AS $group_id => $stop_code ) {

				// Define replacements.
				$replacements = [ 'cai_group_to_term_', '_stop' ];

				// Was a "Stop Sync" button pressed?
				if ( isset( $_POST[$stop_code] ) ) {
					$stop = $stop_code;
					$sync_type = 'group';
					$entity_id = str_replace( $replacements, '', $stop_code );
					break;
				}

				// Was a "Sync Now" or "Continue Sync" button pressed?
				$button = str_replace( '_stop', '', $stop_code );
				if ( isset( $_POST[$button] ) ) {
					$continue = $button;
					$sync_type = 'group';
					$entity_id = str_replace( $replacements, '', $stop_code );
					break;
				}

			}
		}

		// Find out if an Activity Post Type button has been pressed.
		foreach( $activity_post_types AS $activity_post_type => $stop_code ) {

			// Define replacements.
			$replacements = [ 'cai_post_to_activity_', '_stop' ];

			// Was a "Stop Sync" button pressed?
			if ( isset( $_POST[$stop_code] ) ) {
				$stop = $stop_code;
				$sync_type = 'activity_post_type';
				$entity_id = str_replace( $replacements, '', $stop_code );
				break;
			}

			// Was a "Sync Now" or "Continue Sync" button pressed?
			$button = str_replace( '_stop', '', $stop_code );
			if ( isset( $_POST[$button] ) ) {
				$continue = $button;
				$sync_type = 'activity_post_type';
				$entity_id = str_replace( $replacements, '', $stop_code );
				break;
			}

		}

		// Find out if an Activity Type button has been pressed.
		if ( $stop === false AND $continue === false ) {
			foreach( $activity_types AS $activity_type_id => $stop_code ) {

				// Define replacements.
				$replacements = [ 'cai_activity_to_post_', '_stop' ];

				// Was a "Stop Sync" button pressed?
				if ( isset( $_POST[$stop_code] ) ) {
					$stop = $stop_code;
					$sync_type = 'activity_type';
					$entity_id = str_replace( $replacements, '', $stop_code );
					break;
				}

				// Was a "Sync Now" or "Continue Sync" button pressed?
				$button = str_replace( '_stop', '', $stop_code );
				if ( isset( $_POST[$button] ) ) {
					$continue = $button;
					$sync_type = 'activity_type';
					$entity_id = str_replace( $replacements, '', $stop_code );
					break;
				}

			}
		}

	 	// Bail if no button was pressed.
		if ( empty( $stop ) AND empty( $continue ) ) {
			return;
		}

		// Check that we trust the source of the data.
		check_admin_referer( 'civicrm_acf_integration_sync_action', 'civicrm_acf_integration_sync_nonce' );

	 	// Was a "Stop Sync" button pressed?
		if ( ! empty( $stop ) ) {

			// Define slugs.
			$slugs = [
				'contact_post_type' => 'post_to_contact_',
				'contact_type' => 'contact_to_post_',
				'group' => 'group_to_term_',
				'activity_post_type' => 'post_to_activity_',
				'activity_type' => 'activity_to_post_',
			];

			// Build key.
			$key = $slugs[$sync_type] . $entity_id;

			// Clear offset and bail.
			$this->stepped_offset_delete( $key );
			return;

		}

		// Bail if there's no sync type.
		if ( empty( $sync_type ) ) {
			return;
		}

		// Was a Contact Post Type "Sync Now" button pressed?
		if ( $sync_type == 'contact_post_type' ) {
			$this->stepped_sync_posts_to_contacts( $entity_id );
		}

		// Was a Contact Type "Sync Now" button pressed?
		if ( $sync_type == 'contact_type' ) {
			$this->stepped_sync_contacts_to_posts( $entity_id );
		}

		// Was a Group "Sync Now" button pressed?
		if ( $sync_type == 'group' ) {
			$this->stepped_sync_groups_to_terms( $entity_id );
		}

		// Was an Activity Post Type "Sync Now" button pressed?
		if ( $sync_type == 'activity_post_type' ) {
			$this->stepped_sync_posts_to_activities( $entity_id );
		}

		// Was an Activity Type "Sync Now" button pressed?
		if ( $sync_type == 'activity_type' ) {
			$this->stepped_sync_activities_to_posts( $entity_id );
		}

	}



	/**
	 * Stepped synchronisation of WordPress Posts to CiviCRM Contacts.
	 *
	 * @since 0.6.4
	 *
	 * @param str $entity The identifier for the entity - here it's Post ID.
	 */
	public function stepped_sync_posts_to_contacts( $entity = null ) {

		// Get all mapped Post Types.
		$mapped_contact_post_types = $this->plugin->post_type->get_mapped( 'contact' );

		// Loop through them and get the data we want.
		$contact_post_types = [];
		foreach( $mapped_contact_post_types AS $contact_post_type ) {
			$contact_post_types[] = $contact_post_type->name;
		}

		// Sanitise input.
		if ( ! wp_doing_ajax() ) {
			$contact_post_type = empty( $entity ) ? '' : $entity;
		} else {
			$contact_post_type = isset( $_POST['entity_id'] ) ? trim( $_POST['entity_id'] ) : '';
		}

		// Sanity check input.
		if ( ! in_array( $contact_post_type, $contact_post_types ) ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Build key.
		$key = 'post_to_contact_' . $contact_post_type;

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'cai_' . $key, false, false );
		}

		// If we get an error.
		if ( $contact_post_type === '' OR $result === false ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Get the current offset.
		$offset = $this->stepped_offset_init( $key );

		// Construct args.
		$args = [
			'post_type' => $contact_post_type,
			'no_found_rows' => true,
			'numberposts' => $this->step_counts['contact_post_types'],
			'offset' => $offset,
		];

		// Get all posts.
		$posts = get_posts( $args );

		// If we get results.
		if ( count( $posts ) > 0 ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $posts ) < $this->step_counts['contact_post_types'] ) {
				$diff = count( $posts );
			} else {
				$diff = $this->step_counts['contact_post_types'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Remove CiviCRM callbacks to prevent recursion.
			$this->plugin->mapper->hooks_civicrm_remove();

			// Sync each Post in turn.
			foreach( $posts AS $post ) {

				// Let's make an array of params.
				$args = [
					'post_id' => $post->ID,
					'post' => $post,
					'update' => true,
				];

				/**
				 * Broadcast that the Post must be synced.
				 *
				 * Used internally to:
				 *
				 * - Update a CiviCRM Contact
				 * - Update the CiviCRM Custom Fields
				 * - Update the CiviCRM Group memberships
				 *
				 * @since 0.6.4
				 *
				 * @param array $args The array of WordPress params.
				 */
				do_action( 'civicrm_acf_integration_admin_contact_post_sync', $args );

				// Let's make an array of params.
				$args = [
					'post_id' => $post->ID,
				];

				/**
				 * Broadcast that the ACF Fields must be synced.
				 *
				 * Used internally to:
				 *
				 * - Update the CiviCRM Custom Fields
				 *
				 * @since 0.6.4
				 *
				 * @param array $args The array of CiviCRM params.
				 */
				do_action( 'civicrm_acf_integration_admin_contact_acf_fields_sync', $args );

			}

			// Reinstate CiviCRM callbacks.
			$this->plugin->mapper->hooks_civicrm_add();

			// Increment offset option.
			$this->stepped_offset_update( $key, $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			$this->stepped_offset_delete( $key );

		}

		// Send data to browser.
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of CiviCRM Contacts to WordPress Posts.
	 *
	 * @since 0.6.4
	 *
	 * @param str $entity The identifier for the entity - here it's Contact Type ID.
	 */
	public function stepped_sync_contacts_to_posts( $entity = null ) {

		// Init AJAX return.
		$data = array();

		// Sanitise input.
		if ( ! wp_doing_ajax() ) {
			$contact_type_id = is_numeric( $entity ) ? intval( $entity ) : 0;
		} else {
			$contact_type_id = isset( $_POST['entity_id'] ) ? intval( $_POST['entity_id'] ) : 0;
		}

		// Build key.
		$key = 'contact_to_post_' . $contact_type_id;

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'cai_' . $key, false, false );
		}

		// If we get an error.
		if ( $contact_type_id === 0 OR $result === false ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Get the current offset.
		$offset = $this->stepped_offset_init( $key );

		// Init query result.
		$result = [];

		// Init CiviCRM.
		if ( $this->plugin->civicrm->is_initialised() ) {

			// Get the Contact data.
			$result = $this->plugin->civicrm->contact->contacts_chunked_data_get(
				$contact_type_id,
				$offset,
				$this->step_counts['contact_types']
			);

		} else {

			// Do not allow progress.
			$result['is_error'] = 1;

		}

		// Did we get an error?
		$error = false;
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			$error = true;
		}

		// Finish sync on failure or empty result.
		if ( $error OR empty( $result['values'] ) ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			$this->stepped_offset_delete( $key );

		} else {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there fewer items than the step count?
			if ( count( $result['values'] ) < $this->step_counts['contact_types'] ) {
				$diff = count( $result['values'] );
			} else {
				$diff = $this->step_counts['contact_types'];
			}

			// Set "from" and "to" flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Remove WordPress callbacks to prevent recursion.
			$this->plugin->mapper->hooks_wordpress_remove();

			// Trigger sync for each Contact in turn.
			foreach( $result['values'] AS $contact ) {

				// Let's make an array of params.
				$args = [
					'op' => 'sync',
					'objectName' => $contact['contact_type'],
					'objectId' => $contact['contact_id'],
					'objectRef' => (object) $contact,
				];

				/**
				 * Broadcast that the Contact must be synced.
				 *
				 * Used internally to:
				 *
				 * - Update a WordPress Post
				 * - Update the WordPress Terms
				 *
				 * @since 0.6.4
				 *
				 * @param array $args The array of CiviCRM params.
				 */
				do_action( 'civicrm_acf_integration_admin_contact_sync', $args );

			}

			// Reinstate WordPress callbacks.
			$this->plugin->mapper->hooks_wordpress_add();

			// Increment offset option.
			$this->stepped_offset_update( $key, $data['to'] );

		}

		// Send data to browser.
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of CiviCRM Groups to WordPress Terms.
	 *
	 * @since 0.6.4
	 *
	 * @param str $entity The identifier for the entity - here it's Group ID.
	 */
	public function stepped_sync_groups_to_terms( $entity = null ) {

		// Init AJAX return.
		$data = array();

		// Sanitise input.
		if ( ! wp_doing_ajax() ) {
			$group_id = is_numeric( $entity ) ? intval( $entity ) : 0;
		} else {
			$group_id = isset( $_POST['entity_id'] ) ? intval( $_POST['entity_id'] ) : 0;
		}

		// Build key.
		$key = 'group_to_term_' . $group_id;

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'cai_' . $key, false, false );
		}

		// If we get an error.
		if ( $group_id === 0 OR $result === false ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Get the current offset.
		$offset = $this->stepped_offset_init( $key );

		// Init query result.
		$result = [];

		// Init CiviCRM.
		if ( $this->plugin->civicrm->is_initialised() ) {

			// Get the Group Contact data.
			$result = $this->plugin->civicrm->group->group_contacts_chunked_data_get(
				$group_id,
				$offset,
				$this->step_counts['groups']
			);

		} else {

			// Do not allow progress.
			$result['is_error'] = 1;

		}

		// Did we get an error?
		$error = false;
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			$error = true;
		}

		// Finish sync on failure or empty result.
		if ( $error OR empty( $result['values'] ) ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			$this->stepped_offset_delete( $key );

		} else {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there fewer items than the step count?
			if ( count( $result['values'] ) < $this->step_counts['groups'] ) {
				$diff = count( $result['values'] );
			} else {
				$diff = $this->step_counts['groups'];
			}

			// Set "from" and "to" flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Remove WordPress callbacks to prevent recursion.
			$this->plugin->mapper->hooks_wordpress_remove();

			// Let's make an array of params.
			$args = [
				'op' => 'sync',
				'objectName' => 'GroupContact',
				'objectId' => $group_id,
				'objectRef' => $result['values'],
			];

			/**
			 * Broadcast that the Contacts in this Group must be synced.
			 *
			 * Used internally to:
			 *
			 * - Update the WordPress Terms
			 *
			 * @since 0.6.4
			 *
			 * @param array $args The array of CiviCRM params.
			 */
			do_action( 'civicrm_acf_integration_admin_group_sync', $args );

			// Reinstate WordPress callbacks.
			$this->plugin->mapper->hooks_wordpress_add();

			// Increment offset option.
			$this->stepped_offset_update( $key, $data['to'] );

		}

		// Send data to browser.
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of WordPress Posts to CiviCRM Activities.
	 *
	 * @since 0.7.3
	 *
	 * @param str $entity The identifier for the entity - here it's Post ID.
	 */
	public function stepped_sync_posts_to_activities( $entity = null ) {

		// Get all mapped Post Types.
		$mapped_activity_post_types = $this->plugin->post_type->get_mapped( 'activity' );

		// Loop through them and get the data we want.
		$activity_post_types = [];
		foreach( $mapped_activity_post_types AS $activity_post_type ) {
			$activity_post_types[] = $activity_post_type->name;
		}

		// Sanitise input.
		if ( ! wp_doing_ajax() ) {
			$activity_post_type = empty( $entity ) ? '' : $entity;
		} else {
			$activity_post_type = isset( $_POST['entity_id'] ) ? trim( $_POST['entity_id'] ) : '';
		}

		// Sanity check input.
		if ( ! in_array( $activity_post_type, $activity_post_types ) ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Build key.
		$key = 'post_to_activity_' . $activity_post_type;

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'cai_' . $key, false, false );
		}

		// If we get an error.
		if ( $activity_post_type === '' OR $result === false ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Get the current offset.
		$offset = $this->stepped_offset_init( $key );

		// Construct args.
		$args = [
			'post_type' => $activity_post_type,
			'no_found_rows' => true,
			'numberposts' => $this->step_counts['activity_post_types'],
			'offset' => $offset,
		];

		// Get all posts.
		$posts = get_posts( $args );

		// If we get results.
		if ( count( $posts ) > 0 ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $posts ) < $this->step_counts['activity_post_types'] ) {
				$diff = count( $posts );
			} else {
				$diff = $this->step_counts['activity_post_types'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Remove CiviCRM callbacks to prevent recursion.
			$this->plugin->mapper->hooks_civicrm_remove();

			// Sync each Post in turn.
			foreach( $posts AS $post ) {

				// Let's make an array of params.
				$args = [
					'post_id' => $post->ID,
					'post' => $post,
					'update' => true,
				];

				/**
				 * Broadcast that the Post must be synced.
				 *
				 * Used internally to:
				 *
				 * - Update a CiviCRM Activity
				 * - Update the CiviCRM Custom Fields
				 *
				 * @since 0.7.3
				 *
				 * @param array $args The array of WordPress params.
				 */
				do_action( 'civicrm_acf_integration_admin_activity_post_sync', $args );

				// Let's make an array of params.
				$args = [
					'post_id' => $post->ID,
				];

				/**
				 * Broadcast that the ACF Fields must be synced.
				 *
				 * Used internally to:
				 *
				 * - Update the CiviCRM Custom Fields
				 *
				 * @since 0.7.3
				 *
				 * @param array $args The array of CiviCRM params.
				 */
				do_action( 'civicrm_acf_integration_admin_activity_acf_fields_sync', $args );

			}

			// Reinstate CiviCRM callbacks.
			$this->plugin->mapper->hooks_civicrm_add();

			// Increment offset option.
			$this->stepped_offset_update( $key, $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			$this->stepped_offset_delete( $key );

		}

		// Send data to browser.
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of CiviCRM Activities to WordPress Posts.
	 *
	 * @since 0.7.3
	 *
	 * @param str $entity The identifier for the Entity - here it's Activity Type ID.
	 */
	public function stepped_sync_activities_to_posts( $entity = null ) {

		// Init AJAX return.
		$data = array();

		// Sanitise input.
		if ( ! wp_doing_ajax() ) {
			$activity_type_id = is_numeric( $entity ) ? intval( $entity ) : 0;
		} else {
			$activity_type_id = isset( $_POST['entity_id'] ) ? intval( $_POST['entity_id'] ) : 0;
		}

		// Build key.
		$key = 'activity_to_post_' . $activity_type_id;

		// If this is an AJAX request, check security.
		$result = true;
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'cai_' . $key, false, false );
		}

		// If we get an error.
		if ( $activity_type_id === 0 OR $result === false ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Send data to browser.
			$this->send_data( $data );
			return;

		}

		// Get the current offset.
		$offset = $this->stepped_offset_init( $key );

		// Init query result.
		$result = [];

		// Init CiviCRM.
		if ( $this->plugin->civicrm->is_initialised() ) {

			// Get the Activity data.
			$result = $this->plugin->civicrm->activity->activities_chunked_data_get(
				$activity_type_id,
				$offset,
				$this->step_counts['activity_types']
			);

		} else {

			// Do not allow progress.
			$result['is_error'] = 1;

		}

		// Did we get an error?
		$error = false;
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			$error = true;
		}

		// Finish sync on failure or empty result.
		if ( $error OR empty( $result['values'] ) ) {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			$this->stepped_offset_delete( $key );

		} else {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there fewer items than the step count?
			if ( count( $result['values'] ) < $this->step_counts['activity_types'] ) {
				$diff = count( $result['values'] );
			} else {
				$diff = $this->step_counts['activity_types'];
			}

			// Set "from" and "to" flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Remove WordPress callbacks to prevent recursion.
			$this->plugin->mapper->hooks_wordpress_remove();

			// Trigger sync for each Activity in turn.
			foreach( $result['values'] AS $activity ) {

				// Let's make an array of params.
				$args = [
					'op' => 'sync',
					'objectName' => 'Activity',
					'objectId' => $activity['id'],
					'objectRef' => (object) $activity,
				];

				/**
				 * Broadcast that the Activity must be synced.
				 *
				 * Used internally to:
				 *
				 * - Update a WordPress Post
				 *
				 * @since 0.7.3
				 *
				 * @param array $args The array of CiviCRM params.
				 */
				do_action( 'civicrm_acf_integration_admin_activity_sync', $args );

			}

			// Reinstate WordPress callbacks.
			$this->plugin->mapper->hooks_wordpress_add();

			// Increment offset option.
			$this->stepped_offset_update( $key, $data['to'] );

		}

		// Send data to browser.
		$this->send_data( $data );

	}



	/**
	 * Init the synchronisation stepper.
	 *
	 * @since 0.6.4
	 *
	 * @param str $key The unique identifier for the stepper.
	 */
	public function stepped_offset_init( $key ) {

		// Construct option name.
		$option = '_cai_' . $key . '_offset';

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( $option, 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( $option, '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( $option, '0' ) );

		}

		// --<
		return $offset;

	}



	/**
	 * Update the synchronisation stepper.
	 *
	 * @since 0.6.4
	 *
	 * @param str $key The unique identifier for the stepper.
	 * @param str $to The value for the stepper.
	 */
	public function stepped_offset_update( $key, $to ) {

		// Construct option name.
		$option = '_cai_' . $key . '_offset';

		// Increment offset option.
		update_option( $option, (string) $to );

	}



	/**
	 * Delete the synchronisation stepper.
	 *
	 * @since 0.6.4
	 *
	 * @param str $key The unique identifier for the stepper.
	 */
	public function stepped_offset_delete( $key ) {

		// Construct option name.
		$option = '_cai_' . $key . '_offset';

		// Delete the option to start from the beginning.
		delete_option( $option );

	}



	/**
	 * Send JSON data to the browser.
	 *
	 * @since 0.6.4
	 *
	 * @param array $data The data to send.
	 */
	private function send_data( $data ) {

		// Is this an AJAX request?
		if ( defined( 'DOING_AJAX' ) AND DOING_AJAX ) {

			// Set reasonable headers.
			header('Content-type: text/plain');
			header("Cache-Control: no-cache");
			header("Expires: -1");

			// Echo.
			echo json_encode( $data );

			// Die.
			exit();

		}

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




<?php

/**
 * CiviCRM ACF Integration Mapping Class.
 *
 * A class that encapsulates Mapping functionality and implements UI elements
 * and data storage for linking:
 *
 * - CiviCRM Contact Types with WordPress Post Types.
 *
 * More linkages to follow.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_Mapping {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Sync mappings.
	 *
	 * At present, this plugin only maps Contacts to Posts, but the data array
	 * allows for other mappings to take place.
	 *
	 * Sync between CiviCRM Contact Types and WordPress Custom Post Types can
	 * only really be made on a site-by-site basis in Multisite, since CPTs are
	 * defined per-site and may not be available network-wide.
	 *
	 * The option for the sync mappings is therefore stored via `get_option()`
	 * family of functions rather than `get_site_option()` etc.
	 *
	 * Example array (nested array key is Contact Type ID, value is CPT name):
	 *
	 * [ 'contact-post' => [ 3 => 'post', 8 => 'student' ] ]
	 *
	 * @since 0.2
	 * @access public
	 * @var array $mappings The sync mappings.
	 */
	public $mappings = [];

	/**
	 * Post Types sync settings.
	 *
	 * The option for the sync settings is also stored via `get_option()` family
	 * of functions.
	 *
	 * Example array (key is CPT name, value is array of settings for this CPT):
	 *
	 * [
	 *   'parent' => [
	 *     'some_option' => 1,
	 *     'another_option' => 'foo',
	 *   ],
	 *   'student' => [
	 *     'some_option' => 0,
	 *     'another_option' => 'bar',
	 *   ],
	 * ]
	 *
	 * @since 0.3
	 * @access public
	 * @var array $settings The CPT sync settings.
	 */
	public $settings = [];

	/**
	 * Mappings option key.
	 *
	 * @since 0.2
	 * @access public
	 * @var str $mappings_key The Mappings option key.
	 */
	public $mappings_key = 'civicrm_acf_integration_mappings';

	/**
	 * Mapped items Settings option key.
	 *
	 * @since 0.3
	 * @access public
	 * @var str $settings_key The Settings option key.
	 */
	public $settings_key = 'civicrm_acf_integration_mapping_settings';



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

		// Store default mappings if none exist.
		if ( ! $this->option_exists( $this->mappings_key ) ) {
			$this->option_set( $this->mappings_key, $this->mappings );
		}

		// Load mappings array.
		$this->mappings = $this->option_get( $this->mappings_key, $this->mappings );

		// Store default settings if none exist.
		if ( ! $this->option_exists( $this->settings_key ) ) {
			$this->option_set( $this->settings_key, $this->settings );
		}

		// Load settings array.
		$this->settings = $this->option_get( $this->settings_key, $this->settings_key );

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.2
	 */
	public function register_hooks() {

		// Register template directory for form amends.
		add_action( 'civicrm_config', [ $this, 'register_form_directory' ], 10 );

		// Modify CiviCRM Add/Edit Contact Type form.
		add_action( 'civicrm_buildForm', [ $this, 'form_contact_type_build' ], 10, 2 );

		// Intercept CiviCRM Add/Edit Contact Type form submission process.
		add_action( 'civicrm_postProcess', [ $this, 'form_contact_type_process' ], 10, 2 );

		// Intercept CiviCRM Add/Edit Contact Type postSave hook.
		add_action( 'civicrm_postSave_civicrm_contact_type', [ $this, 'form_contact_type_postSave' ], 10, 1 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Register directory that CiviCRM searches in for our form template file.
	 *
	 * @since 0.2
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_form_directory( &$config ) {

		// Kick out if no CiviCRM.
		if ( ! $this->plugin->civicrm->is_initialised() ) {
			return;
		}

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Define our custom path.
		$custom_path = CIVICRM_ACF_INTEGRATION_PATH . 'assets/templates/civicrm';

		// Add our custom template directory.
		$template->addTemplateDir( $custom_path );

		// Register template directory.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}



	/**
	 * Enable a WordPress Custom Post Type to be linked to a CiviCRM Contact Type.
	 *
	 * @since 0.2
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_contact_type_build( $formName, &$form ) {

		// Is this the Contact Type edit form?
		if ( $formName != 'CRM_Admin_Form_ContactType' ) return;

		// Get CiviCRM Contact Type.
		$contact_type = $form->getVar( '_values' );

		// Determine form mode by whether we have a Contact Type.
		if ( isset( $contact_type ) AND ! empty( $contact_type ) ) {
			$mode = 'edit';
		} else {
			$mode = 'create';
		}

		// Build options array.
		$options = [
			'' => '- ' . __( 'No Synced Post Type', 'civicrm-acf-integration' ) . ' -',
		];

		// Maybe assign Contact Type ID.
		$contact_type_id = 0;
		if ( $mode === 'edit' ) {
			$contact_type_id = (int) $contact_type['id'];
		}

		// Get all available Post Types for this Contact Type.
		$post_types = $this->plugin->post_type->post_types_get_for_contact_type( $contact_type_id );

		// Add select option for those which are public.
		if ( count( $post_types ) > 0 ) {
			foreach( $post_types AS $post_type ) {

				/*
				 * Exclude built-in WordPress private Post Types.
				 *
				 * - nav_menu_item
				 * - revision
				 * - customize_changeset
				 * - etc, etc
				 *
				 * ACF does not support these.
				 */
				if ( $post_type->_builtin AND ! $post_type->public ) continue;

				// ACF does not support the built-in WordPress Media Post Type.
				if ( $post_type->name == 'attachment' ) continue;

				// Add anything else.
				$options[esc_attr( $post_type->name )] = esc_html( $post_type->labels->singular_name );

			}
		}

		// Add Post Types dropdown.
		$cpt_select = $form->add(
			'select',
			'civicrm_acf_integration_cpt',
			__( 'Synced Post Type', 'civicrm-acf-integration' ),
			$options,
			FALSE,
			[]
		);

		// Add a description.
		//$form->assign( 'civicrm_acf_integration_cpt_desc', __( 'Blah', 'civicrm-acf-integration' ) );

		// Amend form in edit mode.
		if ( $mode === 'edit' ) {

			// Get existing CPT.
			$cpt_name = $this->mapping_for_contact_type_get( $contact_type['id'] );

			// If we have a mapped CPT.
			if ( $cpt_name !== false ) {

				// Set selected value of our dropdown.
				$cpt_select->setSelected( $cpt_name );

				// Get CPT settings.
				$cpt_settings = $this->setting_get( $cpt_name );

			}

			// Do we allow changes to be made?
			//$cpt_select->freeze();

		}

		// Insert template block into the page.
		CRM_Core_Region::instance('page-body')->add([
			'template' => 'civicrm-acf-integration-contact-type-cpt.tpl'
		]);

	}



	/**
	 * Callback for the Add/Edit Contact Type form's postSave hook.
	 *
	 * Since neither "civicrm_pre" nor "civicrm_post" fire for this CiviCRM
	 * entity, we grab the saved ID here, store it, then use it in the form's
	 * postProcess callback.
	 *
	 * @see form_contact_type_process()
	 *
	 * @since 0.2
	 *
	 * @param object $objectRef The DAO object.
	 */
	public function form_contact_type_postSave( $objectRef ) {

		// Bail if not Contact Type save operation.
		if ( ! ( $objectRef instanceof CRM_Contact_DAO_ContactType ) ) {
			return;
		}

		// Bail if no Contact Type ID.
		if ( empty( $objectRef->id ) ) {
			return;
		}

		// Store locally for use in form_contact_type_process().
		$this->saved_contact_type_id = $objectRef->id;

	}



	/**
	 * Callback for the Add/Edit Contact Type form's postProcess hook.
	 *
	 * Neither "civicrm_pre" nor "civicrm_post" fire for this CiviCRM entity,
	 * so the link between the Contact Type and the WordPress Post Type must be
	 * made here.
	 *
	 * @since 0.2
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_contact_type_process( $formName, &$form ) {

		// Bail if not Contact Type edit form.
		if ( ! ( $form instanceof CRM_Admin_Form_ContactType ) ) {
			return;
		}

		// Grab Contact Type ID from form.
		$contact_type_id = $form->getVar( '_id' );

		// Get Contact Type ID if not present in the form.
		if ( empty( $contact_type_id ) ) {
			if ( isset( $this->saved_contact_type_id ) ) {
				$contact_type_id = $this->saved_contact_type_id;
			}
		}

		// Grab submitted values.
		$values = $form->getSubmitValues();

		// Inspect our select value.
		if ( empty( $values['civicrm_acf_integration_cpt'] ) ) {

			// Remove (or ignore) linkage.
			$this->mapping_for_contact_type_remove( $contact_type_id );

			/**
			 * Broadcast that the mapping has been removed.
			 *
			 * @since 0.4.5
			 *
			 * @param int $contact_type_id The removed Contact Type ID.
			 * @param array $values The form values.
			 */
			do_action( 'civicrm_acf_integration_mapping_removed', $contact_type_id, $values );

		} else {

			// Let's grab the Post Type.
			$post_type = trim( $values['civicrm_acf_integration_cpt'] );

			// Add/Update linkage.
			$this->mapping_for_contact_type_update( $contact_type_id, $post_type );

			/**
			 * Broadcast that the mapping has been updated.
			 *
			 * @since 0.4.5
			 *
			 * @param int $contact_type_id The updated Contact Type ID.
			 * @param str $post_type The updated Post Type name.
			 * @param array $values The form values.
			 */
			do_action( 'civicrm_acf_integration_mapping_edited', $contact_type_id, $post_type, $values );

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Upgrade the mappings array.
	 *
	 * @since 0.5.1
	 */
	public function mappings_upgrade() {

		// Load mappings array.
		$mappings = $this->option_get( $this->mappings_key, [] );

		// Maybe upgrade the array.
		if ( ! empty( $mappings ) AND ! isset( $mappings['contact-post'] ) ) {

			// Make legacy array a sub-array.
			$mappings_new = [ 'contact-post' => $mappings ];
			$this->option_set( $this->mappings_key, $mappings_new );
			$this->mappings = $mappings_new;

		}

	}



	/**
	 * Delete the mappings array.
	 *
	 * @since 0.5.1
	 */
	public function mappings_delete() {

		// Delete the mappings option.
		$this->option_delete( $this->mappings_key );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all Contact Type to Post Type mappings.
	 *
	 * @since 0.2
	 *
	 * @return array $mappings The array of mappings.
	 */
	public function mappings_for_contact_types_get() {

		// --<
		return $this->mappings['contact-post'];

	}



	/**
	 * Get the WordPress Post Type for a CiviCRM Contact Type.
	 *
	 * @since 0.2
	 *
	 * @param int $contact_type_id The numeric ID of the Contact Type.
	 * @return str|bool $cpt_name The name of the Post Type or false if none exists.
	 */
	public function mapping_for_contact_type_get( $contact_type_id ) {

		// Init as false.
		$cpt_name = false;

		// Overwrite if a mapping exists.
		if ( isset( $this->mappings['contact-post'][$contact_type_id] ) ) {
			$cpt_name = $this->mappings['contact-post'][$contact_type_id];
		}

		// --<
		return $cpt_name;

	}



	/**
	 * Add or update the link between a CiviCRM Contact Type and a WordPress Post Type.
	 *
	 * @since 0.2
	 *
	 * @param int $contact_type_id The numeric ID of the Contact Type.
	 * @param str $cpt_name The name of the WordPress Post Type.
	 * @return bool $success Whether or not the operation was successful.
	 */
	public function mapping_for_contact_type_update( $contact_type_id, $cpt_name ) {

		// Overwrite (or create) mapping.
		$this->mappings['contact-post'][$contact_type_id] = $cpt_name;

		// Update option.
		$this->option_set( $this->mappings_key, $this->mappings );

	}



	/**
	 * Maybe delete the link between a CiviCRM Contact Type and a WordPress Post Type.
	 *
	 * @since 0.2
	 *
	 * @param int $contact_type_id The numeric ID of the Contact Type.
	 * @return bool $success Whether or not the operation was successful.
	 */
	public function mapping_for_contact_type_remove( $contact_type_id ) {

		// If a mapping exists.
		if ( isset( $this->mappings['contact-post'][$contact_type_id] ) ) {

			// We also need to remove the setting.
			$this->setting_remove( $this->mappings['contact-post'][$contact_type_id] );

			// Finally, remove mapping.
			unset( $this->mappings['contact-post'][$contact_type_id] );

		}

		// Update option.
		$this->option_set( $this->mappings_key, $this->mappings );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all mapped settings.
	 *
	 * @since 0.3
	 *
	 * @return array $settings The array of settings.
	 */
	public function settings_get() {

		// --<
		return $this->settings;

	}



	/**
	 * Delete the settings array.
	 *
	 * @since 0.5.1
	 */
	public function settings_delete() {

		// Delete the settings option.
		$this->option_delete( $this->settings_key );

	}



	/**
	 * Get the mapped settings for a WordPress Post Type.
	 *
	 * @since 0.2
	 *
	 * @param str $post_type The name of the Post Type.
	 * @return array|bool $setting The array of settings for the Post Type, or false if none exist.
	 */
	public function setting_get( $post_type ) {

		// Init as false.
		$setting = false;

		// Overwrite if a setting exists.
		if ( isset( $this->settings[$post_type] ) ) {
			$setting = $this->settings[$post_type];
		}

		// --<
		return $setting;

	}



	/**
	 * Add or update the mapped settings for a WordPress Post Type.
	 *
	 * @since 0.3
	 *
	 * @param str $post_type The name of the Post Type.
	 * @param array $data The settings data for the Post Type.
	 * @return bool $success Whether or not the operation was successful.
	 */
	public function setting_update( $post_type, $data ) {

		// Overwrite (or create) setting.
		$this->settings[$post_type] = $data;

		// Update option.
		$this->option_set( $this->settings_key, $this->settings );

	}



	/**
	 * Maybe delete the mapped settings for a WordPress Post Type.
	 *
	 * @since 0.3
	 *
	 * @param str $post_type The name of the Post Type.
	 * @return bool $success Whether or not the operation was successful.
	 */
	public function setting_remove( $post_type ) {

		// If a setting exists, delete it.
		if ( isset( $this->settings[$post_type] ) ) {
			unset( $this->settings[$post_type] );
		}

		// Update option.
		$this->option_set( $this->settings_key, $this->settings );

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
	public function option_exists( $option_name = '' ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to option_exists()', 'civicrm-acf-integration' ) );
		}

		// Test by getting option with unlikely default.
		if ( $this->option_get( $option_name, 'fenfgehgefdfdjgrkj' ) == 'fenfgehgefdfdjgrkj' ) {
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
	public function option_get( $option_name = '', $default = false ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to option_get()', 'civicrm-acf-integration' ) );
		}

		// Get option.
		$value = get_option( $option_name, $default );

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
	public function option_set( $option_name = '', $value = '' ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to option_set()', 'civicrm-acf-integration' ) );
		}

		// Update option.
		return update_option( $option_name, $value );

	}



	/**
	 * Delete a specified option.
	 *
	 * @since 0.2
	 *
	 * @param str $option_name The name of the option.
	 * @return bool $success True if the option was successfully deleted.
	 */
	public function option_delete( $option_name = '' ) {

		// Test for empty.
		if ( $option_name == '' ) {
			die( __( 'You must supply an option to option_delete()', 'civicrm-acf-integration' ) );
		}

		// Delete option.
		return delete_option( $option_name );

	}



} // Class ends.




<?php

/**
 * CiviCRM ACF Integration ACF Field Group Class.
 *
 * A class that encapsulates ACF Field Group functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_ACF_Field_Group {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.3
	 * @access public
	 * @var object $acf The parent object.
	 */
	public $acf;

	/**
	 * "Placeholder" Field Group key.
	 *
	 * @since 0.3
	 * @access public
	 * @var str $placeholder_group The key of the Placeholder field group.
	 */
	public $placeholder_group = 'group_cacf_placeholder_group';



	/**
	 * Constructor.
	 *
	 * @since 0.3
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to parent.
		$this->acf = $parent;

		// Init when this plugin is loaded.
		add_action( 'civicrm_acf_integration_acf_loaded', [ $this, 'register_hooks' ] );

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.3
	 */
	public function register_hooks() {

		// Add Field Groups.
		//add_action( 'acf/init', [ $this, 'field_groups_add' ] );

		// Add "CiviCRM Contact Type" Field to Field Group settings.
		add_action( 'acf/render_field_group_settings', [ $this, 'settings_add' ] );
		add_action( 'acf/validate_field_group', [ $this, 'setting_validate' ] );

		// Update mapped Fields when Field Group is saved.
		add_action( 'acf/update_field_group', [ $this, 'field_group_updated' ] );

		// Inspect load_field_group hook.
		//add_action( 'acf/load_field_group', [ $this, 'field_group_modify' ] );

	}



	/**
	 * Getter method for the "Placeholder Group" Field Group key.
	 *
	 * @since 0.4.5
	 *
	 * @return array $placeholder_group The "Placeholder Group" Field Group key.
	 */
	public function placeholder_group_get() {

		// --<
		return $this->placeholder_group;

	}



	// -------------------------------------------------------------------------



	/**
	 * Add ACF Field Groups.
	 *
	 * @since 0.3
	 */
	public function field_groups_add() {

		/*
		// Attach the field group to the built-in 'post' Post Type.
		$field_group_location = [[[
			'param' => 'post_type',
			'operator' => '==',
			'value' => 'post',
		]]];

		// Define field group.
		$field_group = [
			'active' => false,
			'key' => $this->placeholder_group_get(),
			'title' => __( 'Placeholder Field Group', 'civicrm-acf-integration' ),
			'fields' => [],
			'location' => $field_group_location,
		];

		// Now add the group.
		acf_add_local_field_group( $field_group );
		*/

	}



	// -------------------------------------------------------------------------



	/**
	 * Add Setting to Field Group Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field_group The field group data array.
	 */
	public function settings_add( $field_group ) {

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group' => $field_group,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Get the "CiviCRM Contact Type" field.
		$field = $this->acf->field->civicrm_contact_type_get();

		// Get field key.
		$field_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Add setting.
		if ( isset( $field_group[$field_key] ) ) {
			$field['value'] = $field_group[$field_key];
		} else {
			$field['value'] = '';
		}

		// Now add it.
		acf_render_field_wrap( $field );

	}



	/**
	 * Validate Field Group Settings.
	 *
	 * @since 0.3
	 *
	 * @param array $field_group The existing field group data array.
	 * @return array $field_group The modified field group data array.
	 */
	public function setting_validate( $field_group ) {

		// Bail if already invalid.
		if ( ! $field_group['_valid'] ) {
			return $field_group;
		}

		// Bail if it's our placeholder field group.
		if ( $field_group['key'] == $this->placeholder_group_get() ) {
			return $field_group;
		}

		/*
		// Bail if field group is not modified.
		$field_key = $this->plugin->civicrm->contact_type->acf_field_key_get();
		if ( ! isset( $field_group[$field_key] ) ) {
			return $field_group;
		}
		*/

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group BEFORE' => $field_group,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Get field key.
		$field_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Get our setting value.
		$setting = acf_maybe_get_POST( $field_key );

		// Maybe apply setting from POST to Field Group.
		if ( ! empty( $setting ) ) {
			$field_group[$field_key] = trim( $setting );
		}

		// Validate our setting.
		if ( ! empty( $field_group[$field_key] ) ) {
			$field_group[$field_key] = $field_group[$field_key];
		} else {
			$field_group[$field_key] = '';
		}

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group AFTER' => $field_group,
			//'backtrace' => $trace,
		), true ) );
		*/

		// --<
		return $field_group;

	}



	// -------------------------------------------------------------------------



	/**
	 * Hook into Field Group updates.
	 *
	 * We need to force ACF to save the Fields in a Field Group because ACF only
	 * saves Fields that have been changed in the Field Group UI. Where the
	 * "choices" are dynamically added via "acf/load_field", ACF does not detect
	 * that the "Choices" have actually been overridden. This means we have to
	 * load those settings every time a Field is loaded, whether on the Field
	 * Group "Edit" page or on a Post "Edit" page.
	 *
	 * Loading every time "acf/load_field" fires works just fine, but we can
	 * reduce the database calls by saving the values from CiviCRM in the Field
	 * itself. The downside to this is that if changes are made to Custom Fields
	 * in CiviCRM, then the relevant Field Group(s) have to be re-saved in ACF.
	 *
	 * It's possible we can work around this by hooking into events that CiviCRM
	 * fires when a Custom Field's settings are updated.
	 *
	 * @since 0.3
	 *
	 * @param array $field_group The existing field group data array.
	 * @return array $field_group The modified field group data array.
	 */
	public function field_group_updated( $field_group ) {

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group' => $field_group,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Get field key.
		$field_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Bail if this Field Group is not mapped.
		if ( empty( $field_group[$field_key] ) ) {
			return $field_group;
		}

		// Get all the Fields in this Field Group.
	    $fields = acf_get_fields( $field_group );

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group' => $field_group,
			'field_key' => $field_key,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Bail if there aren't any.
		if ( empty( $fields ) ) {
			return $field_group;
		}

		// Loop through Fields and save them.
		foreach( $fields AS $field ) {

			// Skip if the CiviCRM Field key isn't there or isn't populated.
			$key = $this->plugin->civicrm->contact->acf_field_key_get();
			if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
				continue;
			}

			// Build method name.
			$method = $field['type'] . '_setting_modify';

			// Skip if not callable.
			if ( ! is_callable( [ $this->acf->field, $method ] ) ) {
				continue;
			}

			/*
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'field-BEFORE' => $field,
				//'backtrace' => $trace,
			), true ) );
			*/

			// Run Field through associated method.
			$field = $this->acf->field->$method( $field );

			/*
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'field-AFTER' => $field,
				//'backtrace' => $trace,
			), true ) );
			*/

			// Save the Field.
			acf_update_field( $field );

		}

		// --<
		return $field_group;

	}



	/**
	 * Modify Field Groups.
	 *
	 * @since 0.3
	 *
	 * @param array $field_group The existing field group data array.
	 * @return array $field_group The modified field group data array.
	 */
	public function field_group_modify( $field_group ) {

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group' => $field_group,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Get field key.
		$field_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Add our setting.
		$field_group[$field_key] = '';

		// --<
		return $field_group;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get Field Group from Field data.
	 *
	 * @since 0.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $field_group The ACF Field Group data array.
	 */
	public function get_for_field( $field ) {

		// Get field parent safely.
		$field_parent = acf_maybe_get( $field, 'parent' );

		// Bail if there's no field parent.
		if ( ! $field_parent ) {
			return false;
		}

		// Return early if this field has no ancestors.
		$field_ancestors = acf_get_field_ancestors( $field );
		if ( ! $field_ancestors ) {
			return acf_get_field_group( $field_parent );
		}

		// It has ancestors - get top-most field's field group.
		$topmost_field = array_pop( $field_ancestors );
		$field_data = acf_get_field( $topmost_field );
		$field_group = acf_get_field_group( $field_data['parent'] );

		// --<
		return $field_group;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get Field Group from CiviCRM Custom Group ID.
	 *
	 * @since 0.3
	 *
	 * @param int $custom_group_id The numeric ID of the CiviCRM Custom Group.
	 * @return array|bool $field_group The Field Group array, or false on failure.
	 */
	public function get_for_custom_group( $custom_group_id ) {

		// Init Field Group ID.
		$field_group_id = false;

		return;

		// Get field group.
		$field_group = acf_get_field_group( $field_group_id );

		// --<
		return $field_group;

	}



} // Class ends.




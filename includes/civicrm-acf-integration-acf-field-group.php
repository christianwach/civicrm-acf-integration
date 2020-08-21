<?php
/**
 * ACF Field Group Class.
 *
 * Handles ACF Field Group functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration ACF Field Group Class.
 *
 * A class that encapsulates ACF Field Group functionality.
 *
 * @since 0.3
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

		// Update mapped Fields when Field Group is saved.
		add_action( 'acf/update_field_group', [ $this, 'field_group_updated' ] );

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

		// Init mapped flag.
		$mapped = false;

		/**
		 * Query if this Field Group is mapped.
		 *
		 * This filter sends out a request for other classes to respond with a
		 * Boolean if they detect that this Field Group maps to an Entity Type
		 * that they are responsible for.
		 *
		 * Internally, this is used by:
		 *
		 * @see CiviCRM_ACF_Integration_CiviCRM_Contact::query_field_group_mapped()
		 *
		 * @since 0.5.1
		 *
		 * @param bool $mapped False, since we're asking for a mapping.
		 * @param array $field_group The array of ACF Field Group data.
		 * @param bool $mapped True if the Field Group is mapped, or false if not mapped.
		 */
		$mapped = apply_filters( 'civicrm_acf_integration_query_field_group_mapped', $mapped, $field_group );

		// Bail if this Field Group is not mapped.
		if ( $mapped === false ) {
			return $field_group;
		}

		// Get all the Fields in this Field Group.
		$fields = acf_get_fields( $field_group );

		// Bail if there aren't any.
		if ( empty( $fields ) ) {
			return $field_group;
		}

		// Loop through Fields and save them.
		foreach( $fields AS $field ) {

			// Skip if the CiviCRM Field key isn't there or isn't populated.
			$key = $this->plugin->civicrm->acf_field_key_get();
			if ( ! array_key_exists( $key, $field ) OR empty( $field[$key] ) ) {
				continue;
			}

			// Build method name.
			$method = $field['type'] . '_setting_modify';

			// Skip if not callable.
			if ( ! is_callable( [ $this->acf->field, $method ] ) ) {
				continue;
			}

			// Run Field through associated method.
			$field = $this->acf->field->$method( $field );

			// Save the Field.
			acf_update_field( $field );

		}

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



	// -------------------------------------------------------------------------



	/**
	 * Check if a Field Group has been mapped to a WordPress Entity.
	 *
	 * This method is an adapted version of acf_get_field_group_visibility().
	 * At present, we just send an array of params containing the Post Type to
	 * check if the Field Group is visible on that Post Type. For example:
	 *
	 * $params = [ 'post_type' => 'student' ]
	 *
	 * The params match those passed to acf_get_field_groups() in ACF. Search
	 * the ACF code to see what kinds of keys and values are possible.
	 *
	 * @see acf_get_field_groups()
	 * @see acf_get_field_group_visibility()
	 *
	 * @since 0.5.1
	 *
	 * @param array $field_group The Field Group to check.
	 * @param array $params The params to query by.
	 * @return bool True if the Field Group has been mapped to the Event Post Type, or false otherwise.
	 */
	public function is_visible( $field_group, $params ) {

		// Bail if no location rules exist.
		if ( empty( $field_group['location'] ) ) {
			return false;
		}

		// Loop through location groups.
		foreach( $field_group['location'] AS $group ) {

			// Skip group if it has no rules.
			if ( empty( $group ) ) {
				continue;
			}

			// Loop over the rules and determine if "post type" rules match.
			$match_group = true;
			foreach( $group AS $rule ) {

				// Skip any rules which do not reference the "post type".
				if ( $rule['param'] != 'post_type' ) {
					continue;
				}

				// Test the "post type" rule.
				if ( ! acf_match_location_rule( $rule, $params, $field_group ) ) {
					$match_group = false;
					break;
				}

			}

			// If this group matches, it is a visible Field Group.
			if ( $match_group ) {
				return true;
			}

		}

		// Fallback.
		return false;

	}



} // Class ends.




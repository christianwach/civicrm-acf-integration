<?php
/**
 * CiviCRM Contact Type Class.
 *
 * Handles CiviCRM Contact Type functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Contact Type Class.
 *
 * A class that encapsulates CiviCRM Contact Type functionality.
 *
 * @since 0.1
 */
class CiviCRM_ACF_Integration_CiviCRM_Contact_Type {

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
	 * @since 0.1
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Contact Type" field key.
	 *
	 * @since 0.3
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Contact Type" field.
	 */
	public $acf_field_key = 'field_cacf_civicrm_contact_type';

	/**
	 * Top-level Contact Types which can be mapped.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var array $top_level_types The top level CiviCRM Contact Types.
	 */
	public $top_level_types = [
		'Individual',
		'Household',
		'Organization',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to parent.
		$this->civicrm = $parent;

		// Init when the CiviCRM object is loaded.
		add_action( 'civicrm_acf_integration_civicrm_loaded', [ $this, 'register_hooks' ] );

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



	// -------------------------------------------------------------------------



	/**
	 * Get all top-level CiviCRM contact types.
	 *
	 * @since 0.2.1
	 *
	 * @return array $top_level_types The top level CiviCRM Contact Types.
	 */
	public function types_get_top_level() {

		// --<
		return $this->top_level_types;

	}



	/**
	 * Get all CiviCRM contact types, nested by parent.
	 *
	 * CiviCRM only allows one level of nesting, so we can parse the results
	 * into a nested array to return.
	 *
	 * @since 0.1
	 *
	 * @return array $nested The nested CiviCRM contact types.
	 */
	public function types_get_nested() {

		// Only do this once.
		static $nested;
		if ( isset( $nested ) ) {
			return $nested;
		}

		// Init return.
		$nested = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $nested;
		}

		// Define params to get all contact types.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'is_active' => 1,
			'options' => [
				'limit' => '0', // No limit.
			],
		];

		// Call API.
		$result = civicrm_api( 'ContactType', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $nested;
		}

		// Populate contact types array.
		$contact_types = [];
		if ( isset( $result['values'] ) AND count( $result['values'] ) > 0 ) {
			$contact_types = $result['values'];
		}

		// let's get the top level types
		$top_level = [];
		foreach( $contact_types AS $contact_type ) {
			if ( ! isset( $contact_type['parent_id'] ) ) {
				$top_level[] = $contact_type;
			}
		}

		// Build a nested array
		foreach( $top_level AS $item ) {
			$item['children'] = [];
			foreach( $contact_types AS $contact_type ) {
				if ( isset( $contact_type['parent_id'] ) AND $contact_type['parent_id'] == $item['id'] ) {
					$item['children'][] = $contact_type;
				}
			}
			$nested[] = $item;
		}

		// --<
		return $nested;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Contact Type data for a given ID or name.
	 *
	 * @since 0.2.1
	 *
	 * @param str|int $contact_type The name of the CiviCRM Contact Type to query.
	 * @param str $mode The param to query by: 'name' or 'id'.
	 * @return array|bool $contact_type_data An array of Contact Type data, or false on failure.
	 */
	public function get_data( $contact_type, $mode = 'name' ) {

		// Only do this once per Contact Type and mode.
		static $pseudocache;
		if ( isset( $pseudocache[$mode][$contact_type] ) ) {
			return $pseudocache[$mode][$contact_type];
		}

		// Init return.
		$contact_type_data = false;

		// Bail if we have no Contact Type.
		if ( empty( $contact_type ) ) {
			return $contact_type_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_type_data;
		}

		// Define params to get queried Contact Type.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Add param to query by.
		if ( $mode == 'name' ) {
			$params['name'] = $contact_type;
		} elseif ( $mode == 'id' ) {
			$params['id'] = $contact_type;
		}

		// Call the API.
		$result = civicrm_api( 'ContactType', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_type_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_type_data;
		}

		// The result set should contain only one item.
		$contact_type_data = array_pop( $result['values'] );

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$mode][$contact_type] ) ) {
			$pseudocache[$mode][$contact_type] = $contact_type_data;
		}

		// --<
		return $contact_type_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Contact Type and Sub-type for a given Contact Type.
	 *
	 * CiviCRM only allows one level of nesting, so we don't need to recurse -
	 * we can simply re-query if there is a 'parent_id'.
	 *
	 * @since 0.1
	 *
	 * @param str $contact_type The name of the CiviCRM Contact Type to query.
	 * @param str $mode The param to query by: 'name' or 'id'.
	 * @return array|bool $types An array of type and sub-type, or false on failure.
	 */
	public function hierarchy_get( $contact_type, $mode = 'name' ) {

		// Only do this once per Contact Type and mode.
		static $pseudocache;
		if ( isset( $pseudocache[$mode][$contact_type] ) ) {
			return $pseudocache[$mode][$contact_type];
		}

		// Init return.
		$types = false;

		// Get data for the queried Contact Type.
		$contact_type_data = $this->get_data( $contact_type, $mode );

		// Bail if we didn't get any.
		if ( $contact_type_data === false ) {
			return $types;
		}

		// Overwrite with name when passing in an ID.
		if ( $mode == 'id' ) {
			$contact_type_name = $contact_type_data['name'];
		} else {
			$contact_type_name = $contact_type;
		}

		// Assume it's the top level type.
		$top_level_type = $contact_type_data['name'];

		// If there's a parent ID, re-query.
		if ( ! empty( $contact_type_data['parent_id'] ) ) {

			// Define params to get top-level Contact Type.
			$params = [
				'version' => 3,
				'sequential' => 1,
				'id' => $contact_type_data['parent_id'],
			];

			// Call the API.
			$result = civicrm_api( 'ContactType', 'getsingle', $params );

			// Bail if there's an error.
			if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
				return $types;
			}

			// Assign top level type.
			$top_level_type = $result['name'];

		}

		// Clear subtype if identical to type.
		if ( $contact_type_name == $top_level_type ) {
			$contact_subtype = '';
			$contact_type_name = $top_level_type;
		} else {
			$contact_subtype = $contact_type_data['name'];
			$contact_type_name = $top_level_type;
		}

		// Build types.
		$types = [ 'type' => $contact_type_name, 'subtype' => $contact_subtype ];

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$mode][$contact_type] ) ) {
			$pseudocache[$mode][$contact_type] = $types;
		}

		// --<
		return $types;

	}



	/**
	 * Get the CiviCRM Contact Type and Sub-type for a given Contact Type ID.
	 *
	 * @since 0.2
	 *
	 * @param str $contact_type_id The numeric ID of the CiviCRM Contact Type.
	 * @return array $types An associative array populated with parent type and sub-type.
	 */
	public function hierarchy_get_by_id( $contact_type_id ) {

		// Pass through.
		$types = $this->hierarchy_get( $contact_type_id, 'id' );

		// --<
		return $types;

	}



	/**
	 * Get the Contact Type hierarchy for a given a Contact.
	 *
	 * This method assumes that a Contact is of a single sub-type. This may not
	 * be the case.
	 *
	 * @since 0.2
	 *
	 * @param array|obj $contact The Contact data.
	 * @return int|bool $is_mapped The ID of the WordPress Post if the Contact is mapped, false otherwise.
	 */
	public function hierarchy_get_for_contact( $contact ) {

		// Maybe cast Contact data as array.
		if ( is_object( $contact ) ) {
			$contact = (array) $contact;
		}

		// Grab the top level Contact Type for this Contact.
		$contact_type = $contact['contact_type'];

		// TODO: Handle Contacts with multiple Contact Sub-types.

		// Find the lowest level Contact Type for this Contact.
		$contact_sub_type = '';
		if ( ! empty( $contact['contact_sub_type'] ) ) {
			if ( is_array( $contact['contact_sub_type'] ) ) {
				$contact_sub_type = array_pop( $contact['contact_sub_type'] );
			} else {
				if ( false !== strpos( $contact['contact_sub_type'], CRM_Core_DAO::VALUE_SEPARATOR ) ) {
					$types = CRM_Utils_Array::explodePadded( $contact['contact_sub_type'] );
					$contact_sub_type = array_pop( $types );
				} else {
					$contact_sub_type = $contact['contact_sub_type'];
				}
			}
		}

		// Build types.
		$types = [ 'type' => $contact_type, 'subtype' => $contact_sub_type ];

		// --<
		return $types;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Contact Type hierarchy that is mapped to a Post Type.
	 *
	 * @since 0.2
	 *
	 * @param str|bool $post_type_name The name of Post Type.
	 * @return array $types An associative array populated with parent type and sub-type.
	 */
	public function hierarchy_get_for_post_type( $post_type_name ) {

		// Init return.
		$types = false;

		// Get the mapped Contact Type ID.
		$contact_type_id = $this->id_get_for_post_type( $post_type_name );

		// Bail on failure.
		if ( $contact_type_id === false ) {
			return $types;
		}

		// Get the array of types.
		$types = $this->hierarchy_get_by_id( $contact_type_id );

		// --<
		return $types;

	}



	/**
	 * Get the Contact Type that is mapped to a Post Type.
	 *
	 * @since 0.2
	 *
	 * @param str $post_type_name The name of Post Type.
	 * @return int|bool $contact_type_id The numeric ID of the Contact Type, or false if not mapped.
	 */
	public function id_get_for_post_type( $post_type_name ) {

		// Init return.
		$contact_type_id = false;

		// Get mappings and flip.
		$mappings = $this->plugin->mapping->mappings_for_contact_types_get();
		$mappings = array_flip( $mappings );

		// Overwrite the Contact Type ID if there is a value.
		if ( isset( $mappings[$post_type_name] ) ) {
			$contact_type_id = $mappings[$post_type_name];
		}

		// --<
		return $contact_type_id;

	}



	/**
	 * Check if a Contact Type is mapped to a Post Type.
	 *
	 * @since 0.2
	 *
	 * @param int|str|array $contact_type The "ID", "name" or "hierarchy" of the Contact Type.
	 * @return str|bool $is_linked The name of the Post Type, or false otherwise.
	 */
	public function is_mapped( $contact_type ) {

		// Assume not.
		$is_mapped = false;

		// Parse the input when it's an array.
		if ( is_array( $contact_type ) ) {

			// Check if it's a top level Contact Type.
			if ( empty( $contact_type['subtype'] ) ) {
				$contact_type = $contact_type['type'];
			} else {
				$contact_type = $contact_type['subtype'];
			}

		}

		// Parse the input when it's an integer.
		if ( is_numeric( $contact_type ) ) {

			// Assign the numeric ID.
			$contact_type_id = $contact_type = intval( $contact_type );

		}

		// Parse the input when it's a string.
		if ( is_string( $contact_type ) ) {

			// Get data for the queried Contact Type.
			$contact_type_data = $this->get_data( $contact_type, 'name' );

			// Bail if we didn't get any.
			if ( $contact_type_data === false ) {
				return $is_mapped;
			}

			// Assign the numeric ID.
			$contact_type_id = $contact_type_data['id'];

		}

		// Get mapped Post Types.
		$mapped_post_types = $this->plugin->mapping->mappings_for_contact_types_get();

		// Check presence in mappings.
		if ( isset( $mapped_post_types[$contact_type_id] ) ) {
			$is_mapped = $mapped_post_types[$contact_type_id];
		}

		// --<
		return $is_mapped;

	}



	// -------------------------------------------------------------------------



	/**
	 * Getter method for the "CiviCRM Field" key.
	 *
	 * @since 0.4.1
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Contact Type" in the ACF Field Group data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



} // Class ends.




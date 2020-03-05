<?php

/**
 * CiviCRM ACF Integration CiviCRM Relationships Class.
 *
 * A class that encapsulates CiviCRM Relationships functionality.
 *
 * There are oddities in CiviCRM's relationships, particularly the "Employer Of"
 * relationship - which is both a Relationship and a "Contact Field". The ID of
 * the "Current Employer" Contact may be present in the values returned for a
 * "Contact" in the "current_employer" field and can be set via the API by
 * populating the "employer_id" field. I'm not sure how to handle this yet.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Relationship extends CiviCRM_ACF_Integration_CiviCRM_Base {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Relationship" field key in the ACF Field data.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var str $acf_field_key The key of the "CiviCRM Relationship" in the ACF Field data.
	 */
	public $acf_field_key = 'field_cacf_civicrm_relationship';

	/**
	 * Fields which must be handled separately.
	 *
	 * @since 0.4.3
	 * @access public
	 * @var array $fields_handled The array of Fields which must be handled separately.
	 */
	public $fields_handled = [
		'civicrm_relationship',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.4.3
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to parent.
		$this->civicrm = $parent;

		// Init when the CiviCRM object is loaded.
		add_action( 'civicrm_acf_integration_civicrm_loaded', [ $this, 'initialise' ] );

		// Init parent.
		parent::__construct();

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.4.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.4.3
	 */
	public function register_hooks() {

		// Intercept when a CiviCRM Contact's Relationship has been updated.
		add_action( 'civicrm_acf_integration_mapper_relationship_created', [ $this, 'relationship_edited' ], 10, 2 );
		add_action( 'civicrm_acf_integration_mapper_relationship_edited', [ $this, 'relationship_edited' ], 10, 2 );
		add_action( 'civicrm_acf_integration_mapper_relationship_deleted', [ $this, 'relationship_edited' ], 10, 2 );

		// Process activation and deactivation.
		add_action( 'civicrm_acf_integration_relationship_created', [ $this, 'relationship_activate' ], 10, 2 );
		add_action( 'civicrm_acf_integration_relationship_activated', [ $this, 'relationship_activate' ], 10, 2 );
		add_action( 'civicrm_acf_integration_relationship_deactivated', [ $this, 'relationship_deactivate' ], 10, 2 );

		// Add any Relationship Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * @since 0.4.3
	 *
	 * @param array $contact The CiviCRM Contact data.
	 * @param WP_Post $post The WordPress Post object.
	 * @param array $fields The array of ACF Field values, keyed by Field selector.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function fields_handled_update( $contact, $post, $fields ) {

		// Bail if we have no field data to save.
		if ( empty( $fields ) ) {
			return true;
		}

		// Init success.
		$success = true;

		// Loop through the field data.
		foreach( $fields AS $field => $value ) {

			// Get the field settings.
			$settings = get_field_object( $field );

			// Maybe update a Relationship.
			$success = $this->field_handled_update( $field, $value, $contact['id'], $settings );

		}

		// --<
		return $success;

	}



	/**
	 * Update a CiviCRM Contact's Relationship with data from an ACF Field.
	 *
	 * Relationships require special handling because they are not part
	 * of the core Contact data.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data.
	 * @param mixed $value The ACF Field value.
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $settings The ACF Field settings.
	 * @return bool True if updates were successful, or false on failure.
	 */
	public function field_handled_update( $field, $value, $contact_id, $settings ) {

		// Get the "CiviCRM Relationship" key.
		$relationship_key = $this->acf_field_key_get();

		// Skip if we don't have a synced Relationship.
		if ( empty( $settings[$relationship_key] ) ) {
			return true;
		}

		// Skip if it's not a Relationship that requires special handling.
		if ( ! in_array( $settings['type'], $this->fields_handled ) ) {
			return true;
		}

		// The Relationship code is the setting.
		$code = $settings[$relationship_key];

		// Parse value by field type.
		$value = $this->plugin->acf->field->value_get_for_civicrm( $settings['type'], $value );

		// Update the Relationships.
		$success = $this->relationships_update( $contact_id, $value, $code );

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update all of a CiviCRM Contact's Relationships.
	 *
	 * @since 0.4.3
	 *
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array $target_contact_ids The array of Contact IDs in the ACF Field.
	 * @param str $code The code that identifies the Relationship and direction.
	 * @return array|bool $relationships The array of Relationship data, or false on failure.
	 */
	public function relationships_update( $contact_id, $target_contact_ids, $code ) {

		// Init return.
		$relationships = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $relationships;
		}

		// Get the Relationship data.
		$relationship_data = explode( '_', $code );
		$relationship_type_id = absint( $relationship_data[0] );
		$relationship_direction = $relationship_data[1];

		// Get the current Relationships.
		$params = [
			'version' => 3,
			'relationship_type_id' => $relationship_type_id,
		];

		// We need to find all Relationships for the Contact.
		if ( $relationship_direction == 'ab' ) {
			$params['contact_id_a'] = $contact_id;
		} else {
			$params['contact_id_b'] = $contact_id;
		}

		// Call the CiviCRM API.
		$current = civicrm_api( 'Relationship', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $current['is_error'] ) AND $current['is_error'] == 1 ) {
			return $relationships;
		}

		// If there are no existing Relationships.
		if ( empty( $current['values'] ) ) {

			// Bail early if there are no target contact IDs.
			if ( empty( $target_contact_ids ) ) {
				return $relationships;
			}

			// Create a Relationship for each target.
			foreach( $target_contact_ids AS $target_contact_id ) {

				// Assign the correct Source and Target.
				if ( $relationship_direction == 'ab' ) {
					$contact_id_a = $contact_id;
					$contact_id_b = $target_contact_id;
				} else {
					$contact_id_a = $target_contact_id;
					$contact_id_b = $contact_id;
				}

				// Okay, let's do it.
				$relationship = $this->relationship_create( $contact_id_a, $contact_id_b, $relationship_type_id );

				// Add to return array.
				$relationships[] = $relationship;

				/**
				 * Broadcast that a Relationship has been created.
				 *
				 * @since 0.4.5
				 *
				 * @param array $relationship The created Relationship.
				 * @param str $relationship_direction The Relationship direction.
				 */
				do_action( 'civicrm_acf_integration_relationship_created', $relationship, $relationship_direction );

			}

			// No need to go any further.
			return $relationships;

		}

		// We have existing relationships.
		$existing = [
			'ignore' => [],
			'activate' => [],
			'deactivate' => [],
		];

		// Make a copy of the target IDs.
		$unhandled_contact_ids = $target_contact_ids;

		// Let's look at them.
		foreach( $current['values'] AS $current_relationship ) {

			// Maybe deactivate when there are no target Contacts.
			if ( empty( $target_contact_ids ) ) {
				if ( $current_relationship['is_active'] == '1' ) {
					$existing['deactivate'][] = $current_relationship;
				} else {
					$existing['ignore'][] = $current_relationship;
				}
				continue;
			}

			// Flag unmatched.
			$active_match = false;
			$inactive_match = false;

			// Check against each target Contact.
			foreach( $target_contact_ids AS $key => $target_contact_id ) {

				// We need to assign the correct Source and Target.
				if ( $relationship_direction == 'ab' ) {
					$contact_id_a = $contact_id;
					$contact_id_b = $target_contact_id;
				} else {
					$contact_id_a = $target_contact_id;
					$contact_id_b = $contact_id;
				}

				// Is there a match?
				if (
					$current_relationship['contact_id_a'] == $contact_id_a
					AND
					$current_relationship['contact_id_b'] == $contact_id_b
				) {

					// Flag as "active match" if the Relationship is active.
					if ( $current_relationship['is_active'] == '1' ) {
						$active_match = $key;
					} else {
						$inactive_match = $key;
					}

					// Either way, we can move on to the next item.
					break;

				}

			}

			// If we got an active match, add to "ignore".
			if ( $active_match !== false ) {

				/**
				 * This is a current Relationship that is active. For now, let's
				 * just leave it alone. What we may do in future is apply any
				 * settings that the Relationship has - e.g.
				 *
				 * - Permissions,
				 * - Description, etc
				 *
				 * This will require ACF Sub-Fields.
				 */

				// Add to the list of existing Relationships to be ignored.
				if (
					! array_key_exists( $current_relationship['id'], $existing['ignore'] ) AND
					! array_key_exists( $current_relationship['id'], $existing['activate'] ) AND
					! array_key_exists( $current_relationship['id'], $existing['deactivate'] )
				) {
					$existing['ignore'][$current_relationship['id']] = $current_relationship;
				}

				// Remove from unhandled contacts.
				unset( $unhandled_contact_ids[$active_match] );

			} elseif ( $inactive_match !== false ) {

				/**
				 * This is a current Relationship that must be activated.
				 *
				 * We need to update as active because there is a correspondence
				 * with a target Contact.
				 */

				// Add to the list of existing Relationships to be activated.
				if (
					! array_key_exists( $current_relationship['id'], $existing['ignore'] ) AND
					! array_key_exists( $current_relationship['id'], $existing['activate'] ) AND
					! array_key_exists( $current_relationship['id'], $existing['deactivate'] )
				) {
					$existing['activate'][$current_relationship['id']] = $current_relationship;
				}

				// Remove from unhandled contacts.
				unset( $unhandled_contact_ids[$inactive_match] );

			} else {

				/**
				 * This is a current Relationship that must be deactivated.
				 *
				 * We update as inactive because there is no correspondence
				 * with a target Contact.
				 */

				// Add to the list of existing Relationships to be deactivated.
				if (
					! array_key_exists( $current_relationship['id'], $existing['ignore'] ) AND
					! array_key_exists( $current_relationship['id'], $existing['activate'] ) AND
					! array_key_exists( $current_relationship['id'], $existing['deactivate'] )
				) {

					// But only if it's currently active.
					if ( $current_relationship['is_active'] == '1' ) {
						$existing['deactivate'][$current_relationship['id']] = $current_relationship;
					} else {
						$existing['ignore'][$current_relationship['id']] = $current_relationship;
					}

				}

			}

		}

		// First update all Relationships that must be deactivated.
		foreach( $existing['deactivate'] AS $current_relationship ) {

			// Copy minimum values.
			$params = [
				'id' => $current_relationship['id'],
				'contact_id_a' => $current_relationship['contact_id_a'],
				'contact_id_b' => $current_relationship['contact_id_b'],
				'relationship_type_id' => $current_relationship['relationship_type_id'],
			];

			// Just update active status.
			$params['is_active'] = '0';

			// Do update.
			$success = $this->relationship_update( $params );

			// Continue on failure.
			if ( $success === false ) {
				continue;
			}

			// Add to return.
			$relationships[] = $success;

			/**
			 * The corresponding Contact's mapped Post also needs to be updated.
			 *
			 * @since 0.4.3
			 *
			 * @param array $current_relationship The updated Relationship.
			 * @param str $relationship_direction The Relationship direction.
			 */
			do_action( 'civicrm_acf_integration_relationship_deactivated', $current_relationship, $relationship_direction );

		}

		// Next update all Relationships that must be activated.
		foreach( $existing['activate'] AS $current_relationship ) {

			// Copy minimum values.
			$params = [
				'id' => $current_relationship['id'],
				'contact_id_a' => $current_relationship['contact_id_a'],
				'contact_id_b' => $current_relationship['contact_id_b'],
				'relationship_type_id' => $current_relationship['relationship_type_id'],
			];

			// Just update active status.
			$params['is_active'] = '1';

			// Do update.
			$success = $this->relationship_update( $params );

			// Continue on failure.
			if ( $success === false ) {
				continue;
			}

			// Add to return.
			$relationships[] = $success;

			/**
			 * The corresponding Contact's mapped Post also needs to be updated.
			 *
			 * @since 0.4.3
			 *
			 * @param array $current_relationship The updated Relationship.
			 * @param str $relationship_direction The Relationship direction.
			 */
			do_action( 'civicrm_acf_integration_relationship_activated', $current_relationship, $relationship_direction );

		}

		// Finally create a Relationship for each unhandled target.
		if ( ! empty( $unhandled_contact_ids ) ) {
			foreach( $unhandled_contact_ids AS $target_contact_id ) {

				// We need to assign the correct Source and Target.
				if ( $relationship_direction == 'ab' ) {
					$contact_id_a = $contact_id;
					$contact_id_b = $target_contact_id;
				} else {
					$contact_id_a = $target_contact_id;
					$contact_id_b = $contact_id;
				}

				// Okay, let's do it.
				$relationship = $this->relationship_create( $contact_id_a, $contact_id_b, $relationship_type_id );

				// Add to return array.
				$relationships[] = $relationship;

				/**
				 * Broadcast that a Relationship has been created.
				 *
				 * @since 0.4.5
				 *
				 * @param array $relationship The created Relationship.
				 * @param str $relationship_direction The Relationship direction.
				 */
				do_action( 'civicrm_acf_integration_relationship_created', $relationship, $relationship_direction );

			}
		}

		// --<
		return $relationships;

	}



	/**
	 * Activate a CiviCRM Relationship.
	 *
	 * This callback handles the updates of the ACF Field for the corresponding
	 * Contact's Post when the ACF Field on a Post is updated.
	 *
	 * @since 0.4.3
	 *
	 * @param array $relationship The updated Relationship.
	 * @param str $direction The Relationship direction.
	 */
	public function relationship_activate( $relationship, $direction ) {

		// Assign the correct Source and Target.
		if ( $direction == 'ab' ) {
			$contact_id = $relationship['contact_id_b'];
		} else {
			$contact_id = $relationship['contact_id_a'];
		}

		// Make sure we're activating.
		$relationship['is_active'] = '1';

		// Cast as an object.
		$relationship = (object) $relationship;

		// Do the update.
		$this->acf_field_update( $contact_id, $relationship, 'edit' );

	}



	/**
	 * Deactivate a CiviCRM Relationship.
	 *
	 * This callback handles the updates of the ACF Field for the corresponding
	 * Contact's Post when the ACF Field on a Post is updated.
	 *
	 * @since 0.4.3
	 *
	 * @param array $relationship The updated Relationship as return.
	 * @param str $direction The Relationship direction.
	 */
	public function relationship_deactivate( $relationship, $direction ) {

		// Assign the correct Source and Target.
		if ( $direction == 'ab' ) {
			$contact_id = $relationship['contact_id_b'];
		} else {
			$contact_id = $relationship['contact_id_a'];
		}

		// Make sure we're deactivating.
		$relationship['is_active'] = '0';

		// Cast as an object.
		$relationship = (object) $relationship;

		// Do the update.
		$this->acf_field_update( $contact_id, $relationship, 'edit' );

	}



	// -------------------------------------------------------------------------



	/**
	 * Create a CiviCRM Relationship.
	 *
	 * @since 0.4.3
	 *
	 * @param int $contact_id_a The numeric ID of Contact A.
	 * @param int $contact_id_b The numeric ID of Contact B.
	 * @param int $type_id The numeric ID of Relationship Type.
	 * @return array|bool $relationship The array of Relationship data, or false on failure.
	 */
	public function relationship_create( $contact_id_a, $contact_id_b, $type_id ) {

		// Init return.
		$relationship = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $relationship;
		}

		// Param to create the Relationship.
		$params = [
			'version' => 3,
			'contact_id_a' => $contact_id_a,
			'contact_id_b' => $contact_id_b,
			'relationship_type_id' => $type_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Relationship', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $relationship;
		}

		// The result set should contain only one item.
		$relationship = array_pop( $result['values'] );

		// --<
		return $relationship;

	}



	/**
	 * Update a CiviCRM Relationship.
	 *
	 * @since 0.4.3
	 *
	 * @param array $params The params to update the Relationship with.
	 * @return array|bool $relationship The array of Relationship data, or false on failure.
	 */
	public function relationship_update( $params = [] ) {

		// Init return.
		$relationship = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $relationship;
		}

		// Build params to update the Relationship.
		$params['version'] = 3;

		// Bail if there's no ID.
		if ( empty( $params['id'] ) ) {
			return $relationship;
		}

		// Call the CiviCRM API.
		$result = civicrm_api( 'Relationship', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $relationship;
		}

		// The result set should contain only one item.
		$relationship = array_pop( $result['values'] );

		// --<
		return $relationship;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Contact's Relationship has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function relationship_edited( $args ) {

		// Grab Relationship object.
		$relationship = $args['objectRef'];

		// We need to update the ACF Fields on both Posts since they may be synced.
		$this->acf_field_update( $relationship->contact_id_a, $relationship, $args['op'] );
		$this->acf_field_update( $relationship->contact_id_b, $relationship, $args['op'] );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get a Relationship Type by its numeric ID.
	 *
	 * @since 0.4.3
	 *
	 * @param int $relationship_id The numeric ID of the Relationship Type.
	 * @return array $relationship The array of Relationship Type data.
	 */
	public function type_get_by_id( $relationship_id ) {

		// Init return.
		$relationship = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $relationship;
		}

		// Params to get the Relationship Type.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $relationship_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'RelationshipType', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $relationship;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $relationship;
		}

 		// The result set should contain only one item.
		$relationship = array_pop( $result['values'] );

		// --<
		return $relationship;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Relationships for a CiviCRM Contact Type mapped to an ACF Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $field The ACF Field data array.
	 * @return array $relationships The array of possible Relationships.
	 */
	public function get_for_acf_field( $field ) {

		// Init return.
		$relationships = [];

		// Get field group for this field's parent.
		$field_group = $this->plugin->acf->field_group->get_for_field( $field );

		// Bail if there's no field group.
		if ( empty( $field_group ) ) {
			return $relationships;
		}

		// Get the Contact Type.
		$contact_type_key = $this->plugin->civicrm->contact_type->acf_field_key_get();

		// Bail if there's no Contact Type.
		if ( empty( $field_group[$contact_type_key] ) ) {
			return $relationships;
		}

		// The Contact Type is the Field Group setting.
		$contact_type_id = $field_group[$contact_type_key];

		// Get Contact Type hierarchy.
		$contact_types = $this->civicrm->contact_type->hierarchy_get_by_id( $contact_type_id );

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $relationships;
		}

		// Params to get all Relationship Types for this top level Contact Type.
		// We need them in either direction.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'contact_type_a' => $contact_types['type'],
			'contact_type_b' => $contact_types['type'],
			'options' => [
				'limit' => 0,
				'or' => [
					[ 'contact_type_a', 'contact_type_b' ],
				],
			],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'RelationshipType', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $relationships;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $relationships;
		}

		/**
		 * Filter the retrieved relationships.
		 *
		 * Used internally by the custom ACF "CiviCRM Relationship" Field.
		 *
		 * @since 0.4.3
		 *
		 * @param array $relationships The retrieved array of Relationship Types.
		 * @param array $contact_types The array of Contact Types.
		 * @param array $field The ACF Field data array.
		 * @return array $relationships The modified array of Relationship Types.
		 */
		$relationships = apply_filters(
			'civicrm_acf_integration_relationships_get_for_acf_field',
			$result['values'], $contact_types, $field
		);

		// --<
		return $relationships;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update the Relationship ACF Field on a Post mapped to a Contact ID.
	 *
	 * @since 0.4.3
	 *
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param array|object $relationship The Relationship data.
	 * @param string $op The type of database operation.
	 */
	public function acf_field_update( $contact_id, $relationship, $op ) {

		// Grab Contact.
		$contact = $this->plugin->civicrm->contact->get_by_id( $contact_id );

		// Get the Post ID that this Contact is mapped to.
		$post_id = $this->plugin->civicrm->contact->is_mapped( $contact );

		// Skip if not mapped.
		if ( $post_id === false ) {
			return;
		}

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if we don't have any Relationship Fields.
		if ( empty( $acf_fields['relationship'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['relationship'] AS $selector => $value ) {

			// Get the Relationship data.
			$relationship_data = explode( '_', $value );
			$relationship_type_id = absint( $relationship_data[0] );
			$relationship_direction = $relationship_data[1];

			// Skip if this Relationship is not mapped to the Field.
			if ( $relationship_type_id != $relationship->relationship_type_id ) {
				continue;
			}

			// Get the existing value, which should be an array.
			$existing = get_field( $selector, $post_id );

			// If it isn't one, let's make it an empty array.
			if ( ! is_array( $existing ) OR empty( $existing ) ) {
				$existing = [];
			}

			// Assign the correct Target Contact ID.
			if ( $relationship_direction == 'ab' ) {
				$target_contact_id = $relationship->contact_id_b;
			} else {
				$target_contact_id = $relationship->contact_id_a;
			}

			// If deleting the Relationship.
			if ( $op == 'delete' ) {

				// Remove Contact ID if it's there.
				if ( in_array( $target_contact_id, $existing ) ) {
					$existing = array_diff( $existing, [ $target_contact_id ] );
				}

			// If creating the Relationship.
			} elseif ( $op == 'create' ) {

				// Add Contact ID if it's not there.
				if ( ! in_array( $target_contact_id, $existing ) ) {
					$existing[] = $target_contact_id;
				}

			} else {

				// If the Relationship is active.
				if ( $relationship->is_active == '1' ) {

					// Add Contact ID if it's not there.
					if ( ! in_array( $target_contact_id, $existing ) ) {
						$existing[] = $target_contact_id;
					}

				} else {

					// Remove Contact ID if it's there.
					if ( in_array( $target_contact_id, $existing ) ) {
						$existing = array_diff( $existing, [ $target_contact_id ] );
					}

				}

			}

			// Overwrite the ACF Field data.
			$this->plugin->acf->field->value_update( $selector, $existing, $post_id );

		}

	}



	/**
	 * Return the "CiviCRM Relationship" ACF Settings Field.
	 *
	 * @since 0.4.3
	 *
	 * @param array $relationships The Relationships to populate the ACF Field with.
	 * @return array $field The ACF Field data array.
	 */
	public function acf_field_get( $relationships = [] ) {

		// Bail if empty.
		if ( empty( $relationships ) ) {
			return;
		}

		// Define field.
		$field = [
			'key' => $this->acf_field_key_get(),
			'label' => __( 'CiviCRM Relationship', 'civicrm-acf-integration' ),
			'name' => $this->acf_field_key_get(),
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Relationship that this ACF Field should sync with. (Optional)', 'civicrm-acf-integration' ),
			'default_value' => '',
			'placeholder' => '',
			'allow_null' => 1,
			'multiple' => 0,
			'ui' => 0,
			'required' => 0,
			'return_format' => 'value',
			'parent' => $this->plugin->acf->field_group->placeholder_group_get(),
			'choices' => $relationships,
		];

		// --<
		return $field;

	}



	/**
	 * Getter method for the "CiviCRM Relationship" key.
	 *
	 * @since 0.4.3
	 *
	 * @return str $acf_field_key The key of the "CiviCRM Relationship" in the ACF Field data.
	 */
	public function acf_field_key_get() {

		// --<
		return $this->acf_field_key;

	}



	/**
	 * Add any Relationship Fields that are attached to a Post.
	 *
	 * @since 0.4.3
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field_in_group The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Get the "CiviCRM Relationship" key.
		$relationship_key = $this->acf_field_key_get();

		// Add if it has a reference to a Relationship Field.
		if ( ! empty( $field[$relationship_key] ) ) {
			$acf_fields['relationship'][$field['name']] = $field[$relationship_key];
		}

		// --<
		return $acf_fields;

	}



} // Class ends.




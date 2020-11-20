<?php
/**
 * CiviCRM Activity Class.
 *
 * Handles CiviCRM Activity functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.7.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Activity Class.
 *
 * A class that encapsulates CiviCRM Activity functionality.
 *
 * @since 0.7.3
 */
class CiviCRM_ACF_Integration_CiviCRM_Activity {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * "CiviCRM Field" field value prefix in the ACF Field data.
	 *
	 * This distinguishes Activity Fields from Custom Fields.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var str $activity_field_prefix The prefix of the "CiviCRM Field" value.
	 */
	public $activity_field_prefix = 'caiactivity_';



	/**
	 * Constructor.
	 *
	 * @since 0.7.3
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

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.7.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.7.3
	 */
	public function register_hooks() {

		// Always register Mapper hooks.
		$this->register_mapper_hooks();

		// Listen for events from Manual Sync that require Activity updates.
		add_action( 'civicrm_acf_integration_admin_activity_post_sync', [ $this, 'post_sync' ], 10 );
		add_action( 'civicrm_acf_integration_admin_activity_acf_fields_sync', [ $this, 'acf_fields_sync' ], 10 );

		// Listen for queries from our Field Group class.
		add_filter( 'civicrm_acf_integration_query_field_group_mapped', [ $this, 'query_field_group_mapped' ], 10, 2 );

		// Listen for queries from our Custom Field class.
		add_filter( 'civicrm_acf_integration_query_custom_fields', [ $this, 'query_custom_fields' ], 10, 2 );

		// Listen for queries from the Custom Field class.
		add_filter( 'civicrm_acf_integration_query_post_id', [ $this, 'query_post_id' ], 10, 2 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.8
	 */
	public function register_mapper_hooks() {

		// Listen for events from our Mapper that require Activity updates.
		add_action( 'civicrm_acf_integration_mapper_post_saved', [ $this, 'post_saved' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_acf_fields_saved', [ $this, 'acf_fields_saved' ], 10 );

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.8
	 */
	public function unregister_mapper_hooks() {

		// Remove all Mapper listeners.
		remove_action( 'civicrm_acf_integration_mapper_post_saved', [ $this, 'post_saved' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_acf_fields_saved', [ $this, 'acf_fields_saved' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Activity when a WordPress Post is synced.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function post_sync( $args ) {

		// Pass on.
		$this->post_saved( $args );

	}



	/**
	 * Update a CiviCRM Activity when a WordPress Post has been updated.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function post_saved( $args ) {

		// Bail if this Post should not be synced now.
		$this->do_not_sync = false;
		$post = $this->plugin->post->should_be_synced( $args['post'] );
		if ( false === $post ) {
			$this->do_not_sync = true;
			return;
		}

		// Bail if this Post Type is not mapped.
		if ( ! $this->plugin->post_type->is_mapped_to_activity_type( $post->post_type ) ) {
			$this->do_not_sync = true;
			return;
		}

		// Get the Activity ID.
		$activity_id = $this->plugin->post->activity_id_get( $post->ID );

		/*
		// Get previous values.
		$prev_values = get_fields( $post_id );

		// Get submitted values.
		$values = acf_maybe_get_POST( 'acf' );
		*/

		// Does this Post have an Activity ID?
		if ( $activity_id === false ) {

			// No - create an Activity.
			$activity = $this->create_from_post( $post );

			// Store Activity ID if successful.
			if ( $activity !== false ) {
				$this->plugin->post->activity_id_set( $post->ID, $activity['id'] );
			}

		} else {

			// Yes - update the Activity.
			$activity = $this->update_from_post( $post, $activity_id );

		}

		// Add our data to the params.
		$args['activity'] = $activity;
		$args['activity_id'] = $activity['id'];

		/**
		 * Broadcast that an Activity has been updated.
		 *
		 * May be used internally by:
		 *
		 * - Groups
		 * - Post Taxonomies
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The updated array of WordPress params.
		 */
		do_action( 'civicrm_acf_integration_activity_post_saved', $args );

	}



	/**
	 * Update a CiviCRM Activity when the ACF Fields on a WordPress Post are synced.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function acf_fields_sync( $args ) {

		// Pass on.
		$this->acf_fields_saved( $args );

	}



	/**
	 * Update a CiviCRM Activity when the ACF Fields on a WordPress Post have been updated.
	 *
	 * @since 0.7.3
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function acf_fields_saved( $args ) {

		// Bail early if the ACF Fields are not attached to a Post Type.
		if ( ! isset( $this->do_not_sync ) ) {
			return;
		}

		// Bail early if this Post Type shouldn't be synced.
		// @see self::post_saved()
		if ( $this->do_not_sync === true ) {
			return;
		}

		// Bail if it's not a Post.
		$entity = $this->plugin->acf->field->entity_type_get( $args['post_id'] );
		if ( $entity !== 'post' ) {
			return;
		}

		// We need the Post itself.
		$post = get_post( $args['post_id'] );

		// Bail if this is a revision.
		if ( $post->post_type == 'revision' ) {
			return;
		}

		// Does this Post have an Activity ID?
		$activity_id = $this->plugin->post->activity_id_get( $post->ID );

		// Bail if there isn't one.
		if ( $activity_id === false ) {
			return;
		}

		/*
		 * Get existing field values.
		 *
		 * These are actually the *new* values because we are hooking in *after*
		 * the fields have been saved.
		 */
		$fields = get_fields( $post->ID, false );

		// TODO: Decide if we should get the ACF Field data without formatting.
		// This also applies to any calls to get_field_object().
		//$fields = get_fields( $post->ID, false );

		// Get submitted values. (No need for this - see hook priority)
		//$submitted_values = acf_maybe_get_POST( 'acf' );

		// Update the Activity with this data.
		$activity = $this->update_from_fields( $activity_id, $fields, $post->ID );

		// Add our data to the params.
		$args['activity_id'] = $activity_id;
		$args['activity'] = $activity;
		$args['post'] = $post;
		$args['fields'] = $fields;

		/**
		 * Broadcast that an Activity has been updated when ACF Fields were saved.
		 *
		 * Used internally by:
		 *
		 * - Activity Fields to maintain sync with:
		 *   - The ACF "Activity Date Time" Field
		 *   - The ACF "Created Date" Field
		 *   - The ACF "Modified Date" Field
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The updated array of WordPress params.
		 */
		do_action( 'civicrm_acf_integration_activity_acf_fields_saved', $args );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get "chunked" CiviCRM API Activity data for a given Activity Type.
	 *
	 * This method is used internally by the "Manual Sync" admin page.
	 *
	 * @since 0.7.3
	 *
	 * @param integer $contact_type_id The numeric ID of the CiviCRM Activity Type.
	 * @param int $offset The numeric offset for the query.
	 * @param int $limit The numeric limit for the query.
	 * @return array $result The array of Activity data from the CiviCRM API.
	 */
	public function activities_chunked_data_get( $activity_type_id, $offset, $limit ) {

		// Sanity check.
		if ( empty( $activity_type_id ) ) {
			return 0;
		}

		// Params to query Activities.
		$params = [
			'version' => 3,
			'activity_type_id' => $activity_type_id,
			'options' => [
				'limit' => $limit,
				'offset' => $offset,
			],
		];

		// Call API.
		$result = civicrm_api( 'Activity', 'get', $params );

		// Add log entry on failure.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'activity_type_id' => $activity_type_id,
				'offset' => $offset,
				'limit' => $limit,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );
			return $result;
		}

		// --<
		return $result;

	}



	// -------------------------------------------------------------------------



	/**
	 * Check whether an Activity's Activity Type is mapped to a Post Type.
	 *
	 * The Mapper makes use of the boolean return to bail early.
	 *
	 * @see CiviCRM_ACF_Integration_Mapper::activity_pre_create()
	 * @see CiviCRM_ACF_Integration_Mapper::activity_pre_edit()
	 *
	 * @since 0.7.3
	 *
	 * @param array|obj $activity The Activity data.
	 * @param str $create_post Create a mapped Post if missing. Either 'create' or 'skip'.
	 * @return str|bool $is_mapped The Post Type if the Activity is mapped, false otherwise.
	 */
	public function is_mapped( $activity, $create_post = 'skip' ) {

		// Init return.
		$is_mapped = false;

		// Maybe cast Activity data as object.
		if ( is_array( $activity ) ) {
			$activity = (object) $activity;
		}

		// Skip if there is no Activity Type.
		if ( empty( $activity->activity_type_id ) ) {
			return $is_mapped;
		}

		// Get the Post Type mapped to this Activity Type.
		$post_type = $this->plugin->civicrm->activity_type->is_mapped_to_post_type( $activity->activity_type_id );

		// Skip if this Activity Type is not mapped.
		if ( $post_type === false ) {
			return $is_mapped;
		}

		// Bail if there's no Activity ID.
		if ( empty( $activity->id ) ) {
			return $is_mapped;
		}

		// Get the associated Post ID.
		$post_id = $this->plugin->post->get_by_activity_id( $activity->id, $post_type );

		// Create the Post if it's missing.
		if ( $post_id === false AND $create_post === 'create' ) {

			// Prevent recursion and the resulting unexpected Post creation.
			if ( ! doing_action( 'civicrm_acf_integration_post_activity_sync' ) ) {

				// Get full Activity data.
				$activity_data = $this->get_by_id( $activity->id );

				// Remove WordPress callbacks to prevent recursion.
				$this->plugin->mapper->hooks_wordpress_remove();
				$this->plugin->mapper->hooks_civicrm_remove();

				// Let's make an array of params.
				$args = [
					'op' => 'sync',
					'objectName' => 'Activity',
					'objectId' => $activity_data['id'],
					'objectRef' => (object) $activity_data,
				];

				// Sync this Activity to the Post Type.
				$this->plugin->post->activity_sync_to_post( $args, $post_type );

				// Reinstate WordPress callbacks.
				$this->plugin->mapper->hooks_wordpress_add();
				$this->plugin->mapper->hooks_civicrm_add();

			}

		}

		// Assign Post Type.
		$is_mapped = $post_type;

		// --<
		return $is_mapped;

	}



	/**
	 * Check if an Activity is mapped to a Post of a particular Post Type.
	 *
	 * @since 0.7.3
	 *
	 * @param array|obj $activity The Activity data.
	 * @param str $post_type The WordPress Post Type.
	 * @return int|bool $is_mapped The ID of the WordPress Post if the Activity is mapped, false otherwise.
	 */
	public function is_mapped_to_post( $activity, $post_type = 'any' ) {

		// TODO: Query Posts with Post meta instead? Or pseudo-cache?

		// Assume not.
		$is_mapped = false;

		// Maybe cast Activity data as object.
		if ( is_array( $activity ) ) {
			$activity = (object) $activity;
		}

		// Bail if this Activity's Activity Type is not mapped.
		$post_type = $this->is_mapped( $activity );
		if ( $post_type === false ) {
			return false;
		}

		// Grab Activity ID.
		if ( isset( $activity->id ) ) {
			$activity_id = $activity->id;
		}

		// Bail if no Activity ID is found.
		if ( empty( $activity_id ) ) {
			return $is_mapped;
		}

		// Find the Post ID of this Post Type that this Activity is synced with.
		$post_ids = $this->plugin->post->get_by_activity_id( $activity_id, $post_type );

		// Bail if no Post IDs are found.
		if ( empty( $post_ids ) ) {
			return $is_mapped;
		}

		// There should be only one Post ID per Post Type.
		$is_mapped = array_pop( $post_ids );

		// --<
		return $is_mapped;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Activity data for a given ID.
	 *
	 * @since 0.7.3
	 *
	 * @param int $activity_id The numeric ID of the CiviCRM Activity to query.
	 * @return array|bool $activity_data An array of Activity data, or false on failure.
	 */
	public function get_by_id( $activity_id ) {

		// Init return.
		$activity_data = false;

		// Bail if we have no Activity ID.
		if ( empty( $activity_id ) ) {
			return $activity_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $activity_data;
		}

		// Define params to get queried Activity.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'id' => $activity_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Activity', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $activity_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $activity_data;
		}

		// The result set should contain only one item.
		$activity_data = array_pop( $result['values'] );

		// Backfill Target IDs.
		$activity_data['target_contact_id'] = [];
		$targets = $this->get_target_contact_ids( $activity_id );
		if ( ! empty( $targets ) ) {
			$activity_data['target_contact_id'] = $targets;
		}

		// Backfill Assignee IDs.
		$activity_data['assignee_contact_id'] = [];
		$assignees = $this->get_assignee_contact_ids( $activity_id );
		if ( ! empty( $assignees ) ) {
			$activity_data['assignee_contact_id'] = $assignees;
		}

		// --<
		return $activity_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the CiviCRM Activity Target Contact IDs for a given ID.
	 *
	 * For reference, ActivityContact records can have a "record_type_id" which
	 * has the following possible values:
	 *
	 * 1: assignee
	 * 2: creator
	 * 3: focus or target
	 *
	 * @since 0.7.3
	 *
	 * @param int $activity_id The numeric ID of the CiviCRM Activity to query.
	 * @return array|bool $contact_ids An array of Contact IDs, or false on failure.
	 */
	public function get_target_contact_ids( $activity_id ) {

		// Init return.
		$contact_ids = false;

		// Bail if we have no Activity ID.
		if ( empty( $activity_id ) ) {
			return $contact_ids;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_ids;
		}

		// Define params to get queried Activity Targets.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'activity_id' => $activity_id,
			'record_type_id' => 3,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'ActivityContact', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_ids;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_ids;
		}

		// Grab just the Contact IDs.
		$contact_ids = wp_list_pluck( $result['values'], 'contact_id' );

		// --<
		return $contact_ids;

	}



	/**
	 * Get the CiviCRM Activity Assignee Contact IDs for a given ID.
	 *
	 * For reference, ActivityContact records can have a "record_type_id" which
	 * has the following possible values:
	 *
	 * 1: assignee
	 * 2: creator
	 * 3: focus or target
	 *
	 * @since 0.7.3
	 *
	 * @param int $activity_id The numeric ID of the CiviCRM Activity to query.
	 * @return array|bool $contact_ids An array of Contact IDs, or false on failure.
	 */
	public function get_assignee_contact_ids( $activity_id ) {

		// Init return.
		$contact_ids = false;

		// Bail if we have no Activity ID.
		if ( empty( $activity_id ) ) {
			return $contact_ids;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact_ids;
		}

		// Define params to get queried Activity Targets.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'activity_id' => $activity_id,
			'record_type_id' => 1,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'ActivityContact', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $contact_ids;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact_ids;
		}

		// Grab just the Contact IDs.
		$contact_ids = wp_list_pluck( $result['values'], 'contact_id' );

		// --<
		return $contact_ids;

	}



	// -------------------------------------------------------------------------



	/**
	 * Create a CiviCRM Activity for a given set of data.
	 *
	 * @since 0.7.3
	 *
	 * @param array $activity The CiviCRM Activity data.
	 * @return array|bool $activity_data The array Activity data from the CiviCRM API, or false on failure.
	 */
	public function create( $activity ) {

		// Init as failure.
		$activity_data = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $activity_data;
		}

		// Build params to create Activity.
		$params = [
			'version' => 3,
		] + $activity;

		/*
		 * Minimum array to create an Activity:
		 *
		 * $params = [
		 *   'version' => 3,
		 *   'activity_type_id' => 56,
		 *   'source_contact_id' => "user_contact_id",
		 * ];
		 *
		 * Updates are triggered by:
		 *
		 * $params['id'] = 654;
		 *
		 * Custom Fields are addressed by ID:
		 *
		 * $params['custom_9'] = "Blah";
		 * $params['custom_7'] = 1;
		 * $params['custom_8'] = 0;
		 *
		 * CiviCRM kindly ignores any Custom Fields which are passed to it that
		 * aren't attached to the Entity. This is of significance when a Field
		 * Group is attached to multiple Post Types (for example) and the Fields
		 * refer to different Entities (e.g. "Activity" and "Student").
		 *
		 * Nice.
		 */

		// Call the API.
		$result = civicrm_api( 'Activity', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $activity_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $activity_data;
		}

		// The result set should contain only one item.
		$activity_data = array_pop( $result['values'] );

		// --<
		return $activity_data;

	}



	/**
	 * Update a CiviCRM Activity with a given set of data.
	 *
	 * @since 0.7.3
	 *
	 * @param array $activity The CiviCRM Activity data.
	 * @return array|bool $activity_data The array Activity data from the CiviCRM API, or false on failure.
	 */
	public function update( $activity ) {

		// Log and bail if there's no Activity ID.
		if ( empty( $activity['id'] ) ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numerical ID must be present to update an Activity.', 'civicrm-acf-integration' ),
				'activity' => $activity,
				'backtrace' => $trace,
			], true ) );
			return $activity_data;
		}

		// Pass through.
		return $this->create( $activity );

	}



	/**
	 * Fill out the missing data for a CiviCRM Activity.
	 *
	 * @since 0.7.3
	 *
	 * @param object $activity_id The numeric ID of the CiviCRM Activity.
	 * @return object $activity The backfilled CiviCRM Activity data.
	 */
	public function backfill( $activity ) {

		// Get the full Activity data.
		$activity_full = $this->get_by_id( $activity->id );

		// Bail on failure.
		if ( $activity_full === false ) {
			return $activity;
		}

		// Fill out missing Activity data.
		foreach( $activity_full AS $key => $item ) {
			if ( empty( $activity->$key ) AND ! empty( $item ) ) {
				$activity->$key = $item;
			}
		}

		// --<
		return $activity;

	}



	// -------------------------------------------------------------------------



	/**
	 * Prepare the required CiviCRM Activity data from a WordPress Post.
	 *
	 * @since 0.7.3
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @param int $activity_id The numeric ID of the Activity (or null if new).
	 * @return array $activity_data The CiviCRM Activity data.
	 */
	public function prepare_from_post( $post, $activity_id = null ) {

		// Init required data.
		$activity_data = [];

		// Maybe add the Activity ID.
		if ( ! empty( $activity_id ) ) {
			$activity_data['id'] = $activity_id;
		}

		// Assign Date Fields if creating Activity.
		if ( empty( $activity_id ) ) {
			$activity_data['activity_date_time'] = $post->post_date;
			$activity_data['created_date'] = $post->post_date;
			$activity_data['modified_date'] = $post->post_modified;
		}

		// Assign Creator if creating Activity.
		if ( empty( $activity_id ) ) {
			$activity_data['source_contact_id'] = 'user_contact_id';
		}

		// Assign a default Status - "Scheduled" - if creating Activity.
		if ( empty( $activity_id ) ) {
			$activity_data['status_id'] = 1;
		}

		// Assign a default Priority - "Normal" - if creating Activity.
		if ( empty( $activity_id ) ) {
			$activity_data['priority_id'] = 2;
		}

		// Always assign Post Title to Activity "subject".
		if ( empty( $post->post_title ) ) {
			$activity_data['subject'] = __( 'Name not set', 'civicrm-acf-integration' );
		} else {
			$activity_data['subject'] = $post->post_title;
		}

		// Always assign Post Content to Activity "details".
		$activity_data['details'] = $post->post_content;

		// Always assign Activity Type ID.
		$activity_data['activity_type_id'] = $this->plugin->civicrm->activity_type->id_get_for_post_type( $post->post_type );

		/*
		// Set a status for the Activity depending on the Post status.
		if ( $post->post_status == 'trash' ) {
			$activity_data['is_deleted'] = 1;
		} else {
			$activity_data['is_deleted'] = 0;
		}
		*/

		/**
		 * Filter the Activity data.
		 *
		 * @since 0.7.3
		 *
		 * @param array $activity_data The existing CiviCRM Activity data.
		 * @param WP_Post $post The WordPress Post.
		 * @return array $activity_data The modified CiviCRM Activity data.
		 */
		$activity_data = apply_filters( 'civicrm_acf_integration_post_activity_data', $activity_data, $post );

		// --<
		return $activity_data;

	}



	/**
	 * Create a CiviCRM Activity from a WordPress Post.
	 *
	 * This can be merged with `self::update_from_post()` in due course.
	 *
	 * @since 0.7.3
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @return array|bool $activity The CiviCRM Activity data, or false on failure.
	 */
	public function create_from_post( $post ) {

		// Build required data.
		$activity_data = $this->prepare_from_post( $post );

		// Create the Activity.
		$activity = $this->create( $activity_data );

		// --<
		return $activity;

	}



	/**
	 * Sync a WordPress Post with a CiviCRM Activity.
	 *
	 * When we update the Activity, we always sync:
	 *
	 * - The WordPress Post's "title" with the CiviCRM Activity's "subject".
	 * - The WordPress Post's "content" with the CiviCRM Activity's "details".
	 *
	 * @since 0.7.3
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @param int $existing_id The numeric ID of the Activity.
	 * @return array|bool $activity The CiviCRM Activity data, or false on failure.
	 */
	public function update_from_post( $post, $existing_id ) {

		// Build required data.
		$activity_data = $this->prepare_from_post( $post, $existing_id );

		// Update the Activity.
		$activity = $this->update( $activity_data );

		// --<
		return $activity;

	}



	// -------------------------------------------------------------------------



	/**
	 * Prepare the required CiviCRM Activity data from a set of ACF Fields.
	 *
	 * This method combines all Activity Fields that the CiviCRM API accepts as
	 * params for ( 'Activity', 'create' ) along with the linked Custom Fields.
	 *
	 * The CiviCRM API will update Custom Fields as long as they are passed to
	 * ( 'Activity', 'create' ) in the correct format. This is of the form:
	 * 'custom_N' where N is the ID of the Custom Field.
	 *
	 * @see CiviCRM_ACF_Integration_CiviCRM_Base
	 *
	 * @since 0.7.3
	 *
	 * @param array $fields The ACF Field data.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array|bool $activity_data The CiviCRM Activity data.
	 */
	public function prepare_from_fields( $fields, $post_id = null ) {

		// Init data for fields.
		$activity_data = [];

		// Bail if we have no field data to save.
		if ( empty( $fields ) ) {
			return $activity_data;
		}

		// Loop through the field data.
		foreach( $fields AS $field => $value ) {

			// Get the field settings.
			$settings = get_field_object( $field, $post_id );

			// Get the CiviCRM Custom Field and Activity Field.
			$custom_field_id = $this->civicrm->custom_field->custom_field_id_get( $settings );
			$activity_field_name = $this->activity_field_name_get( $settings );

			// Do we have a synced Custom Field or Activity Field?
			if ( ! empty( $custom_field_id ) OR ! empty( $activity_field_name ) ) {

				// If it's a Custom Field.
				if ( ! empty( $custom_field_id ) ) {

					// Build Custom Field code.
					$code = 'custom_' . $custom_field_id;

				} else {

					// The Activity Field code is the setting.
					$code = $activity_field_name;

					// Unless it's the "target" field.
					if ( $code == 'target_contact_id' ) {
						$code = 'target_id';
					}

					// Or it's the "assignee" field *FFS*
					if ( $code == 'assignee_contact_id' ) {
						$code = 'assignee_id';
					}

				}

				// Parse value by field type.
				$value = $this->plugin->acf->field->value_get_for_civicrm( $settings['type'], $value );

				// Some Activity Fields cannot be empty.
				$cannot_be_empty = [
					'source_contact_id',
					'activity_date_time',
					'created_date',
					'modified_date',
				];

				// Add it to the field data.
				if ( in_array( $code, $cannot_be_empty ) AND empty( $value ) ) {
					// Skip.
				} else {
					$activity_data[$code] = $value;
				}

			}

		}

		// --<
		return $activity_data;

	}



	/**
	 * Update a CiviCRM Activity with data from ACF Fields.
	 *
	 * @since 0.3
	 *
	 * @param int $activity_id The numeric ID of the Activity.
	 * @param array $fields The ACF Field data.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array|bool $activity The CiviCRM Activity data, or false on failure.
	 */
	public function update_from_fields( $activity_id, $fields, $post_id = null ) {

		// Build required data.
		$activity_data = $this->prepare_from_fields( $fields, $post_id );

		// Add the Activity ID.
		$activity_data['id'] = $activity_id;

		// Update the Activity.
		$activity = $this->update( $activity_data );

		// --<
		return $activity;

	}



	// -------------------------------------------------------------------------



	/**
	 * Return the "CiviCRM Field" ACF Settings Field.
	 *
	 * @since 0.7.3
	 *
	 * @param array $custom_fields The Custom Fields to populate the ACF Field with.
	 * @param array $activity_fields The Activity Fields to populate the ACF Field with.
	 * @return array $field The ACF Field data array.
	 */
	public function acf_field_get( $custom_fields = [], $activity_fields = [] ) {

		// Build choices array for dropdown.
		$choices = [];

		// Build Activity Field choices array for dropdown.
		$activity_fields_label = esc_attr__( 'Activity Fields', 'civicrm-acf-integration' );
		foreach( $activity_fields AS $activity_field ) {
			$choices[$activity_fields_label][$this->activity_field_prefix . $activity_field['name']] = $activity_field['title'];
		}

		// Build Custom Field choices array for dropdown.
		$custom_field_prefix = $this->civicrm->custom_field_prefix();
		foreach( $custom_fields AS $custom_group_name => $custom_group ) {
			$custom_fields_label = esc_attr( $custom_group_name );
			foreach( $custom_group AS $custom_field ) {
				$choices[$custom_fields_label][$custom_field_prefix . $custom_field['id']] = $custom_field['label'];
			}
		}

		/**
		 * Filter the choices to display in the "CiviCRM Field" select.
		 *
		 * @since 0.7.3
		 *
		 * @param array $choices The existing select options array.
		 * @param array $choices The modified select options array.
		 */
		$choices = apply_filters( 'civicrm_acf_integration_civicrm_field_choices', $choices );

		// Define field.
		$field = [
			'key' => $this->civicrm->acf_field_key_get(),
			'label' => __( 'CiviCRM Field', 'civicrm-acf-integration' ),
			'name' => $this->civicrm->acf_field_key_get(),
			'type' => 'select',
			'instructions' => __( 'Choose the CiviCRM Field that this ACF Field should sync with. (Optional)', 'civicrm-acf-integration' ),
			'default_value' => '',
			'placeholder' => '',
			'allow_null' => 1,
			'multiple' => 0,
			'ui' => 0,
			'required' => 0,
			'return_format' => 'value',
			'parent' => $this->plugin->acf->field_group->placeholder_group_get(),
			'choices' => $choices,
		];

		// --<
		return $field;

	}



	/**
	 * Get the mapped Activity Field name if present.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The existing field data array.
	 * @return str|bool $activity_field_name The name of the Activity Field, or false if none.
	 */
	public function activity_field_name_get( $field ) {

		// Init return.
		$activity_field_name = false;

		// Get the ACF CiviCRM Field key.
		$acf_field_key = $this->civicrm->acf_field_key_get();

		// Set the mapped Activity Field name if present.
		if ( isset( $field[$acf_field_key] ) ) {
			if ( false !== strpos( $field[$acf_field_key], $this->activity_field_prefix ) ) {
				$activity_field_name = strval( str_replace( $this->activity_field_prefix, '', $field[$acf_field_key] ) );
			}
		}

		/**
		 * Filter the Activity Field name.
		 *
		 * @since 0.7.3
		 *
		 * @param int $activity_field_name The existing Activity Field name.
		 * @param array $field The array of ACF Field data.
		 * @return int $activity_field_name The modified Activity Field name.
		 */
		$activity_field_name = apply_filters( 'civicrm_acf_integration_activity_activity_field_name_get', $activity_field_name, $field );

		// --<
		return $activity_field_name;

	}



	// -------------------------------------------------------------------------



	/**
	 * Listen for queries from the Field Group class.
	 *
	 * This method responds with a Boolean if it detects that this Field Group
	 * maps to an Activity Type.
	 *
	 * @since 0.7.3
	 *
	 * @param bool $mapped The existing mapping flag.
	 * @param array $field_group The array of ACF Field Group data.
	 * @param bool $mapped True if the Field Group is mapped, or pass through if not mapped.
	 */
	public function query_field_group_mapped( $mapped, $field_group ) {

		// Bail if a Mapping has already been found.
		if ( $mapped !== false ) {
			return $mapped;
		}

		// Bail if this is not an Activity Field Group.
		$is_activity_field_group = $this->is_activity_field_group( $field_group );
		if ( $is_activity_field_group === false ) {
			return $mapped;
		}

		// --<
		return true;

	}



	/**
	 * Listen for queries from the Custom Field class.
	 *
	 * @since 0.7.3
	 *
	 * @param array $custom_fields The existing Custom Fields.
	 * @param array $field_group The array of ACF Field Group data.
	 * @param array $custom_fields The populated array of CiviCRM Custom Fields params.
	 */
	public function query_custom_fields( $custom_fields, $field_group ) {

		// Bail if this is not an Activity Field Group.
		$is_visible = $this->is_activity_field_group( $field_group );
		if ( $is_visible === false ) {
			return $custom_fields;
		}

		// Get the Custom Fields for CiviCRM Activities.
		$custom_fields = $this->plugin->civicrm->custom_field->get_for_entity_type( 'Activity', '' );

		// --<
		return $custom_fields;

	}



	/**
	 * Listen for queries from the Custom Field class.
	 *
	 * This method responds with an array of "Post IDs" if it detects that the
	 * set of Custom Fields maps to an Activity.
	 *
	 * @since 0.4.5
	 *
	 * @param array|bool $post_ids The existing "Post IDs".
	 * @param array $args The array of CiviCRM Custom Fields params.
	 * @return array|bool $post_id The mapped "Post IDs", or false if not mapped.
	 */
	public function query_post_id( $post_ids, $args ) {

		// Init Activity ID.
		$activity_id = false;

		// Let's tease out the context from the Custom Field data.
		foreach( $args['custom_fields'] AS $field ) {

			// Skip if it is not attached to an Activity.
			if ( $field['entity_table'] != 'civicrm_activity' ) {
				continue;
			}

			// Grab the Activity.
			$activity_id = $field['entity_id'];

			// We can bail now that we know.
			break;

		}

		// Bail if there's no Activity ID.
		if ( $activity_id === false ) {
			return $post_ids;
		}

		// Grab Activity.
		$activity = $this->get_by_id( $activity_id );
		if ( $activity === false ) {
			return $post_ids;
		}

		// Bail if this Activity's Activity Type is not mapped.
		$post_type = $this->is_mapped( $activity, 'create' );
		if ( $post_type === false ) {
			return $post_ids;
		}

		// Get array of IDs for this Post Type.
		$activity_post_ids = $this->plugin->post->get_by_activity_id( $activity_id, $post_type );

		// Bail if no "Post IDs" are found.
		if ( empty( $activity_post_ids ) ) {
			return $post_ids;
		}

		// Add found "Post IDs" to return array.
		if ( is_array( $post_ids ) ) {
			$post_ids = array_merge( $post_ids, $activity_post_ids );
		} else {
			$post_ids = $activity_post_ids;
		}

		// --<
		return $post_ids;

	}



	// -------------------------------------------------------------------------



	/**
	 * Check if a Field Group has been mapped to one or more Activity Post Types.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field_group The Field Group to check.
	 * @return bool|array The array of Post Types if the Field Group has been mapped, or false otherwise.
	 */
	public function is_activity_field_group( $field_group ) {

		// Only do this once per Field Group.
		static $pseudocache;
		if ( isset( $pseudocache[$field_group['ID']] ) ) {
			return $pseudocache[$field_group['ID']];
		}

		// Assume not an Activity Field Group.
		$is_activity_field_group = false;

		// If location rules exist.
		if ( ! empty( $field_group['location'] ) ) {

			// Get mapped Post Types.
			$post_types = $this->plugin->mapping->mappings_for_activity_types_get();

			// Bail if there are no mappings.
			if ( ! empty( $post_types ) ) {

				// Loop through them.
				foreach( $post_types AS $post_type ) {

					// Define params to test for a mapped Post Type.
					$params = [
						'post_type' => $post_type,
					];

					// Do the check.
					$is_visible = $this->plugin->acf->field_group->is_visible( $field_group, $params );

					// If it is, then add to return array.
					if ( $is_visible ) {
						$is_activity_field_group[] = $post_type;
					}

				}

			}

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$field_group['ID']] ) ) {
			$pseudocache[$field_group['ID']] = $is_activity_field_group;
		}

		// --<
		return $is_activity_field_group;

	}



} // Class ends.




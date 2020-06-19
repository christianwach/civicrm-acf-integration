<?php
/**
 * CiviCRM Group Class.
 *
 * Handles CiviCRM Group functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.6.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Group Class.
 *
 * A class that encapsulates CiviCRM Group functionality.
 *
 * @since 0.6.4
 */
class CiviCRM_ACF_Integration_CiviCRM_Group {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.6.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.6.4
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;



	/**
	 * Constructor.
	 *
	 * @since 0.6.4
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
	 * @since 0.6.4
	 */
	public function register_hooks() {

		// Intercept a CiviCRM Group being deleted.
		add_action( 'civicrm_pre', array( $this, 'group_deleted_pre' ), 10, 4 );

		// Intercept CiviCRM's add Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

		// Intercept CiviCRM's delete Contacts from Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

		// Intercept CiviCRM's rejoin Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10, 4 );

	}



	/**
	 * Get all CiviCRM Groups.
	 *
	 * @since 0.6.4
	 *
	 * @param array $groups The array of CiviCRM Groups.
	 */
	public function groups_get_all() {

		// Params to get all Groups.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'is_hidden' => 0,
			'is_active' => 1,
			'options' => [
				'sort' => 'name',
				'limit' => 0,
			],
		];

		// Call the API.
		$result = civicrm_api( 'Group', 'get', $params );

		// Add log entry on failure.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			error_log( print_r( [
				'method' => __METHOD__,
				'result' => $result,
			], true ) );
			return [];
		}

		// Assign Groups data.
		$groups = $result['values'];

		// --<
		return $groups;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept a CiviCRM group prior to it being deleted.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_deleted_pre( $op, $object_name, $group_id, &$civicrm_group ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Group' ) {
			return;
		}

		// Get terms that are synced to this Group ID.
		$terms_for_group = $this->plugin->post->tax->terms_get_by_group_id( $group_id );

		// Bail if there are none.
		if ( empty( $terms_for_group ) ) {
			return;
		}

		// Delete the term meta for each term.
		foreach( $terms_for_group AS $term ) {
			$this->plugin->post->tax->term_meta_delete( $term->term_id );
		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Check if a CiviCRM Contact is a member of a CiviCRM Group.
	 *
	 * @since 0.6.4
	 *
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_id The numeric ID of a CiviCRM Contact.
	 * @return bool $is_member True if the Contact is in the Group, or false otherwise.
	 */
	public function group_contact_exists( $group_id, $contact_id ) {

		// Params to query Group membership.
		$params = [
			'version' => 3,
			'group_id' => $group_id,
			'contact_id' => $contact_id,
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'get', $params );

		// Add log entry on failure.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			error_log( print_r( [
				'method' => __METHOD__,
				'group_id' => $group_id,
				'contact_id' => $contact_id,
				'result' => $result,
			], true ) );
			return false;
		}

		// --<
		return empty( $result['values'] ) ? false : true;

	}



	/**
	 * Add a CiviCRM Contact to a CiviCRM Group.
	 *
	 * @since 0.6.4
	 *
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $result The Group-Contact data, or false on failure.
	 */
	public function group_contact_create( $group_id, $contact_id ) {

		// Remove hook.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10 );

		// Params to add Group membership.
		$params = [
			'version' => 3,
			'group_id' => $group_id,
			'contact_id' => $contact_id,
			'status' => 'Added',
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Reinstate hook.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

		// Add log entry on failure.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			error_log( print_r( [
				'method' => __METHOD__,
				'group_id' => $group_id,
				'contact_id' => $contact_id,
				'result' => $result,
			], true ) );
			return false;
		}

		// --<
		return $result;

	}



	/**
	 * Delete a CiviCRM Contact from a CiviCRM Group.
	 *
	 * @since 0.6.4
	 *
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $result The Group-Contact data, or false on failure.
	 */
	public function group_contact_delete( $group_id, $contact_id ) {

		// Remove hook.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10 );

		// Params to remove Group membership.
		$params = [
			'version' => 3,
			'group_id' => $group_id,
			'contact_id' => $contact_id,
			'status' => 'Removed',
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

		// Add log entry on failure.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			error_log( print_r( [
				'method' => __METHOD__,
				'group_id' => $group_id,
				'contact_id' => $contact_id,
				'result' => $result,
			], true ) );
			return false;
		}

		// --<
		return $result;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Contact is added to a Group.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 */
	public function group_contacts_added( $op, $object_name, $group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contacts.
		if ( empty( $contact_ids ) ) {
			return;
		}

		// Process terms for Group Contacts.
		$this->plugin->post->tax->terms_update_for_group_contacts( $group_id, $contact_ids, 'add' );

	}



	/**
	 * Intercept when a CiviCRM Contact is deleted (or removed) from a Group.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_deleted( $op, $object_name, $group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contacts.
		if ( empty( $contact_ids ) ) {
			return;
		}

		// Process terms for Group Contacts.
		$this->plugin->post->tax->terms_update_for_group_contacts( $group_id, $contact_ids, 'remove' );

	}



	/**
	 * Intercept when a CiviCRM Contact is re-added to a Group.
	 *
	 * The issue here is that CiviCRM fires 'civicrm_pre' with $op = 'delete' regardless
	 * of whether the Contact is being removed or deleted. If a Contact is later re-added
	 * to the Group, then $op != 'create', so we need to intercept $op = 'edit'.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_rejoined( $op, $object_name, $group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contacts.
		if ( empty( $contact_ids ) ) {
			return;
		}

		// Process terms for Group Contacts.
		$this->plugin->post->tax->terms_update_for_group_contacts( $group_id, $contact_ids, 'add' );

	}



} // Class ends.




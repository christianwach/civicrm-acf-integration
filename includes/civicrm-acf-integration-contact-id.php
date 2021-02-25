<?php
/**
 * CiviCRM Contact ID Class.
 *
 * Handles CiviCRM Contact ID functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration CiviCRM Contact ID Class.
 *
 * A class that encapsulates CiviCRM Contact ID functionality.
 *
 * @since 0.8.3
 */
class CiviCRM_ACF_Integration_CiviCRM_Contact_ID extends CiviCRM_ACF_Integration_CiviCRM_Base {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.8.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.8.3
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * Fields which must be handled separately.
	 *
	 * @since 0.8.3
	 * @access public
	 * @var array $fields_handled The array of Fields which must be handled separately.
	 */
	public $fields_handled = [
		'civicrm_contact_id',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.8.3
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
	 * @since 0.8.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.8.3
	 */
	public function register_hooks() {

		// Always register Mapper hooks.
		$this->register_mapper_hooks();

		// Add any Contact ID Fields attached to a Post.
		add_filter( 'civicrm_acf_integration_fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

		// Intercept Post-Contact sync event.
		add_action( 'civicrm_acf_integration_post_contact_sync_to_post', [ $this, 'contact_sync_to_post' ], 10 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.8.3
	 */
	public function register_mapper_hooks() {

		// Listen for events from our Mapper that require Contact ID updates.
		add_action( 'civicrm_acf_integration_mapper_contact_created', [ $this, 'contact_edited' ], 10 );
		add_action( 'civicrm_acf_integration_mapper_contact_edited', [ $this, 'contact_edited' ], 10 );

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.8.3
	 */
	public function unregister_mapper_hooks() {

		// Remove all Mapper listeners.
		remove_action( 'civicrm_acf_integration_mapper_contact_created', [ $this, 'contact_edited' ], 10 );
		remove_action( 'civicrm_acf_integration_mapper_contact_edited', [ $this, 'contact_edited' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a Post has been updated from a Contact via the Mapper.
	 *
	 * Sync any associated ACF Fields mapped to built-in Contact Fields.
	 *
	 * @since 0.8.3
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function contact_sync_to_post( $args ) {

		// The Contact ID is the Object ID.
		$contact_id = $args['objectId'];

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $args['post_id'] );

		// Bail if there are no Contact ID Fields.
		if ( empty( $acf_fields['contact_id'] ) ) {
			return;
		}

		// Let's update each ACF Field in turn.
		foreach( $acf_fields['contact_id'] AS $selector => $dummy ) {
			$this->plugin->acf->field->value_update( $selector, $contact_id, $args['post_id'] );
		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Contact Record has been updated.
	 *
	 * @since 0.8.3
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function contact_edited( $args ) {

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $args['objectId'] );

		// Get originating Entity.
		$entity = $this->plugin->mapper->entity_get();

		// Test if any of this Contact's Contact Types is mapped to a Post Type.
		$post_types = $this->plugin->civicrm->contact->is_mapped( $contact );
		if ( $post_types !== false ) {

			// Handle each Post Type in turn.
			foreach( $post_types AS $post_type ) {

				// Get the Post ID for this Contact.
				$post_id = $this->plugin->civicrm->contact->is_mapped_to_post( $contact, $post_type );

				// Skip if not mapped or Post doesn't yet exist.
				if ( $post_id === false ) {
					continue;
				}

				// Exclude "reverse" edits when a Post is the originator.
				if ( $entity['entity'] === 'post' AND $post_id == $entity['id'] ) {
					continue;
				}

				// Update the ACF Fields for this Post.
				$this->fields_update( $post_id, $args );

			}

		}

		/**
		 * Broadcast that a Contact ID ACF Field may have been edited.
		 *
		 * @since 0.8.3
		 *
		 * @param array $contact The array of CiviCRM Contact data.
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_contact_id_update', $contact, $args );

	}



	/**
	 * Update Contact ID ACF Fields on an Entity mapped to a Contact ID.
	 *
	 * @since 0.8.3
	 *
	 * @param int|str $post_id The ACF "Post ID".
	 * @param array $args The array of CiviCRM params.
	 */
	public function fields_update( $post_id, $args ) {

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if there are no Contact ID Fields.
		if ( empty( $acf_fields['contact_id'] ) ) {
			return;
		}

		// Let's update each ACF Field in turn.
		foreach( $acf_fields['contact_id'] AS $selector => $dummy ) {
			$this->plugin->acf->field->value_update( $selector, $args['objectId'], $post_id );
		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Add any Contact ID Fields that are attached to a Post.
	 *
	 * @since 0.8.3
	 *
	 * @param array $acf_fields The existing ACF Fields array.
	 * @param array $field The ACF Field.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Add if it is a Contact ID Field.
		if ( ! empty( $field['type'] ) AND $field['type'] == 'civicrm_contact_id' ) {
			$acf_fields['contact_id'][$field['name']] = 1;
		}

		// --<
		return $acf_fields;

	}



} // Class ends.




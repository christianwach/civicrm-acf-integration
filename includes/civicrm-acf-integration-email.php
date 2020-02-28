<?php

/**
 * CiviCRM ACF Integration CiviCRM Email Class.
 *
 * A class that encapsulates CiviCRM Email functionality.
 *
 * @package CiviCRM_ACF_Integration
 */
class CiviCRM_ACF_Integration_CiviCRM_Email extends CiviCRM_ACF_Integration_CiviCRM_Base {

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
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * Contact Fields which must be handled separately.
	 *
	 * @since 0.4.1
	 * @access public
	 * @var array $fields_handled The array of Contact Fields which must be handled separately.
	 */
	public $fields_handled = [
		'email',
	];



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
		$this->civicrm = $parent;

		// Init when the CiviCRM object is loaded.
		add_action( 'civicrm_acf_integration_civicrm_loaded', [ $this, 'initialise' ] );

		// Init parent.
		parent::__construct();

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.3
	 */
	public function register_hooks() {

		// Intercept when a CiviCRM Contact's Email has been updated.
		add_action( 'civicrm_acf_integration_mapper_email_edited', [ $this, 'email_edited' ], 10, 2 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's Fields with data from ACF Fields.
	 *
	 * @since 0.4.1
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

			// Maybe update a Contact Field.
			$this->field_handled_update( $field, $value, $contact['id'], $settings );

		}

		// --<
		return $success;

	}



	/**
	 * Update a CiviCRM Contact's Field with data from an ACF Field.
	 *
	 * This Contact Field requires special handling because it is not part
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

		// Get the CiviCRM Custom Field and Contact Field.
		$custom_field_id = $this->plugin->civicrm->contact->custom_field_id_get( $settings );
		$contact_field_name = $this->plugin->civicrm->contact->contact_field_name_get( $settings );

		// Skip if we don't have a synced Custom Field or Contact Field.
		if ( empty( $custom_field_id ) AND empty( $contact_field_name ) ) {
			return;
		}

		// Skip if it's a Custom Field.
		if ( ! empty( $custom_field_id ) ) {
			return;
		}

		// Skip if it's not our specially handled Field.
		if ( ! in_array( $contact_field_name, $this->fields_handled ) ) {
			return;
		}

		// Parse value by field type.
		$value = $this->plugin->acf->field->value_get_for_civicrm( $settings['type'], $value );

		// Update the Contact Field based on type.
		switch ( $contact_field_name ) {

			// Email.
			case 'email' :
				$this->primary_email_update( $contact_id, $value );
				break;

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a CiviCRM Contact's primary email address.
	 *
	 * @since 0.4.1
	 *
	 * @param int $contact_id The numeric ID of the Contact.
	 * @param str $value The email address to update the Contact with.
	 * @return array|bool $email The array of Email data, or false on failure.
	 */
	public function primary_email_update( $contact_id, $value ) {

		// Init return.
		$email = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $email;
		}

		// Get the current primary email.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
			'is_primary' => 1,
		];

		// Call the CiviCRM API.
		$primary_email = civicrm_api( 'Email', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $primary_email['is_error'] ) AND $primary_email['is_error'] == 1 ) {
			return $email;
		}

		// Create if there are no results, update otherwise.
		if ( empty( $primary_email['values'] ) ) {

			// Define params to create new Email.
			$params = [
				'version' => 3,
				'contact_id' => $contact_id,
				'email' => $value,
			];

		} else {

			// There should be only one item.
			$existing_data = array_pop( $primary_email['values'] );

			// Bail if it hasn't changed.
			if ( $existing_data['email'] == $value ) {
				return $existing_data;
			}

			// Define params to update this Email.
			$params = [
				'version' => 3,
				'id' => $primary_email['id'],
				'contact_id' => $contact_id,
				'email' => $value,
			];

		}

		// Call the API.
		$result = civicrm_api( 'Email', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $email;
		}

		// The result set should contain only one item.
		$email = array_pop( $result['values'] );

		// --<
		return $email;

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Email has been updated.
	 *
	 * @since 0.4.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function email_edited( $args ) {

		// Grab the Email data.
		$email = $args['objectRef'];

		// Bail if this is not a Contact's Email.
		if ( empty( $email->contact_id ) ) {
			return;
		}

		// For now, bail if this is not the Primary Email.
		if ( $email->is_primary != '1' ) {
			return;
		}

		// Get the Contact data.
		$contact = $this->plugin->civicrm->contact->get_by_id( $email->contact_id );

		// Bail if this Contact's Contact Type is not mapped.
		$contact_types = $this->plugin->civicrm->contact_type->hierarchy_get_for_contact( $contact );
		$post_type = $this->plugin->civicrm->contact_type->is_mapped( $contact_types );
		if ( $post_type === false ) {
			return;
		}

		// Get the Post ID for this Contact.
		$post_id = $this->plugin->civicrm->contact->is_mapped( $contact );

		// Get the ACF Fields for this Post.
		$acf_fields = $this->plugin->acf->field->fields_get_for_post( $post_id );

		// Bail if there are no Contact Fields.
		if ( empty( $acf_fields['contact'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach( $acf_fields['contact'] AS $selector => $contact_field ) {

			// Skip if it's not the Email field.
			if ( $contact_field != 'email' ) {
				continue;
			}

			// Update it.
			$this->plugin->acf->field->value_update( $selector, $email->email, $post_id );

		}

	}



} // Class ends.




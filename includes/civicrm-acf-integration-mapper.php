<?php
/**
 * Mapper Class.
 *
 * Keeps a WordPress Entity synced with a CiviCRM Entity via ACF Fields.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration Mapper Class.
 *
 * A class that encapsulates methods to keep a WordPress Entity synced with a
 * CiviCRM Entity via ACF Fields.
 *
 * @since 0.2
 */
class CiviCRM_ACF_Integration_Mapper {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Define date format mappings (CiviCRM to ACF).
	 *
	 * @since 0.3
	 * @access public
	 * @var array $date_mappings The CiviCRM to ACF date format mappings.
	 */
	public $date_mappings = [
		'mm/dd/yy' => 'm/d/Y',
		'dd/mm/yy' => 'd/m/Y',
		'yy-mm-dd' => 'Y-m-d',
		'dd-mm-yy' => 'd-m-Y',
		'dd.mm.yy' => 'd.m.Y',
		'M d, yy' => 'M d, Y',
		'd M yy' => 'j M Y',
		'MM d, yy' => 'F j, Y',
		'd MM yy' => 'd F Y',
		'DD, d MM yy' => 'l, d F Y',
		'mm/dd' => 'm/d',
		'dd-mm' => 'd-m',
		'M yy' => 'm Y',
		'M Y' => 'm Y',
		'yy' => 'Y',
	];

	/**
	 * Define time format mappings (CiviCRM to ACF).
	 *
	 * @since 0.3
	 * @access public
	 * @var array $time_mappings The CiviCRM to ACF time format mappings.
	 */
	public $time_mappings = [
		'1' => 'g:i a',
		'2' => 'H:i',
	];



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

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.2
	 */
	public function register_hooks() {

		// Register WordPress hooks.
		$this->hooks_wordpress_add();

		// Register CiviCRM hooks.
		$this->hooks_civicrm_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.2.1
	 */
	public function hooks_wordpress_add() {

		// Intercept Post update in WordPress super-early.
		add_action( 'save_post', [ $this, 'post_saved' ], 1, 3 );

		// Intercept ACF fields prior to save.
		//add_action( 'acf/save_post', [ $this, 'acf_fields_saved' ], 5, 1 );

		// Intercept ACF fields after save.
		add_action( 'acf/save_post', [ $this, 'acf_fields_saved' ], 20, 1 );

		// Intercept new term creation.
		add_action( 'created_term', [ $this, 'term_created' ], 20, 3 );

		// Intercept term updates.
		add_action( 'edit_terms', [ $this, 'term_pre_edit' ], 20, 2 );
		add_action( 'edited_term', [ $this, 'term_edited' ], 20, 3 );

		// Intercept term deletion.
		add_action( 'delete_term', [ $this, 'term_deleted' ], 20, 4 );

	}



	/**
	 * Remove WordPress hooks.
	 *
	 * @since 0.2.1
	 */
	public function hooks_wordpress_remove() {

		// Remove Post update hook.
		remove_action( 'save_post', [ $this, 'post_saved' ], 1 );

		// Remove ACF fields update hook.
		//remove_action( 'acf/save_post', [ $this, 'acf_fields_saved' ], 5 );

		// Remove ACF fields update hook.
		remove_action( 'acf/save_post', [ $this, 'acf_fields_saved' ], 20 );

		// Remove all term-related callbacks.
		remove_action( 'created_term', [ $this, 'intercept_create_term' ], 20 );
		remove_action( 'edit_terms', [ $this, 'intercept_pre_update_term' ], 20 );
		remove_action( 'edited_term', [ $this, 'intercept_update_term' ], 20 );
		remove_action( 'delete_term', [ $this, 'intercept_delete_term' ], 20 );

	}



	/**
	 * Register CiviCRM hooks.
	 *
	 * @since 0.2.1
	 */
	public function hooks_civicrm_add() {

		// Intercept Contact updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'contact_pre_create' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this, 'contact_pre_edit' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'contact_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'contact_edited' ], 10, 4 );

		// Intercept Email updates in CiviCRM.
		add_action( 'civicrm_post', [ $this, 'email_edited' ], 10, 4 );

		// Intercept Website updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'website_pre_edit' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'website_edited' ], 10, 4 );

		// Intercept Phone updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'phone_pre_delete' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'phone_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'phone_edited' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'phone_deleted' ], 10, 4 );

		// Intercept Instant Messenger updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'im_pre_delete' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'im_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'im_edited' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'im_deleted' ], 10, 4 );

		// Intercept Relationship updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'relationship_pre_edit' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'relationship_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'relationship_edited' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'relationship_deleted' ], 10, 4 );

		// Intercept Address updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'address_pre_edit' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'address_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'address_edited' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'address_deleted' ], 10, 4 );

		// Intercept CiviCRM Custom Table updates.
		add_action( 'civicrm_custom', [ $this, 'custom_edited' ], 10, 4 );

		// Intercept Group updates in CiviCRM.
		add_action( 'civicrm_pre', array( $this, 'group_deleted_pre' ), 10, 4 );

		// Intercept Group Membership updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_created' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10, 4 );

		// Intercept Activity updates in CiviCRM.
		add_action( 'civicrm_pre', [ $this, 'activity_pre_create' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this, 'activity_pre_edit' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'activity_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'activity_edited' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'activity_deleted' ], 10, 4 );

	}



	/**
	 * Remove CiviCRM hooks.
	 *
	 * @since 0.2.1
	 */
	public function hooks_civicrm_remove() {

		// Remove Contact update hooks.
		remove_action( 'civicrm_pre', [ $this, 'contact_pre_create' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'contact_pre_edit' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'contact_created' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'contact_edited' ], 10 );

		// Remove Email update hooks.
		remove_action( 'civicrm_post', [ $this, 'email_edited' ], 10 );

		// Remove Website update hooks.
		remove_action( 'civicrm_pre', [ $this, 'website_pre_edit' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'website_edited' ], 10 );

		// Remove Phone update hooks.
		remove_action( 'civicrm_post', [ $this, 'phone_created' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'phone_edited' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'phone_pre_delete' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'phone_deleted' ], 10 );

		// Remove Instant Messenger update hooks.
		remove_action( 'civicrm_post', [ $this, 'im_created' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'im_edited' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'im_pre_delete' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'im_deleted' ], 10 );

		// Remove Relationship update hooks.
		remove_action( 'civicrm_pre', [ $this, 'relationship_pre_edit' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'relationship_created' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'relationship_edited' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'relationship_deleted' ], 10 );

		// Remove Address update hooks.
		remove_action( 'civicrm_pre', [ $this, 'address_pre_edit' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'address_created' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'address_edited' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'address_deleted' ], 10 );

		// Remove CiviCRM Custom Table hooks.
		remove_action( 'civicrm_custom', [ $this, 'custom_edited' ], 10 );

		// Remove Group update hooks.
		remove_action( 'civicrm_pre', array( $this, 'group_deleted_pre' ), 10 );

		// Remove Group Membership update hooks.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_created' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10 );

		// Remove Activity update hooks.
		remove_action( 'civicrm_pre', [ $this, 'activity_pre_create' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'activity_pre_edit' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'activity_created' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'activity_edited' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'activity_deleted' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Fires just before a CiviCRM Entity is created.
	 *
	 * @since 0.2.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function contact_pre_create( $op, $objectName, $objectId, $objectRef ) {

		// Bail if not the context we want.
		if ( $op != 'create' ) {
			return;
		}

		// Bail if this is not a Contact.
		$top_level_types = $this->plugin->civicrm->contact_type->types_get_top_level();
		if ( ! in_array( $objectName, $top_level_types ) ) {
			return;
		}

		// Bail if none of this Contact's Contact Types is mapped.
		$post_types = $this->plugin->civicrm->contact->is_mapped( $objectRef );
		if ( $post_types === false ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Handle each Post Type in turn.
		foreach( $post_types AS $post_type ) {

			// Let's make an array of the CiviCRM params.
			$args = [
				'op' => $op,
				'objectName' => $objectName,
				'objectId' => $objectId,
				'objectRef' => $objectRef,
				'top_level_types' => $top_level_types,
				'post_type' => $post_type,
			];

			/**
			 * Broadcast that a relevant Contact is about to be created.
			 *
			 * @since 0.6.4
			 *
			 * @param array $args The array of CiviCRM params.
			 */
			do_action( 'civicrm_acf_integration_mapper_contact_pre_create', $args );

		}

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Fires just before a CiviCRM Entity is updated.
	 *
	 * @since 0.2.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function contact_pre_edit( $op, $objectName, $objectId, $objectRef ) {

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Bail if this is not a Contact.
		$top_level_types = $this->plugin->civicrm->contact_type->types_get_top_level();
		if ( ! in_array( $objectName, $top_level_types ) ) {
			return;
		}

		// Bail if none of this Contact's Contact Types is mapped.
		$post_types = $this->plugin->civicrm->contact->is_mapped( $objectRef );
		if ( $post_types === false ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Handle each Post Type in turn.
		foreach( $post_types AS $post_type ) {

			// Let's make an array of the CiviCRM params.
			$args = [
				'op' => $op,
				'objectName' => $objectName,
				'objectId' => $objectId,
				'objectRef' => $objectRef,
				'top_level_types' => $top_level_types,
				'post_type' => $post_type,
			];

			/**
			 * Broadcast that a relevant Contact is about to be updated.
			 *
			 * @since 0.4.5
			 *
			 * @param array $args The array of CiviCRM params.
			 */
			do_action( 'civicrm_acf_integration_mapper_contact_pre_edit', $args );

		}

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Create a WordPress Post when a CiviCRM Contact is created.
	 *
	 * @since 0.4.5
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function contact_created( $op, $objectName, $objectId, $objectRef ) {

		// Bail if it's not the "create" operation.
		if ( $op != 'create' ) {
			return;
		}

		// Bail if it's not a Contact.
		if ( ! ( $objectRef instanceof CRM_Contact_DAO_Contact ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a relevant Contact has been created.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_contact_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Update a WordPress Post when a CiviCRM Contact is updated.
	 *
	 * @since 0.2.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function contact_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if it's not an "edit" operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Bail if it's not a Contact.
		if ( ! ( $objectRef instanceof CRM_Contact_DAO_Contact ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
			'extra' => [],
		];

		/*
		 * There are mismatches between the Contact data that is passed in to
		 * this callback and the Contact data that is retrieved by the API -
		 * particularly the "employer_id" which may exist in this data but does
		 * not exist in the data from the API (which has an "employer" field
		 * whose value is the "Name" of the Employer instead) so we save the
		 * "extra" data here for use later.
		 */
		$extra_data = [
			'employer_id',
			//'gender_id',
		];

		// Maybe save extra data.
		foreach( $extra_data AS $property ) {
			if ( isset( $objectRef->$property ) ) {
				$args['extra'][$property] = $objectRef->$property;
			}
		}

		/**
		 * Broadcast that a relevant Contact has been updated.
		 *
		 * Used internally to:
		 *
		 * - Update a WordPress Post
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_contact_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Email is updated.
	 *
	 * @since 0.4.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function email_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not an Email.
		if ( $objectName != 'Email' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Email has been updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_email_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Website is about to be edited.
	 *
	 * @since 0.4.5
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function website_pre_edit( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Website.
		if ( $objectName != 'Website' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Website is about to be updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_website_pre_edit', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Website is updated.
	 *
	 * @since 0.4.5
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function website_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Website.
		if ( $objectName != 'Website' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Website has been updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_website_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Phone is created.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function phone_created( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Phone.
		if ( $objectName != 'Phone' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'create' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Phone has been created.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_phone_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Phone is updated.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function phone_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Phone.
		if ( $objectName != 'Phone' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Phone has been updated.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_phone_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Phone is about to be deleted.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function phone_pre_delete( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Phone.
		if ( $objectName != 'Phone' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Phone is about to be deleted.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_phone_pre_delete', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Phone has been deleted.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function phone_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Address.
		if ( $objectName != 'Phone' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Phone has been deleted.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_phone_deleted', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Instant Messenger is created.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function im_created( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not an Instant Messenger.
		if ( $objectName != 'IM' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'create' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Instant Messenger has been created.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_im_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Instant Messenger is updated.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function im_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not an Instant Messenger.
		if ( $objectName != 'IM' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Instant Messenger has been updated.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_im_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Instant Messenger is about to be deleted.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function im_pre_delete( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not an Instant Messenger.
		if ( $objectName != 'IM' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Instant Messenger is about to be deleted.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_im_pre_delete', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Instant Messenger has been deleted.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function im_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not an Instant Messenger.
		if ( $objectName != 'IM' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Instant Messenger has been deleted.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_im_deleted', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept Custom Field updates.
	 *
	 * @since 0.3
	 *
	 * @param str $op The kind of operation.
	 * @param int $groupID The numeric ID of the Custom Group.
	 * @param int $entityID The numeric ID of the Contact.
	 * @param array $custom_fields The array of Custom Fields.
	 */
	public function custom_edited( $op, $groupID, $entityID, &$custom_fields ) {

		// Bail if there's nothing to see here.
		if ( empty( $custom_fields ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'groupID' => $groupID,
			'entityID' => $entityID,
			'custom_fields' => $custom_fields,
		];

		/**
		 * Broadcast that a set of CiviCRM Custom Fields has been updated.
		 *
		 * Used internally to:
		 *
		 * - Update a WordPress Post
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_custom_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Relationship is about to be edited.
	 *
	 * @since 0.4.5
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function relationship_pre_edit( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Relationship.
		if ( $objectName != 'Relationship' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Relationship is about to be updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_relationship_pre_edit', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact's Relationship has been created.
	 *
	 * @since 0.4.5
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function relationship_created( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Relationship.
		if ( $objectName != 'Relationship' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'create' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Relationship has been created.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_relationship_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact's Relationship has been updated.
	 *
	 * @since 0.4.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function relationship_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Relationship.
		if ( $objectName != 'Relationship' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Contact's Relationship has been updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_relationship_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact's Relationship has been deleted.
	 *
	 * @since 0.4.5
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function relationship_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Relationship.
		if ( $objectName != 'Relationship' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Relationship has been deleted.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_relationship_deleted', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Address is about to be edited.
	 *
	 * @since 0.4.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function address_pre_edit( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Address.
		if ( $objectName != 'Address' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Address is about to be updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_address_pre_edit', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact's Address has been created.
	 *
	 * @since 0.4.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function address_created( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Address.
		if ( $objectName != 'Address' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'create' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Address has been created.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_address_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact's Address has been edited.
	 *
	 * @since 0.4.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function address_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Address.
		if ( $objectName != 'Address' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Address has been updated.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_address_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact's Address has been deleted.
	 *
	 * @since 0.4.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function address_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Bail if this is not a Address.
		if ( $objectName != 'Address' ) {
			return;
		}

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Address has been deleted.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_address_deleted', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept a CiviCRM group prior to it being deleted.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the CiviCRM Group.
	 * @param array $objectRef The array of CiviCRM Group data.
	 */
	public function group_deleted_pre( $op, $objectName, $objectId, &$objectRef ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'Group' ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Group has been deleted.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_group_deleted_pre', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept when a CiviCRM Contact is added to a Group.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the CiviCRM Group.
	 * @param array $objectRef The array of CiviCRM Contact IDs.
	 */
	public function group_contacts_created( $op, $objectName, $objectId, &$objectRef ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contacts.
		if ( empty( $objectRef ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that Contacts have been added to a CiviCRM Group.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_group_contacts_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Contact is deleted (or removed) from a Group.
	 *
	 * @since 0.6.4
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the CiviCRM Group.
	 * @param array $objectRef Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_deleted( $op, $objectName, $objectId, &$objectRef ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contacts.
		if ( empty( $objectRef ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that Contacts have been deleted from a CiviCRM Group.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_group_contacts_deleted', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

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
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the CiviCRM Group.
	 * @param array $objectRef Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_rejoined( $op, $objectName, $objectId, &$objectRef ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contacts.
		if ( empty( $objectRef ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that Contacts have rejoined a CiviCRM Group.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_group_contacts_rejoined', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Fires just before a CiviCRM Activity is created.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function activity_pre_create( $op, $objectName, $objectId, $objectRef ) {

		// Bail if not the context we want.
		if ( $op != 'create' ) {
			return;
		}

		// Bail if this is not an Activity.
		if ( $objectName != 'Activity' ) {
			return;
		}

		// Cast as object for consistency.
		if ( ! is_object( $objectRef ) ) {
			$objectRef = (object) $objectRef;
		}

		// Bail if this Activity's Activity Type is not mapped.
		$post_type = $this->plugin->civicrm->activity->is_mapped( $objectRef );
		if ( $post_type === false ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
			'post_type' => $post_type,
		];

		/**
		 * Broadcast that a relevant Activity is about to be created.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_activity_pre_create', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Fires just before a CiviCRM Entity is updated.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function activity_pre_edit( $op, $objectName, $objectId, $objectRef ) {

		// Bail if not the context we want.
		if ( $op != 'edit' ) {
			return;
		}

		// Bail if this is not an Activity.
		if ( $objectName != 'Activity' ) {
			return;
		}

		// Cast as object for consistency.
		if ( ! is_object( $objectRef ) ) {
			$objectRef = (object) $objectRef;
		}

		// Bail if this Activity's Activity Type is not mapped.
		$post_type = $this->plugin->civicrm->activity->is_mapped( $objectRef );
		if ( $post_type === false ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
			'post_type' => $post_type,
		];

		/**
		 * Broadcast that a relevant Activity is about to be updated.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_activity_pre_edit', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Create a WordPress Post when a CiviCRM Activity is created.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function activity_created( $op, $objectName, $objectId, $objectRef ) {

		// Bail if it's not the "create" operation.
		if ( $op != 'create' ) {
			return;
		}

		// Bail if this is not an Activity.
		if ( $objectName != 'Activity' ) {
			return;
		}

		// Cast as object for consistency.
		if ( ! is_object( $objectRef ) ) {
			$objectRef = (object) $objectRef;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a relevant Activity has been created.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_activity_created', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Update a WordPress Post when a CiviCRM Activity is updated.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function activity_edited( $op, $objectName, $objectId, $objectRef ) {

		// Bail if it's not an "edit" operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Bail if this is not an Activity.
		if ( $objectName != 'Activity' ) {
			return;
		}

		// Bail if it's not an Activity.
		if ( ! ( $objectRef instanceof CRM_Activity_DAO_Activity ) ) {
			return;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the CiviCRM params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a relevant Activity has been updated.
		 *
		 * Used internally to:
		 *
		 * - Update a WordPress Post
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_activity_edited', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	/**
	 * Intercept when a CiviCRM Activity has been deleted.
	 *
	 * @since 0.7.3
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function activity_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Bail if not the context we want.
		if ( $op != 'delete' ) {
			return;
		}

		// Bail if this is not an Activity.
		if ( $objectName != 'Activity' ) {
			return;
		}

		// Cast as object for consistency.
		if ( ! is_object( $objectRef ) ) {
			$objectRef = (object) $objectRef;
		}

		// Remove WordPress callbacks to prevent recursion.
		$this->hooks_wordpress_remove();

		// Let's make an array of the params.
		$args = [
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		];

		/**
		 * Broadcast that a CiviCRM Activity has been deleted.
		 *
		 * @since 0.7.3
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_activity_deleted', $args );

		// Reinstate WordPress callbacks.
		$this->hooks_wordpress_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Intercept the Post saved operation.
	 *
	 * @since 0.2
	 *
	 * @param int $post_id The ID of the Post or revision.
	 * @param int $post The Post object.
	 * @param bool $update True if the Post is being updated, false if new.
	 */
	public function post_saved( $post_id, $post, $update ) {

		// Bail if there was a Multisite switch.
		if ( is_multisite() AND ms_is_switched() ) {
			return;
		}

		// Remove CiviCRM callbacks to prevent recursion.
		$this->hooks_civicrm_remove();

		// Let's make an array of the params.
		$args = [
			'post_id' => $post_id,
			'post' => $post,
			'update' => $update,
		];

		/**
		 * Broadcast that a WordPress Post has been saved.
		 *
		 * @since 0.4.5
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_post_saved', $args );

		// Reinstate CiviCRM callbacks.
		$this->hooks_civicrm_add();

	}



	/**
	 * Intercept the ACF Fields saved operation.
	 *
	 * @since 0.2
	 *
	 * @param integer $post_id The ID of the Post or revision.
	 */
	public function acf_fields_saved( $post_id ) {

		// Bail if there was a Multisite switch.
		if ( is_multisite() AND ms_is_switched() ) {
			return;
		}

		// Remove CiviCRM callbacks to prevent recursion.
		$this->hooks_civicrm_remove();

		// Let's make an array of the params.
		$args = [
			'post_id' => $post_id,
		];

		/**
		 * Broadcast that ACF Fields have been saved for a Post.
		 *
		 * @since 0.4.5
		 * @since 0.6.1 Params reduced to array.
		 *
		 * @param array $args The array of CiviCRM params.
		 */
		do_action( 'civicrm_acf_integration_mapper_acf_fields_saved', $args );

		// Reinstate CiviCRM callbacks.
		$this->hooks_civicrm_add();

	}



	// -------------------------------------------------------------------------



	/**
	 * Hook into the creation of a term.
	 *
	 * @since 0.6.4
	 *
	 * @param array $term_id The numeric ID of the new term.
	 * @param array $tt_id The numeric ID of the new term.
	 * @param string $taxonomy Should be (an array containing) taxonomy names.
	 */
	public function term_created( $term_id, $tt_id, $taxonomy ) {

		// Bail if there was a Multisite switch.
		if ( is_multisite() AND ms_is_switched() ) {
			return;
		}

		// Remove CiviCRM callbacks to prevent recursion.
		$this->hooks_civicrm_remove();

		// Let's make an array of the params.
		$args = [
			'term_id' => $term_id,
			'tt_id' => $tt_id,
			'taxonomy' => $taxonomy,
		];

		/**
		 * Broadcast that a WordPress Term has been created.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of WordPress params.
		 */
		do_action( 'civicrm_acf_integration_mapper_term_created', $args );

		// Reinstate CiviCRM callbacks.
		$this->hooks_civicrm_add();

	}



	/**
	 * Hook into updates to a term before the term is updated.
	 *
	 * @since 0.6.4
	 *
	 * @param int $term_id The numeric ID of the new term.
	 * @param string $taxonomy The taxonomy containing the term.
	 */
	public function term_pre_edit( $term_id, $taxonomy = null ) {

		// Bail if there was a Multisite switch.
		if ( is_multisite() AND ms_is_switched() ) {
			return;
		}

		// Remove CiviCRM callbacks to prevent recursion.
		$this->hooks_civicrm_remove();

		// Let's make an array of the params.
		$args = [
			'term_id' => $term_id,
			'taxonomy' => $taxonomy,
		];

		/**
		 * Broadcast that a WordPress Term is about to be edited.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of WordPress params.
		 */
		do_action( 'civicrm_acf_integration_mapper_term_pre_edit', $args );

		// Reinstate CiviCRM callbacks.
		$this->hooks_civicrm_add();

	}



	/**
	 * Hook into updates to a term.
	 *
	 * @since 0.6.4
	 *
	 * @param int $term_id The numeric ID of the edited term.
	 * @param array $tt_id The numeric ID of the edited term taxonomy.
	 * @param string $taxonomy Should be (an array containing) the taxonomy.
	 */
	public function term_edited( $term_id, $tt_id, $taxonomy ) {

		// Bail if there was a Multisite switch.
		if ( is_multisite() AND ms_is_switched() ) {
			return;
		}

		// Remove CiviCRM callbacks to prevent recursion.
		$this->hooks_civicrm_remove();

		// Let's make an array of the params.
		$args = [
			'term_id' => $term_id,
			'tt_id' => $tt_id,
			'taxonomy' => $taxonomy,
		];

		/**
		 * Broadcast that a WordPress Term has been edited.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of WordPress params.
		 */
		do_action( 'civicrm_acf_integration_mapper_term_edited', $args );

		// Reinstate CiviCRM callbacks.
		$this->hooks_civicrm_add();

	}



	/**
	 * Hook into deletion of a term.
	 *
	 * @since 0.6.4
	 *
	 * @param int $term_id The numeric ID of the deleted term.
	 * @param array $tt_id The numeric ID of the deleted term taxonomy.
	 * @param string $taxonomy Name of the taxonomy.
	 * @param object $deleted_term The deleted term object.
	 */
	public function term_deleted( $term_id, $tt_id, $taxonomy, $deleted_term ) {

		// Bail if there was a Multisite switch.
		if ( is_multisite() AND ms_is_switched() ) {
			return;
		}

		// Remove CiviCRM callbacks to prevent recursion.
		$this->hooks_civicrm_remove();

		// Let's make an array of the params.
		$args = [
			'term_id' => $term_id,
			'tt_id' => $tt_id,
			'taxonomy' => $taxonomy,
			'deleted_term' => $deleted_term,
		];

		/**
		 * Broadcast that a WordPress Term has been deleted.
		 *
		 * @since 0.6.4
		 *
		 * @param array $args The array of WordPress params.
		 */
		do_action( 'civicrm_acf_integration_mapper_term_deleted', $args );

		// Reinstate CiviCRM callbacks.
		$this->hooks_civicrm_add();

	}



} // Class ends.




<?php
/**
 * WordPress Post Type Class.
 *
 * Handles WordPress Post Type functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration WordPress Post Type Class
 *
 * A class that encapsulates WordPress Post Type functionality.
 *
 * @since 0.2
 */
class CiviCRM_ACF_Integration_Post_Type {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;



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

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all Post Types.
	 *
	 * @since 0.2
	 *
	 * @return array $post_types The array of Post Types.
	 */
	public function post_types_get_all() {

		// Get CPTs with admin UI.
		$args = [
			'public'   => true,
			'show_ui' => true,
		];

		$output = 'objects'; // Names or objects, note names is the default.
		$operator = 'and'; // Operator may be 'and' or 'or'.

		// Get Post Types.
		$post_types = get_post_types( $args, $output, $operator );

		/**
		 * Filter the Post Types.
		 *
		 * This filter can be used, for example, to exclude certain Post Types.
		 *
		 * @param array $post_types The existing Post Types.
		 * @param return $post_types The modified Post Types.
		 */
		$post_types = apply_filters( 'civicrm_acf_integration_post_types_get_all', $post_types );

		// --<
		return $post_types;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all Post Types that a Contact Type may be synced with.
	 *
	 * @since 0.2
	 *
	 * @param int $contact_type_id The numeric ID of the Contact Type.
	 * @return array $post_types The array of Post Types.
	 */
	public function post_types_get_for_contact_type( $contact_type_id ) {

		// Init return.
		$filtered = [];

		// Get all Post Types.
		$post_types = $this->post_types_get_all();

		// Get all used Post Types.
		$used_post_types = $this->plugin->mapping->mappings_get_all();

		// Get existing Post Type.
		$existing_post_type = '';
		if ( $contact_type_id !== 0 ) {
			$existing_post_type = $this->plugin->mapping->mapping_for_contact_type_get( $contact_type_id );
		}

		// Retain only those which are unused, plus the existing one.
		if ( count( $post_types ) > 0 ) {
			foreach( $post_types AS $post_type ) {
				$used = in_array( $post_type->name, $used_post_types );
				$mine = ( $post_type->name == $existing_post_type ) ? true : false;
				if ( ! $used OR $mine ) {
					$filtered[] = $post_type;
				}
			}
		}

		// --<
		return $filtered;

	}



	/**
	 * Get the Post Type that is mapped to a Contact Type.
	 *
	 * @since 0.2
	 *
	 * @param int $contact_type_id The numeric ID of the Contact Type.
	 * @return str|bool $post_type The name of Post Type, or false if not mapped.
	 */
	public function get_for_contact_type( $contact_type_id ) {

		// --<
		return $this->plugin->mapping->mapping_for_contact_type_get( $contact_type_id );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all Post Types that an Activity Type may be synced with.
	 *
	 * @since 0.7.3
	 *
	 * @param int $activity_type_id The numeric ID of the Activity Type.
	 * @return array $post_types The array of Post Types.
	 */
	public function post_types_get_for_activity_type( $activity_type_id ) {

		// Init return.
		$filtered = [];

		// Get all Post Types.
		$post_types = $this->post_types_get_all();

		// Get all used Post Types.
		$used_post_types = $this->plugin->mapping->mappings_get_all();

		// Get existing Post Type.
		$existing_post_type = '';
		if ( $activity_type_id !== 0 ) {
			$existing_post_type = $this->plugin->mapping->mapping_for_activity_type_get( $activity_type_id );
		}

		// Retain only those which are unused, plus the existing one.
		if ( count( $post_types ) > 0 ) {
			foreach( $post_types AS $post_type ) {
				$used = in_array( $post_type->name, $used_post_types );
				$mine = ( $post_type->name == $existing_post_type ) ? true : false;
				if ( ! $used OR $mine ) {
					$filtered[] = $post_type;
				}
			}
		}

		// --<
		return $filtered;

	}



	/**
	 * Get the Post Type that is mapped to an Activity Type.
	 *
	 * @since 0.7.3
	 *
	 * @param int $activity_type_id The numeric ID of the Activity Type.
	 * @return str|bool $post_type The name of Post Type, or false if not mapped.
	 */
	public function get_for_activity_type( $activity_type_id ) {

		// --<
		return $this->plugin->mapping->mapping_for_activity_type_get( $activity_type_id );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the number of Posts in a WordPress Post Type.
	 *
	 * @since 0.6.4
	 *
	 * @param str $post_type The name of the WordPress Post Type.
	 * @return int $count The number of Contacts of that Type.
	 */
	public function post_count( $post_type ) {

		// Get the count of all Posts of the Post Type.
		$counts = wp_count_posts( $post_type );

		// Cast as array.
		$counts = (array) $counts;

		// Bail if there are none.
		if ( empty( $counts ) ) {
			return 0;
		}

		// We don't care about the post status.
		$sum = array_sum( array_values( $counts ) );

		// --<
		return $sum;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all Post Types that are mapped to an Entity Type.
	 *
	 * @since 0.6.4
	 *
	 * @param str $entity_type The Entity to which the Post Types are mapped.
	 * @return array $post_types The array of mapped Post Types.
	 */
	public function get_mapped( $entity_type = 'contact' ) {

		// Init return.
		$post_types = [];

		// Get all Post Types.
		$all_post_types = $this->plugin->post_type->post_types_get_all();

		// Get all used Post Types by Entity.
		switch ( $entity_type ) {

			case 'activity':
				$synced_post_types = $this->plugin->mapping->mappings_for_activity_types_get();
				break;

			case 'contact':
				$synced_post_types = $this->plugin->mapping->mappings_for_contact_types_get();
				break;

		}

		// Loop through them and get the ones we want.
		foreach( $all_post_types AS $post_type ) {
			if ( in_array( $post_type->name, $synced_post_types ) ) {
				$post_types[] = $post_type;
			}
		}

		// --<
		return $post_types;

	}



	/**
	 * Check if a Post Type is mapped to a Contact Type.
	 *
	 * @since 0.2
	 *
	 * @param str $post_type The name of the Post Type.
	 * @return bool $is_linked True if the Post Type is mapped, false otherwise.
	 */
	public function is_mapped_to_contact_type( $post_type ) {

		// Assume not.
		$is_linked = false;

		// Get mapped Post Types.
		$mapped_post_types = $this->plugin->mapping->mappings_for_contact_types_get();

		// Bail if there are no mappings.
		if ( empty( $mapped_post_types ) ) {
			return $is_linked;
		}

		// Override if this Post Type is mapped.
		if ( in_array( $post_type, $mapped_post_types ) ) {
			$is_linked = true;
		}

		// --<
		return $is_linked;

	}



	/**
	 * Check if a Post Type is mapped to an Activity Type.
	 *
	 * @since 0.7.3
	 *
	 * @param str $post_type The name of the Post Type.
	 * @return bool $is_linked True if the Post Type is mapped, false otherwise.
	 */
	public function is_mapped_to_activity_type( $post_type ) {

		// Assume not.
		$is_linked = false;

		// Get mapped Post Types.
		$mapped_post_types = $this->plugin->mapping->mappings_for_activity_types_get();

		// Bail if there are no mappings.
		if ( empty( $mapped_post_types ) ) {
			return $is_linked;
		}

		// Override if this Post Type is mapped.
		if ( in_array( $post_type, $mapped_post_types ) ) {
			$is_linked = true;
		}

		// --<
		return $is_linked;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the singular label for a given Post Type.
	 *
	 * @since 0.7
	 *
	 * @param str $post_type The name of the Post Type.
	 * @return str $label The singular label for the Post Type.
	 */
	public function singular_label_get( $post_type ) {

		// Get Post Type data.
		$post_type_data = get_post_type_object( $post_type );

		// Default to the (usually plural) label.
		$label = $post_type_data->label;

		// If the labels array is populated, override with singular label.
		if ( ! empty( $post_type_data->labels->singular_name ) ) {
			$label = $post_type_data->labels->singular_name;
		}

		// --<
		return $label;

	}



} // Class ends.




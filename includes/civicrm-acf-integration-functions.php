<?php
/**
 * Theme functions.
 *
 * Global scope functions that are available to the theme can be found here.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * Get "Age" as a string for a given ACF Field.
 *
 * @since 0.6.2
 *
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return str $age The age expressed as a string.
 */
function cacf_get_age_from_acf_field( $selector, $post_id = null ) {

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Try the global if no Post ID.
	if ( is_null( $post_id ) ) {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return '';
		}
		$post_id = $post->ID;
	}

	// Get field settings.
	$acf_settings = get_field_object( $selector, $post_id );

	// Bail if we don't get any settings.
	if ( empty( $acf_settings ) ) {
		return '';
	}

	// Bail if it's not a "Date" or "Date Time" ACF Field.
	if ( ! in_array( $acf_settings['type'], [ 'date_picker', 'date_time_picker' ] ) ) {
		return '';
	}

	// Get Field value.
	$value = get_field( $selector );

	// Bail if it's empty.
	if ( empty( $value ) ) {
		return '';
	}

	// Convert ACF Field value to CiviCRM "Ymdhis" format.
	$datetime = DateTime::createFromFormat( $acf_settings['return_format'], $value );
	$date = $datetime->format( 'Ymdhis' );

	// Get "Age" as string.
	$age = $cacf->civicrm->contact_field->date_age_get( $date );

	// --<
	return $age;

}




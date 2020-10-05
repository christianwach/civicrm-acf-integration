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



/**
 * Get the Phone Numbers by Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return str $phones The formatted Phone Numbers.
 */
function cacf_get_phone_numbers( $selector, $post_id = null ) {

	// Init return.
	$phones = '';

	// Get the Phone Records.
	$records = cacf_get_phone_records( $selector, $post_id );

	// Bail if we don't get a Phone Record.
	if ( empty( $records ) ) {
		return $phones;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get Location Types.
	$location_types = $cacf->civicrm->phone->location_types_get();

	// Build Location Types array for reference.
	$locations = [];
	foreach( $location_types AS $location_type ) {
		$locations[$location_type['id']] = esc_html( $location_type['display_name'] );
	}

	// Get Phone Types.
	$phone_types = $cacf->civicrm->phone->phone_types_get();

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the Phone Number is empty.
		if ( empty( $record['field_phone_number'] ) ) {
			continue;
		}

		// Build string from Location, Phone Types and Phone Number.
		$phone = sprintf(
			__( '%1$s %2$s: %3$s', 'civicrm-acf-integration' ),
			strval( $locations[$record['field_phone_location']] ),
			strval( $phone_types[$record['field_phone_type']] ),
			strval( $record['field_phone_number'] )
		);

		// Maybe add Extension.
		if ( ! empty( $record['field_phone_extension'] ) ) {
			$phone = sprintf(
				__( '%1$s Ext. %2$s', 'civicrm-acf-integration' ),
				$phone,
				strval( $record['field_phone_extension'] )
			);
		}

		// Add to filtered array.
		$filtered[] = $phone;

	}

	// Open the list.
	$phones .= '<ul><li>';

	// Format the list.
	$phones .= implode( '</li><li>', $filtered );

	// Close the list.
	$phones .= '</li></ul>';

	/**
	 * Allow the Phone Numbers to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $phones The existing Phone Numbers.
	 * @param str $selector The ACF field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return str $phones The modified Phone Numbers.
	 */
	$phones = apply_filters( 'cacf_get_phone_numbers', $phones, $selector, $post_id );

	// --<
	return $phones;

}



/**
 * Get the Phone Numbers by Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Phone Location Type.
 * @param int $phone_type_id The numeric ID of the CiviCRM Phone Phone Type.
 * @param str $return Return an HTML list or comma-delimited string. Default 'list'.
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return str $phone The formatted Phone Number.
 */
function cacf_get_phone_numbers_by_type_ids( $location_type_id, $phone_type_id, $return = 'list', $selector, $post_id = null ) {

	// Init return.
	$phones = '';

	// Get the Phone Records.
	$records = cacf_get_phone_records_by_type_ids( $location_type_id, $phone_type_id, $selector, $post_id );

	// Bail if we don't get a Phone Record.
	if ( empty( $records ) ) {
		return $phones;
	}

	// Init filtered array.
	$filtered = [];

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the Phone Number is empty.
		if ( empty( $record['field_phone_number'] ) ) {
			continue;
		}

		// Assign Phone Number to return.
		$phone = strval( $record['field_phone_number'] );

		// Maybe add Extension.
		if ( ! empty( $record['field_phone_extension'] ) ) {
			$phone = sprintf(
				__( '%1$s Ext. %2$s', 'civicrm-acf-integration' ),
				$phone,
				strval( $record['field_phone_extension'] )
			);
		}

		// Add to filtered array.
		$filtered[] = $phone;

	}

	// Bail if we don't get any Phone Records.
	if ( empty( $filtered ) ) {
		return $phones;
	}

	// Format the return.
	if ( $return === 'list' ) {

		// Open the list.
		$phones .= '<ul><li>';

		// Format the list.
		$phones .= implode( '</li><li>', $filtered );

		// Close the list.
		$phones .= '</li></ul>';

	} else {

		// Format the string.
		$phones .= implode( ', ', $filtered );

	}

	/**
	 * Allow the Phone Numbers to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $phones The existing Phone Numbers.
	 * @param str $selector The ACF field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return str $phones The modified Phone Numbers.
	 */
	$phones = apply_filters( 'cacf_get_phone_numbers_by_type_ids', $phones, $selector, $post_id );

	// --<
	return $phones;

}



/**
 * Get Phone Records by Type(s) from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Phone Location Type.
 * @param int $phone_type_id The numeric ID of the CiviCRM Phone Phone Type.
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return array $phones The array of Phone Record data.
 */
function cacf_get_phone_records_by_type_ids( $location_type_id, $phone_type_id, $selector, $post_id = null ) {

	// Init return.
	$phones = [];

	// Get the Phone Records.
	$records = cacf_get_phone_records( $selector, $post_id );

	// Bail if we don't get a Phone Record.
	if ( empty( $records ) ) {
		return $phones;
	}

	// If we are looking for just the Location Type ID.
	if ( ! empty( $location_type_id ) AND empty( $phone_type_id ) ) {

		// Try and find the Phone Records that match the Location Type ID.
		foreach( $records AS $record ) {
			if ( $record['field_phone_location'] == $location_type_id ) {
				$phones[] = $record;
			}
		}

	}

	// If we are looking for just the Phone Type ID.
	if ( empty( $location_type_id ) AND ! empty( $phone_type_id ) ) {

		// Try and find the Phone Records that match the Phone Type ID.
		foreach( $records AS $record ) {
			if ( $record['field_phone_type'] == $phone_type_id ) {
				$phones[] = $record;
			}
		}

	}

	// If we are looking for just the Phone Type ID.
	if ( ! empty( $location_type_id ) AND ! empty( $phone_type_id ) ) {

		// Try and find the Phone Records that match both the Location and Phone Type IDs.
		foreach( $records AS $record ) {
			if (
				$record['field_phone_location'] == $location_type_id
				AND
				$record['field_phone_type'] == $phone_type_id
			) {
				$phones[] = $record;
			}
		}

	}

	/**
	 * Allow the Phone Records to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param array $phones The existing Phone Records.
	 * @param int $location_type_id The numeric ID of the CiviCRM Phone Location Type.
	 * @param int $phone_type_id The numeric ID of the CiviCRM Phone Phone Type.
	 * @param str $selector The ACF field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $phones The modified Phone Records.
	 */
	$phones = apply_filters( 'cacf_get_phone_records_by_type_ids', $phones, $location_type_id, $phone_type_id, $selector, $post_id );

	// --<
	return $phones;

}



/**
 * Get the "Primary" Phone Number from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return str $phone The formatted Phone Number.
 */
function cacf_get_primary_phone_number( $selector, $post_id = null ) {

	// Init return.
	$phone = '';

	// Get the Phone Record.
	$record = cacf_get_primary_phone_record( $selector, $post_id );

	// Bail if we don't get a Phone Record.
	if ( empty( $record ) ) {
		return $phone;
	}

	// Bail if the Phone Number is empty.
	if ( empty( $record['field_phone_number'] ) ) {
		return $phone;
	}

	// Assign Phone Number to return.
	$phone = strval( $record['field_phone_number'] );

	// Maybe add Extension.
	if ( ! empty( $record['field_phone_extension'] ) ) {
		$phone = sprintf(
			__( '%1$s Ext. %2$s', 'civicrm-acf-integration' ),
			$phone,
			strval( $record['field_phone_extension'] )
		);
	}

	/**
	 * Allow the Phone Number to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $phone The existing Primary Phone Number.
	 * @param str $selector The ACF field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return str $phone The modified Primary Phone Number.
	 */
	$phone = apply_filters( 'cacf_get_primary_phone_number', $phone, $selector, $post_id );

	// --<
	return $phone;

}



/**
 * Get the "Primary" Phone Record from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return array $phone The array of Phone Record data.
 */
function cacf_get_primary_phone_record( $selector, $post_id = null ) {

	// Init return.
	$phone = [];

	// Get the Phone Record.
	$records = cacf_get_phone_records( $selector, $post_id );

	// Now try and find the Primary Phone Record.
	foreach( $records AS $record ) {
		if ( $record['field_phone_primary'] == '1' ) {
			$phone = $record;
			break;
		}
	}

	/**
	 * Allow the Phone Record to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param array $phone The existing Primary Phone data.
	 * @param str $selector The ACF field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $phone The modified Primary Phone data.
	 */
	$phone = apply_filters( 'cacf_get_primary_phone_record', $phone, $selector, $post_id );

	// --<
	return $phone;

}


/**
 * Get the Phone Records from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int $post_id The numeric ID of the WordPress Post.
 * @return array $records The array of Phone Record data.
 */
function cacf_get_phone_records( $selector, $post_id = null ) {

	// Init return.
	$records = [];

	// Try the global if no Post ID.
	if ( empty( $post_id ) ) {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return $phone;
		}
		$post_id = $post->ID;
	}

	// Get field settings.
	$acf_settings = get_field_object( $selector, $post_id );

	// Bail if we don't get any settings.
	if ( empty( $acf_settings ) ) {
		return $records;
	}

	 // Bail if it's not a CiviCRM Phone Field.
	 if ( $acf_settings['type'] != 'civicrm_phone' ) {
		return $records;
	 }

	// Get Field value.
	$records = get_field( $selector, $post_id );

	/**
	 * Allow the Phone Record to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param array $records The existing Phone Record data.
	 * @param str $selector The ACF field selector.
	 * @param int $post_id The numeric ID of the WordPress Post.
	 * @return array $records The modified Phone Record data.
	 */
	$records = apply_filters( 'cacf_get_phone_records', $records, $selector, $post_id );

	// --<
	return $records;

}



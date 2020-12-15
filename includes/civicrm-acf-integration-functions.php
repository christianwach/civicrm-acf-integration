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
 * @param int|str $post_id The ACF "Post ID".
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



// -----------------------------------------------------------------------------



/**
 * Get the Phone Numbers from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
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
			(string) $locations[$record['field_phone_location']],
			(string) $phone_types[$record['field_phone_type']],
			(string) $record['field_phone_number']
		);

		// Maybe add Extension.
		if ( ! empty( $record['field_phone_extension'] ) ) {
			$phone = sprintf(
				__( '%1$s Ext. %2$s', 'civicrm-acf-integration' ),
				$phone,
				(string) $record['field_phone_extension']
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
	 * @param int|str $post_id The ACF "Post ID".
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
 * @param int $phone_type_id The numeric ID of the CiviCRM Phone Type.
 * @param str $return Return an HTML list or comma-delimited string. Default 'list'.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
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

	// If we are looking for all records.
	if ( empty( $location_type_id ) AND empty( $phone_type_id ) ) {
		return cacf_get_phone_numbers( $selector, $post_id );
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
		$phone = (string) $record['field_phone_number'];

		// Maybe add Extension.
		if ( ! empty( $record['field_phone_extension'] ) ) {
			$phone = sprintf(
				__( '%1$s Ext. %2$s', 'civicrm-acf-integration' ),
				$phone,
				(string) $record['field_phone_extension']
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
	 * @param int|str $post_id The ACF "Post ID".
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
 * @param int|str $post_id The ACF "Post ID".
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

	// If we are looking for a combination of Location Type ID and Phone Type ID.
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

	// If we are looking for a neither Location Type ID nor Phone Type ID.
	if ( empty( $location_type_id ) AND empty( $phone_type_id ) ) {
		$phones = $records;
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
	 * @param int|str $post_id The ACF "Post ID".
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
 * @param int|str $post_id The ACF "Post ID".
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
	$phone = (string) $record['field_phone_number'];

	// Maybe add Extension.
	if ( ! empty( $record['field_phone_extension'] ) ) {
		$phone = sprintf(
			__( '%1$s Ext. %2$s', 'civicrm-acf-integration' ),
			$phone,
			(string) $record['field_phone_extension']
		);
	}

	/**
	 * Allow the Phone Number to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $phone The existing Primary Phone Number.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
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
 * @param int|str $post_id The ACF "Post ID".
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
	 * @param int|str $post_id The ACF "Post ID".
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
 * @param int|str $post_id The ACF "Post ID".
 * @return array $records The array of Phone Record data.
 */
function cacf_get_phone_records( $selector, $post_id = null ) {

	// Init return.
	$records = [];

	// Try the global if no Post ID.
	if ( empty( $post_id ) ) {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return $records;
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
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $records The modified Phone Record data.
	 */
	$records = apply_filters( 'cacf_get_phone_records', $records, $selector, $post_id );

	// --<
	return $records;

}



// -----------------------------------------------------------------------------



/**
 * Get the Instant Messengers from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $ims The formatted Instant Messenger "Names".
 */
function cacf_get_ims( $selector, $post_id = null ) {

	// Init return.
	$ims = '';

	// Get the Instant Messenger Records.
	$records = cacf_get_im_records( $selector, $post_id );

	// Bail if we don't get an Instant Messenger Record.
	if ( empty( $records ) ) {
		return $ims;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get Location Types.
	$location_types = $cacf->civicrm->im->location_types_get();

	// Build Location Types array for reference.
	$locations = [];
	foreach( $location_types AS $location_type ) {
		$locations[$location_type['id']] = esc_html( $location_type['display_name'] );
	}

	// Get Instant Messenger Providers.
	$im_providers = $cacf->civicrm->im->im_providers_get();

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the Instant Messenger is empty.
		if ( empty( $record['field_im_name'] ) ) {
			continue;
		}

		// Build string from Locations, Providers and Instant Messenger.
		$im = sprintf(
			__( '%1$s (%2$s): %3$s', 'civicrm-acf-integration' ),
			(string) $im_providers[$record['field_im_provider']],
			(string) $locations[$record['field_im_location']],
			(string) $record['field_im_name']
		);

		// Add to filtered array.
		$filtered[] = $im;

	}

	// Open the list.
	$ims .= '<ul><li>';

	// Format the list.
	$ims .= implode( '</li><li>', $filtered );

	// Close the list.
	$ims .= '</li></ul>';

	/**
	 * Allow the Instant Messengers to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $ims The existing Instant Messenger "Names".
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $ims The modified Instant Messenger "Names".
	 */
	$ims = apply_filters( 'cacf_get_im_names', $ims, $selector, $post_id );

	// --<
	return $ims;

}



/**
 * Get the Instant Messengers by Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Instant Messenger Location Type.
 * @param int $im_provider_id The numeric ID of the Instant Messenger Provider.
 * @param str $return Return an HTML list or comma-delimited string. Default 'list'.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $im The formatted Instant Messenger.
 */
function cacf_get_ims_by_type_ids( $location_type_id, $im_provider_id, $return = 'list', $selector, $post_id = null ) {

	// Init return.
	$ims = '';

	// Get the Instant Messenger Records.
	$records = cacf_get_im_records_by_type_ids( $location_type_id, $im_provider_id, $selector, $post_id );

	// Bail if we don't get an Instant Messenger Record.
	if ( empty( $records ) ) {
		return $ims;
	}

	// If we are looking for all records.
	if ( empty( $location_type_id ) AND empty( $im_provider_id ) ) {
		return cacf_get_ims( $selector, $post_id );
	}

	// Init filtered array.
	$filtered = [];

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the Instant Messenger is empty.
		if ( empty( $record['field_im_name'] ) ) {
			continue;
		}

		// Assign Instant Messenger to return.
		$im = (string) $record['field_im_name'];

		// Add to filtered array.
		$filtered[] = $im;

	}

	// Bail if we don't get any Instant Messenger Records.
	if ( empty( $filtered ) ) {
		return $ims;
	}

	// Format the return.
	if ( $return === 'list' ) {

		// Open the list.
		$ims .= '<ul><li>';

		// Format the list.
		$ims .= implode( '</li><li>', $filtered );

		// Close the list.
		$ims .= '</li></ul>';

	} else {

		// Format the string.
		$ims .= implode( ', ', $filtered );

	}

	/**
	 * Allow the Instant Messengers to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $ims The existing Instant Messengers.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $ims The modified Instant Messengers.
	 */
	$ims = apply_filters( 'cacf_get_ims_by_type_ids', $ims, $selector, $post_id );

	// --<
	return $ims;

}



/**
 * Get Instant Messenger Records by Type(s) from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Instant Messenger Location Type.
 * @param int $im_provider_id The numeric ID of the CiviCRM IM Provider.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return array $ims The array of Instant Messenger Record data.
 */
function cacf_get_im_records_by_type_ids( $location_type_id, $im_provider_id, $selector, $post_id = null ) {

	// Init return.
	$ims = [];

	// Get the Instant Messenger Records.
	$records = cacf_get_im_records( $selector, $post_id );

	// Bail if we don't get an Instant Messenger Record.
	if ( empty( $records ) ) {
		return $ims;
	}

	// If we are looking for just the Location Type ID.
	if ( ! empty( $location_type_id ) AND empty( $im_provider_id ) ) {

		// Try and find the Instant Messenger Records that match the Location Type ID.
		foreach( $records AS $record ) {
			if ( $record['field_im_location'] == $location_type_id ) {
				$ims[] = $record;
			}
		}

	}

	// If we are looking for just the Provider ID.
	if ( empty( $location_type_id ) AND ! empty( $im_provider_id ) ) {

		// Try and find the Instant Messenger Records that match the Provider ID.
		foreach( $records AS $record ) {
			if ( $record['field_im_provider'] == $im_provider_id ) {
				$ims[] = $record;
			}
		}

	}

	// If we are looking for a combination of Location Type ID and Provider ID.
	if ( ! empty( $location_type_id ) AND ! empty( $im_provider_id ) ) {

		// Try and find the Instant Messenger Records that match both the Location and Provider IDs.
		foreach( $records AS $record ) {
			if (
				$record['field_im_location'] == $location_type_id
				AND
				$record['field_im_provider'] == $im_provider_id
			) {
				$ims[] = $record;
			}
		}

	}

	// If we are looking for a neither Location Type ID nor Provider ID.
	if ( empty( $location_type_id ) AND empty( $im_provider_id ) ) {
		$ims = $records;
	}

	/**
	 * Allow the Instant Messenger Records to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param array $ims The existing Instant Messenger Records.
	 * @param int $location_type_id The numeric ID of the CiviCRM Instant Messenger Location Type.
	 * @param int $im_provider_id The numeric ID of the CiviCRM Instant Messenger Provider.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $ims The modified Instant Messenger Records.
	 */
	$ims = apply_filters( 'cacf_get_im_records_by_type_ids', $ims, $location_type_id, $im_provider_id, $selector, $post_id );

	// --<
	return $ims;

}



/**
 * Get the "Primary" Instant Messenger from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $im The formatted Instant Messenger.
 */
function cacf_get_primary_im( $selector, $post_id = null ) {

	// Init return.
	$im = '';

	// Get the Instant Messenger Record.
	$record = cacf_get_primary_im_record( $selector, $post_id );

	// Bail if we don't get an Instant Messenger Record.
	if ( empty( $record ) ) {
		return $im;
	}

	// Bail if the Instant Messenger is empty.
	if ( empty( $record['field_im_name'] ) ) {
		return $im;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get Instant Messenger Providers.
	$im_providers = $cacf->civicrm->im->im_providers_get();

	// Build string from Providers and Instant Messenger.
	$im = sprintf(
		__( '%1$s: %2$s', 'civicrm-acf-integration' ),
		(string) $im_providers[$record['field_im_provider']],
		(string) $record['field_im_name']
	);

	/**
	 * Allow the Instant Messenger to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param str $im The existing Primary Instant Messenger.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $im The modified Primary Instant Messenger.
	 */
	$im = apply_filters( 'cacf_get_primary_im_name', $im, $selector, $post_id );

	// --<
	return $im;

}



/**
 * Get the "Primary" Instant Messenger Record from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return array $im The array of Instant Messenger Record data.
 */
function cacf_get_primary_im_record( $selector, $post_id = null ) {

	// Init return.
	$im = [];

	// Get the Instant Messenger Record.
	$records = cacf_get_im_records( $selector, $post_id );

	// Now try and find the Primary Instant Messenger Record.
	foreach( $records AS $record ) {
		if ( $record['field_im_primary'] == '1' ) {
			$im = $record;
			break;
		}
	}

	/**
	 * Allow the Instant Messenger Record to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param array $im The existing Primary Instant Messenger data.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $im The modified Primary Instant Messenger data.
	 */
	$im = apply_filters( 'cacf_get_primary_im_record', $im, $selector, $post_id );

	// --<
	return $im;

}


/**
 * Get the Instant Messenger Records from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.7.3
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return array $records The array of Instant Messenger Record data.
 */
function cacf_get_im_records( $selector, $post_id = null ) {

	// Init return.
	$records = [];

	// Try the global if no Post ID.
	if ( empty( $post_id ) ) {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return $records;
		}
		$post_id = $post->ID;
	}

	// Get field settings.
	$acf_settings = get_field_object( $selector, $post_id );

	// Bail if we don't get any settings.
	if ( empty( $acf_settings ) ) {
		return $records;
	}

	 // Bail if it's not a CiviCRM Instant Messenger Field.
	 if ( $acf_settings['type'] != 'civicrm_im' ) {
		return $records;
	 }

	// Get Field value.
	$records = get_field( $selector, $post_id );

	/**
	 * Allow the Instant Messenger Record to be filtered.
	 *
	 * @since 0.7.3
	 *
	 * @param array $records The existing Instant Messenger Record data.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $records The modified Instant Messenger Record data.
	 */
	$records = apply_filters( 'cacf_get_im_records', $records, $selector, $post_id );

	// --<
	return $records;

}



// -----------------------------------------------------------------------------



/**
 * Get the Addresses from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $addresses The formatted Addresses.
 */
function cacf_get_addresses( $selector, $post_id = null ) {

	// Init return.
	$addresses = '';

	// Get the Address Records.
	$records = cacf_get_address_records( $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $addresses;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get Location Types.
	$location_types = $cacf->civicrm->address->location_types_get();

	// Build Location Types array for reference.
	$locations = [];
	foreach( $location_types AS $location_type ) {
		$locations[$location_type['id']] = esc_html( $location_type['display_name'] );
	}

	// Format them.
	foreach( $records AS $record ) {

		// Build "heading" from Location.
		$heading = sprintf(
			__( '%s Address', 'civicrm-acf-integration' ),
			(string) $locations[$record['field_address_location_type']]
		);

		// Convert basic ACF data to template data.
		$street_address = esc_html( trim( $record['field_address_street_address'] ) );
		$supplemental_address_1 = esc_html( trim( $record['field_address_supplemental_address_1'] ) );
		$supplemental_address_2 = esc_html( trim( $record['field_address_supplemental_address_2'] ) );
		$supplemental_address_3 = esc_html( trim( $record['field_address_supplemental_address_3'] ) );
		$city = esc_html( trim( $record['field_address_city'] ) );
		$postal_code = esc_html( trim( $record['field_address_postal_code'] ) );

		// Convert Country ACF data to template data.
		$state_province_id = empty( $record['field_address_state_province_id'] ) ? '' : (int) $record['field_address_state_province_id'];
		$state_province = $cacf->civicrm->address->state_province_get_by_id( $state_province_id );
		if ( ! empty( $state_province ) ) {
			$state = esc_html( $state_province['name'] );
			$state_short = esc_html( $state_province['abbreviation'] );
		}

		// Convert Country ACF data to template data.
		$country_id = empty( $record['field_address_country_id'] ) ? '' : (int) $record['field_address_country_id'];
		$country_data = $cacf->civicrm->address->country_get_by_id( $country_id );
		if ( ! empty( $country_data ) ) {
			$country = esc_html( $country_data['name'] );
			$country_short = esc_html( $country_data['iso_code'] );
		}

		// Build Address from template.
		ob_start();
		include CIVICRM_ACF_INTEGRATION_PATH . 'assets/templates/wordpress/shortcode-address.php';;
		$address = ob_get_contents();
		ob_end_clean();

		// Add to filtered array.
		$filtered[] = $heading . "\n\n" . $address;

	}

	// Open the list.
	$addresses .= '<ul><li>';

	// Format the list.
	$addresses .= implode( '</li><li>', $filtered );

	// Close the list.
	$addresses .= '</li></ul>';

	/**
	 * Allow the Addresses to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $addresses The existing Addresses.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $addresses The modified Addresses.
	 */
	$addresses = apply_filters( 'cacf_get_addresses', $addresses, $selector, $post_id );

	// --<
	return $addresses;

}



/**
 * Get the Address for a Location Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Address Location Type.
 * @param str $return Return an HTML list or comma-delimited string. Default 'list'.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $address The formatted Address.
 */
function cacf_get_address_by_type_id( $location_type_id, $return = 'list', $selector, $post_id = null ) {

	// Init return.
	$address = '';

	// Get the Address Records.
	$records = cacf_get_address_records_by_type_id( $location_type_id, $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $address;
	}

	// If we are looking for all records.
	if ( empty( $location_type_id ) ) {
		return cacf_get_addresses( $selector, $post_id );
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Init filtered array.
	$filtered = [];

	// Format them.
	foreach( $records AS $record ) {

		// Convert basic ACF data to template data.
		$street_address = esc_html( trim( $record['field_address_street_address'] ) );
		$supplemental_address_1 = esc_html( trim( $record['field_address_supplemental_address_1'] ) );
		$supplemental_address_2 = esc_html( trim( $record['field_address_supplemental_address_2'] ) );
		$supplemental_address_3 = esc_html( trim( $record['field_address_supplemental_address_3'] ) );
		$city = esc_html( trim( $record['field_address_city'] ) );
		$postal_code = esc_html( trim( $record['field_address_postal_code'] ) );

		// Convert Country ACF data to template data.
		$state_province_id = empty( $record['field_address_state_province_id'] ) ? '' : (int) $record['field_address_state_province_id'];
		$state_province = $cacf->civicrm->address->state_province_get_by_id( $state_province_id );
		if ( ! empty( $state_province ) ) {
			$state = esc_html( $state_province['name'] );
			$state_short = esc_html( $state_province['abbreviation'] );
		}

		// Convert Country ACF data to template data.
		$country_id = empty( $record['field_address_country_id'] ) ? '' : (int) $record['field_address_country_id'];
		$country_data = $cacf->civicrm->address->country_get_by_id( $country_id );
		if ( ! empty( $country_data ) ) {
			$country = esc_html( $country_data['name'] );
			$country_short = esc_html( $country_data['iso_code'] );
		}

		// Build Address from template.
		ob_start();
		include CIVICRM_ACF_INTEGRATION_PATH . 'assets/templates/wordpress/shortcode-address.php';;
		$filtered[] = ob_get_contents();
		ob_end_clean();

	}

	// Bail if we don't get any Address Records.
	if ( empty( $filtered ) ) {
		return $address;
	}

	// Format the string.
	$address = implode( ', ', $filtered );

	/**
	 * Allow the Addresses to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $address The existing Address.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $address The modified Address.
	 */
	$address = apply_filters( 'cacf_get_address_by_type_id', $address, $selector, $post_id );

	// --<
	return $address;

}



/**
 * Get Address Records by Location Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Address Location Type.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return array $addresses The array of Address Record data.
 */
function cacf_get_address_records_by_type_id( $location_type_id, $selector, $post_id = null ) {

	// Init return.
	$addresses = [];

	// Get the Address Records.
	$records = cacf_get_address_records( $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $addresses;
	}

	// If we are looking for a Location Type ID.
	if ( ! empty( $location_type_id ) ) {

		// Try and find the Address Records that match the Location Type ID.
		foreach( $records AS $record ) {
			if ( $record['field_address_location_type'] == $location_type_id ) {
				$addresses[] = $record;
			}
		}

	}

	// If we are not looking for a Location Type ID.
	if ( empty( $location_type_id ) ) {
		$addresses = $records;
	}

	/**
	 * Allow the Address Records to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param array $addresses The existing Address Records.
	 * @param int $location_type_id The numeric ID of the CiviCRM Address Location Type.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $addresses The modified Address Records.
	 */
	$addresses = apply_filters( 'cacf_get_address_records_by_type_id', $addresses, $location_type_id, $selector, $post_id );

	// --<
	return $addresses;

}



/**
 * Get the "Primary" Address Record from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return array $address The array of Address Record data.
 */
function cacf_get_primary_address_record( $selector, $post_id = null ) {

	// Init return.
	$address = [];

	// Get the Address Records.
	$records = cacf_get_address_records( $selector, $post_id );

	// Now try and find the Primary Address Record.
	foreach( $records AS $record ) {
		if ( $record['field_address_primary'] == '1' ) {
			$address = $record;
			break;
		}
	}

	/**
	 * Allow the Address Record to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param array $addresses The existing Primary Address data.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $addresses The modified Primary Address data.
	 */
	$address = apply_filters( 'cacf_get_primary_addresses_record', $address, $selector, $post_id );

	// --<
	return $address;

}



/**
 * Get the Address Records from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return array $records The array of Address Record data.
 */
function cacf_get_address_records( $selector, $post_id = null ) {

	// Init return.
	$records = [];

	// Try the global if no Post ID.
	if ( empty( $post_id ) ) {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return $records;
		}
		$post_id = $post->ID;
	}

	// Get field settings.
	$acf_settings = get_field_object( $selector, $post_id );

	// Bail if we don't get any settings.
	if ( empty( $acf_settings ) ) {
		return $records;
	}

	 // Bail if it's not a CiviCRM Address Field.
	 if ( $acf_settings['type'] != 'civicrm_address' ) {
		return $records;
	 }

	// Get Field value.
	$records = get_field( $selector, $post_id );

	/**
	 * Allow the Address Record to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param array $records The existing Address Record data.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return array $records The modified Address Record data.
	 */
	$records = apply_filters( 'cacf_get_address_records', $records, $selector, $post_id );

	// --<
	return $records;

}



// -----------------------------------------------------------------------------



/**
 * Get the Cities from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $cities The formatted Cities.
 */
function cacf_get_cities( $selector, $post_id = null ) {

	// Init return.
	$cities = '';

	// Get the Address Records.
	$records = cacf_get_address_records( $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $cities;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get Location Types.
	$location_types = $cacf->civicrm->address->location_types_get();

	// Build Location Types array for reference.
	$locations = [];
	foreach( $location_types AS $location_type ) {
		$locations[$location_type['id']] = esc_html( $location_type['display_name'] );
	}

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the City is empty.
		if ( empty( $record['field_address_city'] ) ) {
			continue;
		}

		// Build string from Location and City.
		$addresses = sprintf(
			__( '%1$s: %2$s', 'civicrm-acf-integration' ),
			(string) $locations[$record['field_address_location_type']],
			(string) $record['field_address_city']
		);

		// Add to filtered array.
		$filtered[] = $addresses;

	}

	// Open the list.
	$cities .= '<ul><li>';

	// Format the list.
	$cities .= implode( '</li><li>', $filtered );

	// Close the list.
	$cities .= '</li></ul>';

	/**
	 * Allow the Cities to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $cities The existing Cities.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $cities The modified Cities.
	 */
	$cities = apply_filters( 'cacf_get_cities', $cities, $selector, $post_id );

	// --<
	return $cities;

}



/**
 * Get the City for a Location Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Address Location Type.
 * @param str $return Return an HTML list or comma-delimited string. Default 'list'.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $city The formatted City.
 */
function cacf_get_city_by_type_id( $location_type_id, $return = 'list', $selector, $post_id = null ) {

	// Init return.
	$city = '';

	// Get the City Records.
	$records = cacf_get_address_records_by_type_id( $location_type_id, $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $city;
	}

	// If we are looking for all records.
	if ( empty( $location_type_id ) ) {
		return cacf_get_cities( $selector, $post_id );
	}

	// Init filtered array.
	$filtered = [];

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the City is empty.
		if ( empty( $record['field_address_city'] ) ) {
			continue;
		}

		// Assign City to filter.
		$filtered[] = (string) $record['field_address_city'];

	}

	// Bail if we don't get any City Records.
	if ( empty( $filtered ) ) {
		return $city;
	}

	// Format the string.
	$city .= implode( ', ', $filtered );

	/**
	 * Allow the Cities to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $city The existing City.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $city The modified City.
	 */
	$city = apply_filters( 'cacf_get_city_by_type_id', $city, $selector, $post_id );

	// --<
	return $city;

}



/**
 * Get the "Primary" City from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $city The formatted City.
 */
function cacf_get_primary_city( $selector, $post_id = null ) {

	// Init return.
	$city = '';

	// Get the Primary Address Record.
	$record = cacf_get_primary_address_record( $selector, $post_id );

	// Bail if we don't get a City Record.
	if ( empty( $record ) ) {
		return $city;
	}

	// Bail if the City is empty.
	if ( empty( $record['field_address_city'] ) ) {
		return $city;
	}

	// Assign City to return.
	$city = (string) $record['field_address_city'];

	/**
	 * Allow the City to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $city The existing Primary City.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $city The modified Primary City.
	 */
	$city = apply_filters( 'cacf_get_primary_city', $city, $selector, $post_id );

	// --<
	return $city;

}



// -----------------------------------------------------------------------------



/**
 * Get the States from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $states The formatted States.
 */
function cacf_get_states( $selector, $post_id = null ) {

	// Init return.
	$states = '';

	// Get the Address Records.
	$records = cacf_get_address_records( $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $states;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get Location Types.
	$location_types = $cacf->civicrm->address->location_types_get();

	// Build Location Types array for reference.
	$locations = [];
	foreach( $location_types AS $location_type ) {
		$locations[$location_type['id']] = esc_html( $location_type['display_name'] );
	}

	// Get States/Provinces.
	$state_provinces = $cacf->civicrm->address->state_provinces_get();

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the State is empty.
		if ( empty( $record['field_address_state_province_id'] ) ) {
			continue;
		}

		// Build string from Location and State.
		$addresses = sprintf(
			__( '%1$s: %2$s', 'civicrm-acf-integration' ),
			(string) $locations[$record['field_address_location_type']],
			(string) $state_provinces[$record['field_address_state_province_id']]
		);

		// Add to filtered array.
		$filtered[] = $addresses;

	}

	// Open the list.
	$states .= '<ul><li>';

	// Format the list.
	$states .= implode( '</li><li>', $filtered );

	// Close the list.
	$states .= '</li></ul>';

	/**
	 * Allow the States to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $states The existing States.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $states The modified States.
	 */
	$states = apply_filters( 'cacf_get_states', $states, $selector, $post_id );

	// --<
	return $states;

}



/**
 * Get the State for a Location Type from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param int $location_type_id The numeric ID of the CiviCRM Address Location Type.
 * @param str $return Return an HTML list or comma-delimited string. Default 'list'.
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $state The formatted State.
 */
function cacf_get_state_by_type_id( $location_type_id, $return = 'list', $selector, $post_id = null ) {

	// Init return.
	$state = '';

	// Get the State Records.
	$records = cacf_get_address_records_by_type_id( $location_type_id, $selector, $post_id );

	// Bail if we don't get any.
	if ( empty( $records ) ) {
		return $state;
	}

	// If we are looking for all records.
	if ( empty( $location_type_id ) ) {
		return cacf_get_states( $selector, $post_id );
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get States/Provinces.
	$states = $cacf->civicrm->address->state_provinces_get();

	// Init filtered array.
	$filtered = [];

	// Format them.
	foreach( $records AS $record ) {

		// Skip if the State is empty.
		if ( empty( $record['field_address_state_province_id'] ) ) {
			continue;
		}

		// Assign State to filter.
		$filtered[] = (string) $states[$record['field_address_state_province_id']];

	}

	// Bail if we don't get any State Records.
	if ( empty( $filtered ) ) {
		return $state;
	}

	// Format the string.
	$state .= implode( ', ', $filtered );

	/**
	 * Allow the States to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $state The existing State.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $state The modified State.
	 */
	$state = apply_filters( 'cacf_get_state_by_type_id', $state, $selector, $post_id );

	// --<
	return $state;

}



/**
 * Get the "Primary" State from a given ACF Field.
 *
 * If you are calling this from outside The Loop, pass a Post ID as well.
 *
 * @since 0.8.2
 *
 * @param str $selector The ACF field selector.
 * @param int|str $post_id The ACF "Post ID".
 * @return str $state The formatted State.
 */
function cacf_get_primary_state( $selector, $post_id = null ) {

	// Init return.
	$state = '';

	// Get the Primary Address Record.
	$record = cacf_get_primary_address_record( $selector, $post_id );

	// Bail if we don't get a State Record.
	if ( empty( $record ) ) {
		return $state;
	}

	// Bail if the State is empty.
	if ( empty( $record['field_address_state_province_id'] ) ) {
		return $state;
	}

	// Get reference to plugin.
	$cacf = civicrm_acf_integration();

	// Get States/Provinces.
	$states = $cacf->civicrm->address->state_provinces_get();

	// Assign State to return.
	$state = (string) $states[$record['field_address_state_province_id']];

	/**
	 * Allow the State to be filtered.
	 *
	 * @since 0.8.2
	 *
	 * @param str $state The existing Primary State.
	 * @param str $selector The ACF field selector.
	 * @param int|str $post_id The ACF "Post ID".
	 * @return str $state The modified Primary State.
	 */
	$state = apply_filters( 'cacf_get_primary_state', $state, $selector, $post_id );

	// --<
	return $state;

}




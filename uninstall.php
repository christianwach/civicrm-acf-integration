<?php

/**
 * CiviCRM ACF Integration Uninstaller.
 *
 * Attempts to delete all traces of this plugin.
 *
 * @package CiviCRM_ACF_Integration
 */



// Kick out if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}



// Delete version.
delete_site_option( 'civicrm_acf_integration_version' );

// Delete mapping settings.
civicrm_acf_integration()->mapping->mappings_delete();
civicrm_acf_integration()->mapping->settings_delete();

// TODO: In multisite, remove from all sites.

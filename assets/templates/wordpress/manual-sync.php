<!-- assets/templates/wordpress/manual-sync.php -->
<div class="wrap">

	<h1><?php _e( 'CiviCRM ACF Integration: Manual Sync', 'civicrm-acf-integration' ); ?></h1>

	<?php if ( ! empty( $messages ) ) : ?>
		<?php echo $messages; ?>
	<?php endif; ?>

	<form method="post" id="civicrm_acf_integration_sync_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'civicrm_acf_integration_sync_action', 'civicrm_acf_integration_sync_nonce' ); ?>

		<p><?php _e( 'Things can be a little complicated on initial setup because there can be data in WordPress or CiviCRM or both. These utilities should help you get going.', 'civicrm-acf-integration' ); ?></p>

		<hr>

		<h3><?php _e( 'WordPress Post Type Synchronisation', 'civicrm-acf-integration' ); ?></h3>

		<p><?php _e( 'Select which Post Types you want to sync with CiviCRM.', 'civicrm-acf-integration' ); ?></p>

		<table class="form-table">

			<?php if ( ! empty( $post_types ) ) : ?>
				<?php foreach( $post_types AS $post_type_name => $post_type_label ) : ?>

					<tr valign="top">
						<th scope="row"><label for="cai_post_to_contact-<?php echo $post_type_name; ?>"><?php echo esc_html( $post_type_label ); ?></label></th>
						<td><input type="submit" id="cai_post_to_contact-<?php echo $post_type_name; ?>" name="cai_post_to_contact-<?php echo $post_type_name; ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-acf-integration' ); } else { _e( 'Continue Sync', 'civicrm-acf-integration' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="cai_post_to_contact_stop-<?php echo $post_type_name; ?>" name="cai_post_to_contact_stop-<?php echo $post_type_name; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php } ?></td>
					</tr>

					<tr valign="top">
						<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_post_to_contact-<?php echo $post_type_name; ?>"><div class="progress-label"></div></div></td>
					</tr>

				<?php endforeach; ?>
			<?php endif; ?>

		</table>

		<hr>

		<h3><?php _e( 'CiviCRM Contact Type Synchronisation', 'civicrm-acf-integration' ); ?></h3>

		<p><?php _e( 'Select which Contact Types you want to sync with WordPress.', 'civicrm-acf-integration' ); ?></p>

		<table class="form-table">

			<?php if ( ! empty( $contact_types ) ) : ?>
				<?php foreach( $contact_types AS $contact_type_id => $contact_type_label ) : ?>

					<tr valign="top">
						<th scope="row"><label for="cai_contact_to_post-<?php echo $contact_type_id; ?>"><?php echo esc_html( $contact_type_label ); ?></label></th>
						<td><input type="submit" id="cai_contact_to_post-<?php echo $contact_type_id; ?>" name="cai_contact_to_post-<?php echo $contact_type_id; ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-acf-integration' ); } else { _e( 'Continue Sync', 'civicrm-acf-integration' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="cai_contact_to_post_stop-<?php echo $contact_type_id; ?>" name="cai_contact_to_post_stop-<?php echo $contact_type_id; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php } ?></td>
					</tr>

					<tr valign="top">
						<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_contact_to_post-<?php echo $contact_type_id; ?>"><div class="progress-label"></div></div></td>
					</tr>

				<?php endforeach; ?>
			<?php endif; ?>

		</table>

		<hr>

		<h3><?php _e( 'CiviCRM Group Synchronisation', 'civicrm-acf-integration' ); ?></h3>

		<p><?php _e( 'Select which Groups you want to sync with WordPress.', 'civicrm-acf-integration' ); ?></p>

		<table class="form-table">

			<?php if ( ! empty( $groups ) ) : ?>
				<?php foreach( $groups AS $group_id => $group_label ) : ?>

					<tr valign="top">
						<th scope="row"><label for="cai_group_to_term-<?php echo $group_id; ?>"><?php echo esc_html( $group_label ); ?></label></th>
						<td><input type="submit" id="cai_group_to_term-<?php echo $group_id; ?>" name="cai_group_to_term-<?php echo $group_id; ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-acf-integration' ); } else { _e( 'Continue Sync', 'civicrm-acf-integration' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="cai_group_to_term_stop-<?php echo $group_id; ?>" name="cai_group_to_term_stop-<?php echo $group_id; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php } ?></td>
					</tr>

					<tr valign="top">
						<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_group_to_term-<?php echo $group_id; ?>"><div class="progress-label"></div></div></td>
					</tr>

				<?php endforeach; ?>
			<?php endif; ?>

		</table>

		<hr>

	</form>

</div><!-- /.wrap -->

<?php
/**
 * Manual Sync template.
 *
 * Handles markup for the Manual Sync admin page.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.6.4
 */

?><!-- assets/templates/wordpress/manual-sync.php -->
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
				<?php foreach( $post_types AS $post_type => $label ) : ?>

					<?php $identifier = 'cai_post_to_contact_' . $post_type; ?>
					<?php $stop = ''; ?>

					<?php if ( 'fgffgs' == get_option( '_' . $identifier . '_offset', 'fgffgs' ) ) : ?>
						<?php $button = __( 'Sync Now', 'civicrm-acf-integration' ); ?>
					<?php else : ?>
						<?php $button = __( 'Continue Sync', 'civicrm-acf-integration' ); ?>
						<?php $stop = $identifier . '_stop'; ?>
					<?php endif; ?>

					<tr valign="top">
						<th scope="row"><label for="<?php echo $identifier; ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php echo $button; ?>" class="button-primary" /><?php
							if ( ! empty( $stop ) ) :
								?> <input type="submit" id="<?php echo $stop; ?>" name="<?php echo $stop; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php
							endif;
						?></td>
					</tr>

					<tr valign="top">
						<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_post_to_contact_<?php echo $post_type; ?>"><div class="progress-label"></div></div></td>
					</tr>

				<?php endforeach; ?>
			<?php endif; ?>

		</table>

		<hr>

		<h3><?php _e( 'CiviCRM Contact Type Synchronisation', 'civicrm-acf-integration' ); ?></h3>

		<p><?php _e( 'Select which Contact Types you want to sync with WordPress.', 'civicrm-acf-integration' ); ?></p>

		<table class="form-table">

			<?php if ( ! empty( $contact_types ) ) : ?>
				<?php foreach( $contact_types AS $contact_type_id => $label ) : ?>

					<?php $identifier = 'cai_contact_to_post_' . $contact_type_id; ?>
					<?php $stop = ''; ?>

					<?php if ( 'fgffgs' == get_option( '_' . $identifier . '_offset', 'fgffgs' ) ) : ?>
						<?php $button = __( 'Sync Now', 'civicrm-acf-integration' ); ?>
					<?php else : ?>
						<?php $button = __( 'Continue Sync', 'civicrm-acf-integration' ); ?>
						<?php $stop = $identifier . '_stop'; ?>
					<?php endif; ?>

					<tr valign="top">
						<th scope="row"><label for="<?php echo $identifier; ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php echo $button; ?>" class="button-primary" /><?php
							if ( ! empty( $stop ) ) :
								?> <input type="submit" id="<?php echo $stop; ?>" name="<?php echo $stop; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php
							endif;
						?></td>
					</tr>

					<tr valign="top">
						<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_contact_to_post_<?php echo $contact_type_id; ?>"><div class="progress-label"></div></div></td>
					</tr>

				<?php endforeach; ?>
			<?php endif; ?>

		</table>

		<hr>

		<h3><?php _e( 'CiviCRM Group Synchronisation', 'civicrm-acf-integration' ); ?></h3>

		<p><?php _e( 'Select which CiviCRM Groups you want to sync with WordPress Terms.', 'civicrm-acf-integration' ); ?></p>

		<table class="form-table">

			<?php if ( ! empty( $groups ) ) : ?>
				<?php foreach( $groups AS $group_id => $label ) : ?>

					<?php $identifier = 'cai_group_to_term_' . $group_id; ?>
					<?php $stop = ''; ?>

					<?php if ( 'fgffgs' == get_option( '_' . $identifier . '_offset', 'fgffgs' ) ) : ?>
						<?php $button = __( 'Sync Now', 'civicrm-acf-integration' ); ?>
					<?php else : ?>
						<?php $button = __( 'Continue Sync', 'civicrm-acf-integration' ); ?>
						<?php $stop = $identifier . '_stop'; ?>
					<?php endif; ?>

					<tr valign="top">
						<th scope="row"><label for="<?php echo $identifier; ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php echo $button; ?>" class="button-primary" /><?php
							if ( ! empty( $stop ) ) :
								?> <input type="submit" id="<?php echo $stop; ?>" name="<?php echo $stop; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php
							endif;
						?></td>
					</tr>

					<tr valign="top">
						<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_group_to_term_<?php echo $group_id; ?>"><div class="progress-label"></div></div></td>
					</tr>

				<?php endforeach; ?>
			<?php endif; ?>

		</table>

		<hr>

	</form>

</div><!-- /.wrap -->

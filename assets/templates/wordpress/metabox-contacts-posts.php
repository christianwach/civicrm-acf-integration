<?php
/**
 * CiviCRM Contacts to WordPress Posts sync template.
 *
 * Handles markup for the CiviCRM Contacts to WordPress Posts meta box.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8
 */

?><!-- assets/templates/wordpress/metabox-contacts-posts.php -->
<?php $prefix = 'cai_contact_to_post'; ?>

<div class="cai_wrapper <?php echo $prefix; ?>">

	<p><?php _e( 'Select which Contact Types you want to sync to their corresponding WordPress Post Types.', 'civicrm-acf-integration' ); ?></p>

	<?php if ( ! empty( $contact_types ) ) : ?>
		<table class="form-table">

			<?php foreach( $contact_types AS $contact_type_id => $label ) : ?>

				<?php $identifier = $prefix . '_' . $contact_type_id; ?>
				<?php $stop = ''; ?>

				<?php if ( 'fgffgs' == get_option( '_' . $identifier . '_offset', 'fgffgs' ) ) : ?>
					<?php $button = __( 'Sync Now', 'civicrm-acf-integration' ); ?>
				<?php else : ?>
					<?php $button = __( 'Continue Sync', 'civicrm-acf-integration' ); ?>
					<?php $stop = $identifier . '_stop'; ?>
				<?php endif; ?>

				<tr valign="top">
					<th scope="row"><label for="<?php echo $identifier; ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php echo $button; ?>" class="button-secondary" /><?php
						if ( ! empty( $stop ) ) :
							?> <input type="submit" id="<?php echo $stop; ?>" name="<?php echo $stop; ?>" value="<?php _e( 'Stop Sync', 'civicrm-acf-integration' ); ?>" class="button-secondary" /><?php
						endif;
					?></td>
				</tr>

				<tr valign="top">
					<td colspan="2" class="progress-bar progress-bar-hidden"><div id="progress-bar-cai_contact_to_post_<?php echo $contact_type_id; ?>"><div class="progress-label"></div></div></td>
				</tr>

			<?php endforeach; ?>

		</table>
	<?php else : ?>

		<p><?php _e( 'No synced Contact Types found.', 'civicrm-acf-integration' ); ?></p>

	<?php endif; ?>

</div>

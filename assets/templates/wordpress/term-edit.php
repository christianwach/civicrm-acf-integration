<?php
/**
 * Edit Term template.
 *
 * Injects markup into the Edit Term page.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.6.4
 */

?><!-- assets/templates/wordpress/term-edit.php -->
<tr class="form-field term-cai-civicrm-group-wrap">
	<th scope="row"><label for="cai-civicrm-group"><?php _e( 'CiviCRM Group for ACF Integration', 'civicrm-acf-integration' ); ?></label></th>
	<td>
		<select name="cai-civicrm-group" id="cai-civicrm-group" class="postform">
			<?php if ( $group_id !== 0 ) : ?>
				<option value="0"><?php _e( 'None', 'civicrm-acf-integration' ); ?></option>
			<?php else : ?>
				<option value="0" selected="selected"><?php _e( 'None', 'civicrm-acf-integration' ); ?></option>
			<?php endif; ?>
			<?php if ( ! empty( $groups ) ) : ?>
				<?php foreach ( $groups AS $group ) : ?>
					<?php if ( $group['id'] == $group_id ) : ?>
						<option value="<?php echo $group['id'] ?>" selected="selected"><?php echo $group['title']; ?></option>
					<?php else : ?>
						<option value="<?php echo $group['id'] ?>"><?php echo $group['title']; ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</select>
		<p class="description"><?php _e( 'When a CiviCRM Group is chosen, then any Contact that is a member of the chosen Group will have this term assigned to their synced Post.', 'civicrm-acf-integration' ); ?></p>
	</td>
</tr>

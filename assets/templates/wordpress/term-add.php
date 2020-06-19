<!-- assets/templates/wordpress/term-add.php -->
<div class="form-field term-cai-civicrm-group-wrap">
	<label for="cai-civicrm-group"><?php _e( 'CiviCRM Group for ACF Integration', 'civicrm-acf-integration' ); ?></label>
	<select name="cai-civicrm-group" id="cai-civicrm-group" class="postform">
		<option value="0" selected="selected"><?php _e( 'None', 'civicrm-acf-integration' ); ?></option>
		<?php if ( ! empty( $groups ) ) : ?>
			<?php foreach ( $groups AS $group ) : ?>
				<option value="<?php echo $group['id'] ?>"><?php echo $group['title']; ?></option>
			<?php endforeach; ?>
		<?php endif; ?>
	</select>
	<p class="description"><?php _e( 'When a CiviCRM Group is chosen here, then any Post which is given this term will have its synced Contact added to the Group. In CiviCRM, any Contact that is made a member of the chosen Group will have this term assigned to their synced Post.', 'civicrm-acf-integration' ); ?></p>
</div>

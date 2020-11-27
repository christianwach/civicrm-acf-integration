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

	<p><?php _e( 'Things can be a little complicated on initial setup because there can be data in WordPress or CiviCRM or both. The utilities below should help you get going.', 'civicrm-acf-integration' ); ?></p>

	<?php if ( ! empty( $messages ) ) : ?>
		<?php echo $messages; ?>
	<?php endif; ?>

	<form method="post" id="civicrm_acf_integration_sync_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'civicrm_acf_integration_sync_action', 'civicrm_acf_integration_sync_nonce' ); ?>
		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', FALSE); ?>
		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', FALSE); ?>

		<div id="welcome-panel" class="welcome-panel hidden">
		</div>

		<div id="dashboard-widgets-wrap">

			<div id="dashboard-widgets" class="metabox-holder<?php echo $columns_css; ?>">

				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes($screen->id, 'normal', '');  ?>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes($screen->id, 'side', ''); ?>
				</div>

			</div><!-- #post-body -->
			<br class="clear">

		</div><!-- #poststuff -->

</div><!-- /.wrap -->

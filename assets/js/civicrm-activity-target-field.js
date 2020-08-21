/**
 * CiviCRM ACF Integration Custom ACF Field Type - CiviCRM Activity Target Field.
 *
 * @package CiviCRM_ACF_Integration
 */

(function($, undefined){

	// Extend the Select Field model.
	var Field = acf.models.SelectField.extend({
		type: 'civicrm_activity_target',
	});

	// Register it.
	acf.registerFieldType( Field );

})(jQuery);

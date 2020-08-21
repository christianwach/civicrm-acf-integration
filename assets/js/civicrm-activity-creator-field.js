/**
 * CiviCRM ACF Integration Custom ACF Field Type - CiviCRM Activity Creator Field.
 *
 * @package CiviCRM_ACF_Integration
 */

(function($, undefined){

	// Extend the Select Field model.
	var Field = acf.models.SelectField.extend({
		type: 'civicrm_activity_creator',
	});

	// Register it.
	acf.registerFieldType( Field );

})(jQuery);

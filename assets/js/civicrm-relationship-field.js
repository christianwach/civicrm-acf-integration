/**
 * CiviCRM ACF Integration Custom ACF Field Type - CiviCRM Relationship.
 *
 * @package CiviCRM_ACF_Integration
 */

(function($, undefined){

	// Extend the Select Field model.
	var Field = acf.models.SelectField.extend({
		type: 'civicrm_relationship',
	});

	// Register it.
	acf.registerFieldType( Field );

})(jQuery);

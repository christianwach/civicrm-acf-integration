=== CiviCRM ACF Integration ===
Contributors: needle
Donate link: https://www.paypal.me/interactivist
Tags: civicrm, acf, sync
Requires at least: 4.9
Tested up to: 5.3
Stable tag: 0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enables integration between CiviCRM Entities and WordPress Entities with data synced via Advanced Custom Fields.



== Description ==

*CiviCRM ACF Integration* enables integration between CiviCRM Entities and WordPress Entities with data synced via Advanced Custom Fields.

Please be aware that this plugin is at an early stage of development and — although it is limited in its coverage of the entities that can be linked — it is fairly comprehensive in its mapping of the built-in CiviCRM *Custom Field Types* with their corresponding ACF *Field Types*.

So if, for now, you want to display (or create) a Contact Type on your WordPress site with ACF Fields that contain synced CiviCRM data, this plugin could work for you.



### Requirements

This plugin recommends a minimum of *WordPress 4.9*, *Advanced Custom Fields 5.8* or *Advanced Custom Fields Pro 5.8* and *CiviCRM 5.13*.



### Preparing for integration

In general, think of this plugin as a way to make data in CiviCRM visible in WordPress via ACF Fields. It is therefore recommended that you configure CiviCRM the way you want it first - so, for example, you should create your Contact Types, Custom Groups and Custom Fields before implementing links between Entities and Fields because (although CiviCRM allows you to alter your setup at any time) *CiviCRM ACF Integration* may not recognised your changes and may not automatically sync the structural changes to WordPress or ACF.

In addition, given that this plugin is still at an early stage of development, it is highly recommended that you try it out on a test site to gain familiarity with how it works. Test early, test often and make backups. Okay, now that we're clear about that, onwards...



### Links between entities

At present the *CiviCRM ACF Integration* plugin allows you to specify links between:

* WordPress Custom Post Types and CiviCRM Contact Types. (For now, it's recommended to link only Contact Sub-types — e.g. "Student" — instead of the top-level "Individual" Contact Type)
* More entity links to follow...

To do this, in CiviCRM go to *Administer* —> *Customize Data and Screens* —> *Contact Types* and edit a Contact Type. You will see a dropdown that allows you to choose a WordPress Post Type to link to the Contact Type you are editing. Choose one and save the Contact Type.

From now on, each time you create a Contact of the Contact Type that you have linked, a new WordPress Post will be created. The "Display Name" of the Contact will become the WordPress Post Title. And - in the reverse direction - each time you create a WordPress Post which is of the Post Type you have linked to a Contact Type, a new Contact will be created with their "Display Name" set to the Title of the new Post.



### Links between Fields

Once the link between a Contact Type and a Post Type has been made, go to the edit screen of an ACF Field Group whose Location Rule places it on the edit screen of your linked Post Type. Scroll to the "Settings" section and you will see a dropdown which allows you to choose the CiviCRM Contact Type that ACF Fields in the Field Group should refer to. Choose one and save the Field Group.

Now you can edit your ACF Fields themselves to integrate them with the Contact's data fields. These are split into two kinds:

* "Contact Fields" are those fields that are associated with a Contact by default, e.g. "First Name", "Last Name", "Gender", "Date of Birth" etc
* "Custom Fields" are part of a Custom Group that has been associated with the Contact Type, e.g. "Most Important Issue", "Marital Status" and "Marriage Date" in the "Constituent Information" Custom Group that is part of the CiviCRM sample data. These Fields are listed under the name of Custom Group to which they belong.

ACF Fields of type Select, Radio Button and Checkbox will have their CiviCRM values ported across to the ACF Field "choices" when the Field Group is saved. To link to a Multi-Select CiviCRM Field, you must enable "Select multiple values?" in the ACF Field's settings. If you make changes to the Option Values for a CiviCRM Custom Field mapped to one of these ACF Field Types, you will have to re-save the ACF Field Group(s) that contain the mapped ACF Fields.

Please note that in order to link some kinds of Fields, you may have to save the ACF Field Group before the CiviCRM Field selector shows up and populates correctly. In particular, this applies to:

* "Text" Fields - because ACF does not AJAX-refresh the Field Settings when this Field Type is chosen
* "Multi-Select" Fields - because these map to vanilla ACF "Select" Fields where "Select multiple values?" is checked and, again, there's no AJAX-refresh when the settings are modified
* "Auto-complete" Fields - because these also map to vanilla ACF "Select" Fields where "Stylised UI" and "Use AJAX to lazy load choices?" are checked and, here too, there's no AJAX-refresh

In each of these cases, choose the ACF Field Type, modify the Field Settings as required, save the Field Group, then re-edit the Field. The "CiviCRM Field" setting should then appear and populate appropriately.


#### CiviCRM Contact Fields

The following are the Contact Fields and the kind of ACF Field needed to map them to CiviCRM:

* Prefix & Suffix — ACF Select
* First Name, Middle Name, Last Name — ACF Text
* Primary Email — ACF Text (this will be enhanced in a future version)
* Website — ACF Link (this will be enhanced in a future version)
* Address - ACF Google Map (only available in ACF Pro)
* Gender — ACF Radio Button
* Date of Birth & Date of Death - ACF Date Picker
* Deceased - ACF True/False
* Job Title, Source & Nickname - ACF Text
* Employer - ACF CiviCRM Contact (see "Custom ACF Fields" below)

When you select a Field Type for an ACF Field, the "CiviCRM Field" dropdown in the ACF Field's Settings will only show you those CiviCRM Contact Fields which can be mapped to this type of ACF Field.


#### CiviCRM Custom Fields

When creating a Custom Field in CiviCRM, you need to specify the kind of Data Type that it is - e.g. "Alphanumeric", "Integer", "Number", etc - followed by the Field Type - e.g. "Text", "Select", "Radio", etc. The Field Type pretty much corresponds to the kind of ACF Field that will map to the Custom Field. When you select a Field Type for an ACF Field, the "CiviCRM Field" dropdown in the ACF Field's Settings will only show you those CiviCRM Custom Fields which can be mapped to this type of ACF Field.


#### ACF Custom Fields

The *CiviCRM ACF Integration* plugin also provides three custom ACF Fields which you will see as choices in the "Field Type" dropdown when you add a new ACF Field to a Field Group. These are:

* "CiviCRM Contact" -- syncs with either a CiviCRM "Contact Reference" Custom Field or the "Current Employer" Contact Field
* "CiviCRM Relationship" -- syncs between the ACF Field and a CiviCRM Relationship
* "CiviCRM Yes/No" -- syncs between the ACF Field and a CiviCRM Yes/No Custom Field (necessary because a CiviCRM Yes/No Custom Field is actually a Yes/No/Unknown field and the ACF True-False Field does not allow Unknown)



### Outstanding Issues


##### Changes to Custom Field settings

If you alter the settings of a CiviCRM Custom Field then the ACF Field(s) that are mapped to it will not automatically pick up those changes. For the time being, you will need to manually check that the settings on both sides are what you would expect them to be.


##### File Field

There is no mapping between the CiviCRM "File" and the ACF "File" Field Types yet.


##### Address Field

There is no "Address Field" in ACF or ACF Pro although there are some somewhat outdated plugins ([here](https://github.com/GCX/acf-address-field) and [here](https://github.com/strickdj/acf-field-address), plus [this article](https://acfextras.com/simple-address-with-schema-markup/)) that do offer this. A future version of this plugin could provide a Custom Field Type (with Sub-fields) that allows CiviCRM Addresses to be sync to ACF Fields. Its settings would allow the choice of which fields to render.


##### Current Employer

There are oddities in CiviCRM's relationships, particularly the "Employer Of" relationship - which is both a "Relationship" and a "Contact Field". The ID of a Contact's "Current Employer" may be present in the `current_employer` field when retrieved via the CiviCRM API and can be set by populating the `employer_id` field.

The "Current Employer" can be mapped using an ACF Select Field, but sync will only take place when the ACF Field's value is changed or when the "Current Employer" field on the Contact is changed. It will not sync when the "Employer Of"/"Employee Of" Relationship is edited.


##### Expired Relationships

CiviCRM's Relationships can be time-limited and the "Inactive Relationships" list on a Contact's Relationships tab shows both relationships that are Disabled and those that have a past End Date. An ACF Field mapped to such a Relationship will only update when the "Disable expired relationships" Scheduled Job runs and sets the Relationship's `is_active` property.


##### Country & State/Province

These work when using either the "Select" or "Multi-Select" options in CiviCRM, however the "choices" in the ACF Field are dependent upon the Countries and State/Provinces that have been enabled in CiviCRM. If you change these settings, you will have to re-save the ACF Field Groups that contain the Fields that are mapped to them.


##### WordPress Post Content

Since CiviCRM Contacts do not have a WYSIWYG field attached to them by default, there are (as yet) no options for syncing the Post Content to a Contact. To do something equivalent, use an ACF Field of type "Wysiwyg Editor" and map it to a CiviCRM Custom Field of type "Note/RichTextEditor". You can use the setting of an ACF Field Group to hide the Content Editor.


##### CiviCRM Stylesheets

Unless you disable the CiviCRM Shortcode on a Post Type (via the settings page in CiviCRM Admin Utilities) then the CiviCRM Stylesheets load on the edit screen for that Post Type and interfere with the styling of ACF Select2 elements. It seems unlikely that the CiviCRM Shortcode would be useful in the Post Content of a linked Post Type, so disable it unless it's absolutely necessary.



### Credits

Many thanks to [Ryan Waterbury](https://github.com/onedogsolutions) of [One Dog Solutions](https://onedog.solutions/) for funding the initial development of this plugin.



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Make sure CiviCRM is activated and properly configured
1. Activate the plugin through the 'Plugins' menu in WordPress



== Changelog ==

= 0.5 =

Initial release.

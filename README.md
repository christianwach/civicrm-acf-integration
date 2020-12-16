CiviCRM ACF Integration
=======================

**Contributors:** [needle](https://profiles.wordpress.org/needle/)<br/>
**Donate link:** https://www.paypal.me/interactivist<br/>
**Tags:** civicrm, acf, sync<br/>
**Requires at least:** 4.9<br/>
**Tested up to:** 5.6<br/>
**Stable tag:** 0.8.2<br/>
**License:** GPLv2 or later<br/>
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

Enables integration between CiviCRM Entities and WordPress Entities with data synced via Advanced Custom Fields.

## Description

*CiviCRM ACF Integration* is a WordPress plugin that enables integration between CiviCRM Entities and WordPress Entities with data synced via Advanced Custom Fields.

Please be aware that this plugin is at an early stage of development and - although it is limited in its coverage of the entities that can be linked - it is fairly comprehensive in its mapping of the built-in CiviCRM *Custom Field Types* with their corresponding ACF *Field Types*.

So if, for now, you want to display (or create) a Contact Type on your WordPress site with ACF Fields that contain synced CiviCRM data, this plugin could work for you.

This plugin recommends a minimum of *WordPress 4.9*, *Advanced Custom Fields 5.8* or *Advanced Custom Fields Pro 5.8* and *CiviCRM 5.13*. YMMV.

## Preparing for integration

In general, think of this plugin as a way to make data in CiviCRM visible in WordPress via ACF Fields. It is therefore recommended that you configure CiviCRM the way you want it first - so, for example, you should create your Contact Types, Custom Groups and Custom Fields before implementing links between Entities and Fields because (although CiviCRM allows you to alter your setup at any time) *CiviCRM ACF Integration* may not recognised your changes and may not automatically sync the structural changes to WordPress or ACF.

In addition, given that this plugin is still at an early stage of development, it is highly recommended that you try it out on a test site to gain familiarity with how it works. Test early, test often and make backups. Okay, now that we're clear about that, onwards...

## Links between entities

At present the *CiviCRM ACF Integration* plugin allows you to specify links between:

* CiviCRM Contact Types and WordPress Custom Post Types
* CiviCRM Events and Event Organiser Events
* CiviCRM Activity Types and WordPress Custom Post Types
* More entity links to follow...

### CiviCRM Contact Types and WordPress Custom Post Types

To link these together, in CiviCRM go to *Administer* -> *Customize Data and Screens* -> *Contact Types* and edit a Contact Type. You will see a dropdown that allows you to choose a WordPress Post Type to link to the Contact Type you are editing. Choose one and save the Contact Type.

From now on, each time you create a Contact of the Contact Type that you have linked, a new WordPress Post will be created. The "Display Name" of the Contact will become the WordPress Post Title. And - in the reverse direction - each time you create a WordPress Post which is of the Post Type you have linked to a Contact Type, a new Contact will be created with their "Display Name" set to the Title of the new Post.

### CiviCRM Events and Event Organiser Events

If you want to make the same kind of links between Events in WordPress and CiviCRM, this plugin is compatible with [CiviCRM Event Organiser](https://github.com/christianwach/civicrm-event-organiser) and enables integration of Custom Fields on CiviCRM Events with ACF Fields attached to the Event Organiser "Event" Post Type.

*Important note:* Please make sure you have *CiviCRM Event Organiser* version 0.6 or greater.

### CiviCRM Activity Types and WordPress Custom Post Types

To link these together, in CiviCRM go to *Administer* -> *Customize Data and Screens* -> *Activity Types* and edit an Activity Type. You will see a dropdown that allows you to choose a WordPress Post Type to link to the Activity Type you are editing. Choose one and save the Activity Type.

From now on, each time you create an Activity of the Activity Type that you have linked, a new WordPress Post will be created. The "Subject" of the Activity will become the WordPress Post Title. And - in the reverse direction - each time you create a WordPress Post which is of the Post Type you have linked to an Activity Type, a new Activity will be created with their "Subject" set to the Title of the new Post.

## Links between Fields

Once the link between a Contact Type and a Post Type has been made, go to the edit screen of an ACF Field Group. Create a Location Rule that places it on the edit screen of your linked Post Type. Now you can edit your ACF Fields themselves to integrate them with the Contact's data fields. These are split into two kinds:

* "Contact Fields" are those fields that are associated with a Contact by default, e.g. "First Name", "Last Name", "Gender", "Date of Birth" etc
* "Custom Fields" are part of a Custom Group that has been associated with a Contact Type (or other CiviCRM Entity), e.g. "Most Important Issue", "Marital Status" and "Marriage Date" in the "Constituent Information" Custom Group that is part of the CiviCRM sample data. These Fields are listed under the name of Custom Group to which they belong.

ACF Fields of type Select, Radio Button and Checkbox will have their CiviCRM values ported across to the ACF Field "choices" when the Field Group is saved. To link to a Multi-Select CiviCRM Field, you must enable "Select multiple values?" in the ACF Field's settings. If you make changes to the Option Values for a CiviCRM Custom Field mapped to one of these ACF Field Types, you will have to re-save the ACF Field Group(s) that contain the mapped ACF Fields.

Please note that in order to link some kinds of Fields, you may have to save the ACF Field Group before the CiviCRM Field selector shows up and populates correctly. In particular, this applies to:

* "Text" Fields - because ACF does not AJAX-refresh the Field Settings when this Field Type is chosen
* "Multi-Select" Fields - because these map to vanilla ACF "Select" Fields where "Select multiple values?" is checked and, again, there's no AJAX-refresh when the settings are modified
* "Auto-complete" Fields - because these also map to vanilla ACF "Select" Fields where "Stylised UI" and "Use AJAX to lazy load choices?" are checked and, here too, there's no AJAX-refresh

In each of these cases, choose the ACF Field Type, modify the Field Settings as required, save the Field Group, then re-edit the Field. The "CiviCRM Field" setting should then appear and populate appropriately.

### CiviCRM Contact Fields

The following are the Contact Fields and the kind of ACF Field needed to map them to CiviCRM:

* Prefix & Suffix - ACF Select
* First Name, Middle Name, Last Name - ACF Text
* Email - ACF Email
* Website - ACF Link
* Address - ACF CiviCRM Address (see "Custom ACF Fields" below)
* Address as Map - ACF Google Map (only available in ACF Pro)
* Gender - ACF Radio Button
* Date of Birth & Date of Death - ACF Date Picker
* Deceased - ACF True/False
* Job Title, Source & Nickname - ACF Text
* Employer - ACF CiviCRM Contact Reference (see "Custom ACF Fields" below)
* Phone - ACF CiviCRM Phone (see "Custom ACF Fields" below)
* Instant Messenger - ACF CiviCRM Instant Messenger (see "Custom ACF Fields" below)
* Contact Image - ACF Image

When you select a Field Type for an ACF Field, the "CiviCRM Field" dropdown in the ACF Field's Settings will only show you those CiviCRM Contact Fields which can be mapped to this type of ACF Field.

### CiviCRM Custom Fields

When creating a Custom Field in CiviCRM, you need to specify the kind of Data Type that it is - e.g. "Alphanumeric", "Integer", "Number", etc - followed by the Field Type - e.g. "Text", "Select", "Radio", etc. The Field Type pretty much corresponds to the kind of ACF Field that will map to the Custom Field. When you select a Field Type for an ACF Field, the "CiviCRM Field" dropdown in the ACF Field's Settings will only show you those CiviCRM Custom Fields which can be mapped to this type of ACF Field.

### Custom ACF Fields

The *CiviCRM ACF Integration* plugin also provides a number of custom ACF Fields which you will see as choices in the "Field Type" dropdown when you add a new ACF Field to a Field Group. These are:

#### CiviCRM Contact ID

Populates with the numeric ID of the synced CiviCRM Contact.

#### CiviCRM Contact Reference

Syncs with either a CiviCRM "Contact Reference" Custom Field or the "Current Employer" Contact Field.

#### CiviCRM Relationship

Syncs between the ACF Field and a CiviCRM Relationship.

#### CiviCRM Yes/No

Syncs between the ACF Field and a CiviCRM Yes/No Custom Field. This Field Type is necessary because a CiviCRM Yes/No Custom Field is actually a Yes/No/Unknown field and the ACF True-False Field does not allow Unknown.

#### CiviCRM Address

**NOTE: Requires ACF Pro.** Syncs with all the CiviCRM "Address" Contact Fields. Use the supplied template functions to display particular Addresses in your templates. Here are some examples:

```php
<p><strong><?php echo __( 'All Addresses as list:', 'your-slug' ); ?></strong>
<?php echo cacf_get_addresses( 'address_field' ); ?></p>

<p><strong><?php echo __( 'Primary Address:', 'your-slug' ); ?></strong>
<?php echo cacf_get_primary_address( 'address_field' ); ?></p>

<p><strong><?php echo __( 'Main Address:', 'your-slug' ); ?></strong>
<?php echo cacf_get_address_by_type_id( 'address_field', 3 ); ?></p>
```

You can also display Addresses using the [Shortcake](https://en-gb.wordpress.org/plugins/shortcode-ui/)-compatible `[cai_address]` Shortcode. The available attributes are:

* `field` (required) The ACF Field selector.
* `location_type` (optional) The desired Addresses Location Type ID.
* `post_id` (optional) If omitted, defaults to the current Post ID when used in The Loop. If `post_id` is specified, Addresses will be retrieved from the ACF Field attached to the Post with that ID.

Some examples might be:

* **All Addresses in the current Post:**
`[cai_address field="addresses" /]`
* **Home Address as `<address>` element from the Post with ID `2319`:**
`[cai_address field="addresses" location_type="1" post_id="2319"]`

You can narrow down what you display with two other Shortcodes: `[cai_city]` and `[cai_state]` both of which take the same attributes as `[cai_address]`. Some examples might be:

* **Home City from the Post with ID `2319`:**
`[cai_city field="addresses" location_type="1" post_id="2319"]`

* **Home State from the current Post:**
`[cai_state field="addresses" location_type="1"]`

#### CiviCRM Phone

**NOTE: Requires ACF Pro.** Syncs with all the CiviCRM "Phone" Contact Fields. Use the supplied template functions to display particular Phone Numbers in your templates. Here are some examples:

```php
<p><strong><?php echo __( 'Primary Phone Number:', 'your-slug' ); ?></strong> <?php echo cacf_get_primary_phone_number( 'phone_numbers' ); ?></p>

<p><strong><?php _e( 'All Numbers as list:', 'your-slug' ). ' '; ?></strong></p>
<?php echo cacf_get_phone_numbers( 'phone_numbers' ); ?>

<p><strong><?php _e( 'All Home Phone Numbers as list:', 'your-slug' ). ' '; ?></strong></p>
<?php echo cacf_get_phone_numbers_by_type_ids( 'phone_numbers', 1 ); ?>

<p><strong><?php echo __( 'All Home Phone Numbers as string:', 'your-slug' ); ?></strong><br />
<?php echo cacf_get_phone_numbers_by_type_ids( 'phone_numbers', 1, null, 'commas' );
?></p>

<p><strong><?php echo __( 'Voicemail:', 'your-slug' ). ' '; ?></strong>
<?php echo cacf_get_phone_numbers_by_type_ids( 'phone_numbers', 0, 5, 'commas' );
?></p>
```

You can also display Phone Numbers using the [Shortcake](https://en-gb.wordpress.org/plugins/shortcode-ui/)-compatible `[cai_phone]` Shortcode. The available attributes are:

* `field` (required) The ACF Field selector.
* `location_type` (optional) The desired Phone Location Type ID.
* `phone_type` (optional) The desired Phone Type ID.
* `style` (optional - default is `list`) Choosing `list` will display the Phone Numbers in an unordered list. Choosing `commas` will display the Phone Numbers as a comma-separated string, but when there is only one Phone Number it will not have a trailing comma.
* `post_id` (optional) If omitted, defaults to the current Post ID when used in The Loop. If `post_id` is specified, Phone Records will be retrieved from the ACF Field attached to the Post with that ID.

Some examples might be:

* **All Phone Numbers in the current Post as list:**
`[cai_phone field="phone_numbers" style="list" post_id="2319" /]`
* **All Home Phone Numbers as string from the Post with ID `2319`:**
`[cai_phone field="phone_numbers" location_type="1" style="commas" post_id="2319"]`

#### CiviCRM Instant Messenger

**NOTE: Requires ACF Pro.** Syncs with all the CiviCRM "Instant Messenger" Contact Fields. As with the "CiviCRM Phone" Field, use the supplied template functions to display particular Instant Messenger Records in your templates. Here are some examples:

```php
<p><strong><?php echo __( 'Primary IM:', 'your-slug' ); ?></strong> <?php echo cacf_get_primary_im( 'instant_messenger' ); ?></p>

<p><strong><?php _e( 'All IMs as list:', 'your-slug' ). ' '; ?></strong></p>
<?php echo cacf_get_ims( 'instant_messenger' ); ?>

<p><strong><?php _e( 'All Skype IMs as list:', 'your-slug' ). ' '; ?></strong></p>
<?php echo cacf_get_ims_by_type_ids( 'instant_messenger', null, 6, 'list' ); ?>
```

You can also display Instant Messenger Records using the [Shortcake](https://en-gb.wordpress.org/plugins/shortcode-ui/)-compatible `[cai_im]` Shortcode. The available attributes are:

* `field` (required) The ACF Field selector.
* `location_type` (optional) The desired Instant Messenger Location Type ID.
* `im_type` (optional) The desired Instant Messenger Provider ID.
* `style` (optional - default is `list`) Choosing `list` will display the Instant Messenger Records in an unordered list. Choosing `commas` will display the Instant Messenger Records as a comma-separated string, but when there is only one Record it will not have a trailing comma.
* `post_id` (optional) If omitted, defaults to the current Post ID when used in The Loop. If `post_id` is specified, Instant Messenger Records will be retrieved from the ACF Field attached to the Post with that ID.

Some examples might be:

* **All Instant Messenger Records from the current Post as list:**
`[cai_im field="instant_messenger" style="list" /]`
* **All Home Instant Messenger Records as string from the Post with ID `2319`:**
`[cai_im field="instant_messenger" location_type="1" style="commas" post_id="2319"]`

#### CiviCRM Activity Creator

Syncs between the ACF Field and the Creator of a CiviCRM Activity.

#### CiviCRM Activity Target

Syncs between the ACF Field and the Targets of a CiviCRM Activity.

#### CiviCRM Activity Assignee

Syncs between the ACF Field and the Assignee of a CiviCRM Activity.

## Outstanding Issues

### Bulk changes via the CiviCRM Contact Edit screen

Wholesale changes via the CiviCRM Contact Edit screen should be fully supported. However, there's a lot going on when Contacts are saved that way, so please [open an issue](https://github.com/christianwach/civicrm-acf-integration/issues) if you find anything that needs fixing.

### Changes to Custom Field settings

If you alter the settings of a CiviCRM Custom Field then the ACF Field(s) that are mapped to it will not automatically pick up those changes. For the time being, you will need to manually check that the settings on both sides are what you would expect them to be.

### File Field

There is no mapping between the CiviCRM "File" and the ACF "File" Field Types yet.

### Current Employer

There are oddities in CiviCRM's relationships, particularly the "Employer Of" relationship - which is both a "Relationship" and a "Contact Field". The ID of a Contact's "Current Employer" may be present in the `current_employer` field when retrieved via the CiviCRM API and can be set by populating the `employer_id` field.

The "Current Employer" can be mapped using an ACF Select Field, but sync will only take place when the ACF Field's value is changed or when the "Current Employer" field on the Contact is changed. It will not sync when the "Employer Of"/"Employee Of" Relationship is edited.

### Expired Relationships

CiviCRM's Relationships can be time-limited and the "Inactive Relationships" list on a Contact's Relationships tab shows both relationships that are Disabled and those that have a past End Date. An ACF Field mapped to such a Relationship will only update when the "Disable expired relationships" Scheduled Job runs and sets the Relationship's `is_active` property.

### Country & State/Province

These work when using either the "Select" or "Multi-Select" options in CiviCRM, however the "choices" in the ACF Field are dependent upon the Countries and State/Provinces that have been enabled in CiviCRM. If you change these settings, you will have to re-save the ACF Field Groups that contain the Fields that are mapped to them.

### WordPress Post Content

Since CiviCRM Contacts do not have a WYSIWYG field attached to them by default, there are (as yet) no options for syncing the Post Content to a Contact. To do something equivalent, use an ACF Field of type "Wysiwyg Editor" and map it to a CiviCRM Custom Field of type "Note/RichTextEditor". You can use the setting of an ACF Field Group to hide the Content Editor.

### CiviCRM Stylesheets

Unless you disable the CiviCRM Shortcode on a Post Type (via the settings page in CiviCRM Admin Utilities) then the CiviCRM Stylesheets load on the edit screen for that Post Type and interfere with the styling of ACF Select2 elements. It seems unlikely that the CiviCRM Shortcode would be useful in the Post Content of a linked Post Type, so disable it unless it's absolutely necessary.

## Credits

Many thanks to:

* [Ryan Waterbury](https://github.com/onedogsolutions) of [One Dog Solutions](https://onedog.solutions/) for funding the initial development of this plugin.
* [Tadpole Collective](https://tadpole.cc/) for funding the integration of CiviCRM Groups with WordPress Terms and the development of Manual Sync functionality.

## Installation

There are two ways to install from GitHub:

#### ZIP Download

If you have downloaded *CiviCRM ACF Integration* as a ZIP file from the git repository, do the following to install and activate the plugin:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/civicrm-acf-integration`
2. Activate the plugin
3. You are done!

#### git clone

If you have cloned the code from the git repository, it is assumed that you know what you're doing.

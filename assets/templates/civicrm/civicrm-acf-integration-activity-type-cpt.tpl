{* template block that contains the new fields *}
<table>
  <tr class="civicrm_acf_integration_block">
    <td class="label"><label for="civicrm_acf_integration_cpt">{$form.civicrm_acf_integration_cpt.label}</label></td>
    <td>{$form.civicrm_acf_integration_cpt.html}</td>
  </tr>
</table>

{* reposition the above blocks after #someOtherBlock *}
<script type="text/javascript">
  {literal}

  // jQuery will not move an item unless it is wrapped.
  cj('tr.civicrm_acf_integration_block').insertBefore('.crm-admin-options-form-block .crm-admin-options-form-block-weight');

  {/literal}
</script>

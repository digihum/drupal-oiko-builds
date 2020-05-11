<?php

/**
 * Format field group labels as HTML.
 */
function oiko_profile_post_update_0001() {
  foreach (['entity_form_display', 'entity_view_display'] as $entity_type) {
    foreach (\Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple() as $display) {
      /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $display */
      if (in_array('field_group', $display->getThirdPartyProviders())) {
        $updated = FALSE;

        $field_groups = $display->getThirdPartySettings('field_group');
        foreach ($field_groups as $group_name => $data) {
          if (!isset($data['format_settings']['fieldset_label_html'])) {
            $data['format_settings']['fieldset_label_html'] = TRUE;
            $display->setThirdPartySetting('field_group', $group_name, $data);
            $updated = TRUE;
          }
        }
        if ($updated) {
          $display->save();
        }
      }
    }
  }
}

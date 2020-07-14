<?php

/**
 * Converts p72_has_language to pa19_has_language.
 */
function medmus_cidoc_post_update_pa19_convert(&$sandbox) {
  $result = \Drupal::entityQuery('cidoc_reference')
    ->condition('property', 'p72_has_language')
    ->execute();
  if (!empty($result)) {
    foreach (\Drupal\cidoc\Entity\CidocReference::loadMultiple($result) as $reference) {
      $reference->set('property', 'pa19_has_language')->save();
    }
  }
}

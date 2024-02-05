<?php

/**
 * Update cidoc_entity to be revisionable.
 *
 * @throws \Exception
 *   Re-throws any exception raised during the update process.
 */
function cidoc_post_update_make_cidoc_entity_revisionable(&$sandbox) {
  throw new \Drupal\Core\Utility\UpdateException('This code has been removed, talk to a developer');
}

/**
 * Update cidoc_reference to be revisionable.
 *
 * @throws \Exception
 *   Re-throws any exception raised during the update process.
 */
function cidoc_post_update_make_cidoc_reference_revisionable(&$sandbox) {
  throw new \Drupal\Core\Utility\UpdateException('This code has been removed, talk to a developer');
}

/**
 * Ensure bidirectional references exist.
 */
function cidoc_post_update_bidirectional_properties2(&$sandbox) {
  // Allow bidirectional properties.
  \Drupal::state()->set('cidoc.maintain_reverse_relationships', 1);

  // Get a list of bidirectional properties.
  $properties = array_filter(\Drupal\cidoc\Entity\CidocProperty::loadMultiple(NULL), function ($property) {
    return $property->isBidirectional();
  });

  // Get a list of references of those properties and re-save them.
  if (!empty($properties)) {
    $result = \Drupal::entityQuery('cidoc_reference')
      ->condition('property', array_keys($properties), 'IN')
      ->execute();
    if (!empty($result)) {
      foreach (\Drupal\cidoc\Entity\CidocReference::loadMultiple($result) as $reference) {
        // Re-save the property to generate a bidirection partner.
        $reference->save();
      }
    }
  }
}

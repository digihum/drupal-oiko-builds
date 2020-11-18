<?php

use \Drupal\Core\Entity\Sql\SqlContentEntityStorageSchemaConverter;

/**
 * Update cidoc_entity to be revisionable.
 *
 * @throws \Exception
 *   Re-throws any exception raised during the update process.
 */
function cidoc_post_update_make_cidoc_entity_revisionable(&$sandbox) {
  // Temporarilty disable reverse relationship functionality.
  if ($state = \Drupal::state()->get('cidoc.maintain_reverse_relationships', 1)) {
    \Drupal::state()->set('cidoc.maintain_reverse_relationships', 0);
  }

  if ($load_state = \Drupal::state()->get('cidoc.populate_temporal_date_for_cache', 1)) {
    \Drupal::state()->set('cidoc.populate_temporal_date_for_cache', 0);
  }

  $schema_converter = new SqlContentEntityStorageSchemaConverter(
    'cidoc_entity',
    \Drupal::entityTypeManager(),
    \Drupal::entityDefinitionUpdateManager(),
    \Drupal::service('entity.last_installed_schema.repository'),
    \Drupal::keyValue('entity.storage_schema.sql'),
    \Drupal::database()
  );

  $schema_converter->convertToRevisionable(
    $sandbox,
    [
      'name',
      'internal_name',
      'content',
      'changed',
      'populated',
      'citation',
    ]
  );

  if ($state) {
    \Drupal::state()->delete('cidoc.maintain_reverse_relationships');
  }
  if ($load_state) {
    \Drupal::state()->delete('cidoc.populate_temporal_date_for_cache');
  }
}

/**
 * Update cidoc_reference to be revisionable.
 *
 * @throws \Exception
 *   Re-throws any exception raised during the update process.
 */
function cidoc_post_update_make_cidoc_reference_revisionable(&$sandbox) {
  // Temporarilty disable reverse relationship functionality.
  if ($state = \Drupal::state()->get('cidoc.maintain_reverse_relationships', 1)) {
    \Drupal::state()->set('cidoc.maintain_reverse_relationships', 0);
  }
  if ($load_state = \Drupal::state()->get('cidoc.populate_temporal_date_for_cache', 1)) {
    \Drupal::state()->set('cidoc.populate_temporal_date_for_cache', 0);
  }

  $schema_converter = new SqlContentEntityStorageSchemaConverter(
    'cidoc_reference',
    \Drupal::entityTypeManager(),
    \Drupal::entityDefinitionUpdateManager(),
    \Drupal::service('entity.last_installed_schema.repository'),
    \Drupal::keyValue('entity.storage_schema.sql'),
    \Drupal::database()
  );

  $schema_converter->convertToRevisionable(
    $sandbox,
    [
      'changed',
      'citation',
    ]
  );

  if ($state) {
    \Drupal::state()->delete('cidoc.maintain_reverse_relationships');
  }
  if ($load_state) {
    \Drupal::state()->delete('cidoc.populate_temporal_date_for_cache');
  }
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

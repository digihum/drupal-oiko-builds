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
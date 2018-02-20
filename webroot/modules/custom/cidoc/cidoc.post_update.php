<?php

use \Drupal\Core\Entity\Sql\SqlContentEntityStorageSchemaConverter;

/**
 * Update cidoc_entity to be revisionable.
 *
 * @throws \Exception
 *   Re-throws any exception raised during the update process.
 */
function cidoc_post_update_make_cidoc_entity_revisionable(&$sandbox) {
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
}

/**
 * Update cidoc_reference to be revisionable.
 *
 * @throws \Exception
 *   Re-throws any exception raised during the update process.
 */
function cidoc_post_update_make_cidoc_reference_revisionable(&$sandbox) {
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
}
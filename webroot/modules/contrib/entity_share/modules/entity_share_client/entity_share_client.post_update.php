<?php

/**
 * @file
 * Post update functions for Entity Share Client.
 */

declare(strict_types = 1);

use Drupal\Core\Entity\ContentEntityUpdater;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\entity_share_client\Entity\ImportConfig;

/**
 * Create a default import config to preserve 8.x-2.x behavior.
 */
function entity_share_client_post_update_create_default_import_config() {
  ImportConfig::create([
    'id' => 'default',
    'label' => t('Default'),
    'import_processor_settings' => [
      'block_field_block_content_importer' => [
        'max_recursion_depth' => -1,
        'weights' => [
          'prepare_importable_entity_data' => 20,
        ],
      ],
      'changed_time' => [
        'weights' => [
          'process_entity' => 100,
        ],
      ],
      'default_data_processor' => [
        'weights' => [
          'is_entity_importable' => -10,
          'post_entity_save' => 0,
          'prepare_importable_entity_data' => -100,
        ],
      ],
      'entity_reference' => [
        'max_recursion_depth' => -1,
        'weights' => [
          'process_entity' => 10,
        ],
      ],
      'physical_file' => [
        'weights' => [
          'process_entity' => 0,
        ],
      ],
    ],
  ])
    ->save();

  \Drupal::messenger()->addStatus(t('A default import config had been created. It is recommended to check it to ensure it matches your needs.'));
}

/**
 * Set the changed time on entity import statuses.
 */
function entity_share_client_post_update_compute_remote_import_changed_time(&$sandbox) {
  \Drupal::classResolver(ContentEntityUpdater::class)->update($sandbox, 'entity_import_status', function (EntityImportStatusInterface $status) {
    $needs_saving = FALSE;
    if (empty($status->getChangedTime())) {
      // Use the last import time if we have it, otherwise we'll use the current request
      // time.
      if (!empty($status->getLastImport())) {
        $status->setChangedTime($status->getLastImport());
      }
      $needs_saving = TRUE;
    }
    return $needs_saving;
  });
}

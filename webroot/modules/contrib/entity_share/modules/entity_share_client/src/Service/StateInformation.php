<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share\EntityShareUtility;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Class StateInformation.
 *
 * @package Drupal\entity_share_client\Service
 */
class StateInformation implements StateInformationInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The Drupal datetime service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * StateInformation constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Drupal time service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ResourceTypeRepositoryInterface $resource_type_repository,
    TimeInterface $time
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusInfo(array $data) {
    $status_info = [
      'label' => $this->t('Undefined'),
      'class' => 'entity-share-undefined',
      'info_id' => StateInformationInterface::INFO_ID_UNDEFINED,
      'local_entity_link' => NULL,
      'local_revision_id' => NULL,
    ];

    // Get the entity type and entity storage.
    $parsed_type = explode('--', $data['type']);
    $entity_type_id = $parsed_type[0];
    try {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    }
    catch (\Exception $exception) {
      $status_info = [
        'label' => $this->t('Unknown entity type'),
        'class' => 'entity-share-undefined',
        'info_id' => StateInformationInterface::INFO_ID_UNKNOWN,
        'local_entity_link' => NULL,
        'local_revision_id' => NULL,
      ];
      return $status_info;
    }

    // Check if an entity already exists.
    $existing_entities = $entity_storage
      ->loadByProperties(['uuid' => $data['id']]);

    if (empty($existing_entities)) {
      $status_info = [
        'label' => $this->t('New entity'),
        'class' => 'entity-share-new',
        'info_id' => StateInformationInterface::INFO_ID_NEW,
        'local_entity_link' => NULL,
        'local_revision_id' => NULL,
      ];
    }
    // An entity already exists.
    // Check if the entity type has a changed date.
    else {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $existing_entity */
      $existing_entity = array_shift($existing_entities);

      $resource_type = $this->resourceTypeRepository->get(
        $parsed_type[0],
        $parsed_type[1]
      );

      $changed_public_name = FALSE;
      if ($resource_type->hasField('changed')) {
        $changed_public_name = $resource_type->getPublicName('changed');
      }

      if (!empty($data['attributes'][$changed_public_name]) && method_exists($existing_entity, 'getChangedTime')) {
        $entity_changed_time = EntityShareUtility::convertChangedTime($data['attributes'][$changed_public_name]);

        $entity_keys = $entity_storage
          ->getEntityType()
          ->getKeys();
        // Case of translatable entity.
        if (isset($entity_keys['langcode']) && !empty($entity_keys['langcode'])) {
          $entity_language_id = $data['attributes'][$resource_type->getPublicName($entity_keys['langcode'])];

          // Entity has the translation.
          if ($existing_entity->hasTranslation($entity_language_id)) {
            $existing_translation = $existing_entity->getTranslation($entity_language_id);

            // Existing entity.
            if ($this->entityHasChanged($existing_translation, $entity_changed_time)) {
              $status_info = [
                'label' => $this->t('Entities not synchronized'),
                'class' => 'entity-share-changed',
                'info_id' => StateInformationInterface::INFO_ID_CHANGED,
                'local_entity_link' => $existing_entity->toUrl(),
                'local_revision_id' => $existing_entity->getRevisionId(),
              ];
            }
            else {
              $status_info = [
                'label' => $this->t('Entities synchronized'),
                'class' => 'entity-share-up-to-date',
                'info_id' => StateInformationInterface::INFO_ID_SYNCHRONIZED,
                'local_entity_link' => $existing_entity->toUrl(),
                'local_revision_id' => $existing_entity->getRevisionId(),
              ];
            }
          }
          else {
            $status_info = [
              'label' => $this->t('New translation'),
              'class' => 'entity-share-new',
              'info_id' => StateInformationInterface::INFO_ID_NEW_TRANSLATION,
              'local_entity_link' => $existing_entity->toUrl(),
              'local_revision_id' => $existing_entity->getRevisionId(),
            ];
          }
        }
        // Case of untranslatable entity.
        else {
          // Existing entity.
          if ($this->entityHasChanged($existing_entity, $entity_changed_time)) {
            $status_info = [
              'label' => $this->t('Entities not synchronized'),
              'class' => 'entity-share-changed',
              'info_id' => StateInformationInterface::INFO_ID_CHANGED,
              'local_entity_link' => $existing_entity->toUrl(),
              'local_revision_id' => $existing_entity->getRevisionId(),
            ];
          }
          else {
            $status_info = [
              'label' => $this->t('Entities synchronized'),
              'class' => 'entity-share-up-to-date',
              'info_id' => StateInformationInterface::INFO_ID_SYNCHRONIZED,
              'local_entity_link' => $existing_entity->toUrl(),
              'local_revision_id' => $existing_entity->getRevisionId(),
            ];
          }
        }
      }
    }

    return $status_info;
  }

  /**
   * Checks if the entity has changed on Remote before import.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   * @param int $remote_changed_time
   *   The timestamp of "changed" date on Remote.
   *
   * @return bool
   *   Whether the entity has changed on Remote before import.
   */
  protected function entityHasChanged(ContentEntityInterface $entity, int $remote_changed_time) {
    // We are determining if the entity has changed by comparing the dates.
    // The last import date must be after the remote changed date, otherwise
    // the entity has changed.
    if ($import_status_entity = $this->getImportStatusOfEntity($entity)) {
      return $import_status_entity->getLastImport() < $remote_changed_time;
    }
    // If for some reason the "Entity import status" entity doesn't exist,
    // simply compare by modification dates on remote and local.
    else {
      return $entity->getChangedTime() != $remote_changed_time;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createImportStatusOfEntity(ContentEntityInterface $entity, array $parameters) {
    try {
      $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
      $entity_import_status_data = [
        'entity_id' => $entity->id(),
        'entity_uuid' => $entity->uuid(),
        'entity_type_id' => $entity->getEntityTypeId(),
        'entity_bundle' => $entity->bundle(),
        'last_import' => $this->time->getRequestTime(),
      ];
      if ($entity_storage->getEntityType()->hasKey('langcode')) {
        $entity_import_status_data['langcode'] = $entity->language()->getId();
      }
      foreach (['remote_website', 'channel_id', 'policy'] as $additional_parameter) {
        if (!empty($parameters[$additional_parameter])) {
          $entity_import_status_data[$additional_parameter] = $parameters[$additional_parameter];
        }
      }
      $import_status_entity = $entity_storage->create($entity_import_status_data);
      $import_status_entity->save();
      return $import_status_entity;
    }
    catch (\Exception $e) {
      // @todo log the error.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getImportStatusByParameters(string $uuid, string $entity_type_id, string $langcode = NULL) {
    // A content entity can be uniquely identified by entity type, UUID and
    // language code (if entity type supports languages).
    $search_criteria = [
      'entity_uuid' => $uuid,
      'entity_type_id' => $entity_type_id,
    ];
    if ($langcode) {
      $search_criteria['langcode'] = $langcode;
    }
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface[] $import_status_entities */
    $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
    $import_status_entities = $entity_storage->loadByProperties($search_criteria);
    if (!empty($import_status_entities)) {
      return current($import_status_entities);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportStatusOfEntity(ContentEntityInterface $entity) {
    $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
    $langcode = NULL;
    if ($entity_storage->getEntityType()->hasKey('langcode')) {
      $langcode = $entity->language()->getId();
    }
    return $this->getImportStatusByParameters($entity->uuid(), $entity->getEntityTypeId(), $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteImportStatusOfEntity(EntityInterface $entity, string $langcode = NULL) {
    // If entity is not supported by "entity import", do nothing.
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if (in_array($entity->getEntityTypeId(), ['user', 'entity_import_status'])) {
      return;
    }
    $entity_storage = $this->entityTypeManager->getStorage('entity_import_status');
    $search_criteria = [
      'entity_uuid' => $entity->uuid(),
      'entity_type_id' => $entity->getEntityTypeId(),
    ];
    if ($langcode && $entity_storage->getEntityType()->hasKey('langcode')) {
      $search_criteria['langcode'] = $langcode;
    }
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface[] $import_status_entities */
    $import_status_entities = $entity_storage->loadByProperties($search_criteria);
    if ($import_status_entities) {
      foreach ($import_status_entities as $import_status_entity) {
        $import_status_entity->delete();
      }
    }
  }

}

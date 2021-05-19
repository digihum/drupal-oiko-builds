<?php

declare(strict_types = 1);

namespace Drupal\medmus_share\Entity;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;

/**
 * Defines the entity_import_status entity class.
 *
 * @ContentEntityType(
 *   id = "medmus_deleted_remote_entity",
 *   label = @Translation("Deleted remote entity"),
 *   label_collection = @Translation("Deleted remote entities"),
 *   base_table = "medmus_deleted_remote_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\medmus_share\DeletedRemoteEntityViewsData",
 *   },
 *   admin_permission = "administer_import_status_entities",
 *   links = {
 *     "canonical" = "/admin/content/entity_share/remote_deletes/{medmus_deleted_remote_entity}",
 *     "delete-form" = "/admin/content/entity_share/remote_deletes/{medmus_deleted_remote_entity}/delete",
 *     "collection" = "/admin/content/entity_share/remote_deletes",
 *   },
 * )
 */
class DeletedRemoteEntity extends ContentEntityBase implements DeletedRemoteEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    // The fields used to relate to the imported entity.
    $fields['tracking_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The identifier of remote entity status entry.'));

    $fields['entity_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity UUID'))
      ->setDescription(t('The UUID of synced entity.'));

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The identifier of entity type of imported entity.'));

    $fields['remote_website'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Remote website'))
      ->setDescription(t('The identifier of the remote website.'))
      ->setSetting('target_type', 'remote');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['decision'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Decision'))
      ->setDescription(t('The decision.'))
      ->setDefaultValue(DeletedRemoteEntityInterface::DECISION_UNDECIDED);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if (isset($this->overideChangedTime)) {
      $this->set('changed', $this->overideChangedTime);
    }
    else {
      $this->set('changed', \Drupal::service('datetime.time')->getRequestTime());
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getDecision() {
    return $this->get('decision')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDecision($decision) {
    $this->set('decision', $decision);
    return $this;
  }

  /**
   * Gets the timestamp of the last entity change.
   *
   * @return int
   *   The timestamp of the last entity save operation.
   */
  public function getChangedTime() {
    if (isset($this->overideChangedTime)) {
      return $this->overideChangedTime;
    }
    else {
      return $this->get('changed')->value;
    }
  }

  /**
   * Sets the timestamp of the last entity change.
   *
   * @param int $timestamp
   *   The timestamp of the last entity save operation.
   *
   * @return $this
   */
  public function setChangedTime($timestamp) {
    $this->overideChangedTime = $timestamp;
    return $this;
  }

  protected $overideChangedTime;

  /**
   * {@inheritdoc}
   */
  public static function getAvailableDecisions(): array {
    return [
      DeletedRemoteEntityInterface::DECISION_UNDECIDED => t('Undecided'),
      DeletedRemoteEntityInterface::DECISION_KEEP => t('Keep'),
      DeletedRemoteEntityInterface::DECISION_DELETE => t('Deleted'),
      DeletedRemoteEntityInterface::DECISION_DELETE_AUTOMATICALLY => t('Deleted automatically'),
    ];
  }

  /**
   * Change the access so that it ANDs with the local entity.
   *
   * @param string $operation
   * @param \Drupal\Core\Session\AccountInterface|null $account
   * @param false $return_as_object
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = parent::access($operation, $account, TRUE);
    if (($local_entity = $this->getLocalEntity()) && $local_entity instanceof AccessibleInterface) {
      $access->andIf($local_entity->access($operation, $account, TRUE));
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalEntity() {
    $entity_type = $this->get('entity_type_id')->value;
    $entity_uuid = $this->get('entity_uuid')->value;
    if (empty($entity_type) || empty($entity_uuid)) {
      return FALSE;
    }

    // Try loading the entity.
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    if ($local_entities = $storage->loadByProperties(['uuid' => $entity_uuid])) {
      return reset($local_entities);
    }
    else {
      return FALSE;
    }
  }

  public function label() {
    if (($local_entity = $this->getLocalEntity()) && ($local_entity instanceof EntityInterface)) {
      $params = [
        '%label' => $local_entity->label(),
      ];
      if (($remote = $this->get('remote_website')->entity) && ($remote instanceof EntityInterface)) {
        $params['%remote'] = $remote->label();
      }
      return t('Deletion of %label on %remote', $params);
    }
    return parent::label();
  }

}

<?php

namespace Drupal\medmus_share\Plugin\Action;

use Drupal\medmus_share\Entity\DeletedRemoteEntityInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Delete entity action with default confirmation form.
 *
 * @Action(
 *   id = "medmus_share_deletion_entity_keep",
 *   label = @Translation("Keep selected entities (Remote entity edition)"),
 *   type = "medmus_deleted_remote_entity",
 *   confirm = FALSE,
 * )
 */
class RemoteDeletionEntityKeepAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /* @var \Drupal\medmus_share\Entity\DeletedRemoteEntityInterface $entity */
    $entity->setDecision(DeletedRemoteEntityInterface::DECISION_KEEP);
    $entity->save();
    return $this->t('Keep entities');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}

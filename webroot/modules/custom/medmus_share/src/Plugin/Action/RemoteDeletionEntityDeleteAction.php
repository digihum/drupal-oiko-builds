<?php

namespace Drupal\medmus_share\Plugin\Action;

use Drupal\medmus_share\Entity\DeletedRemoteEntityInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Delete entity action with default confirmation form.
 *
 * @Action(
 *   id = "medmus_share_deletion_entity_delete",
 *   label = @Translation("Delete selected entities (Remote entity edition)"),
 *   type = "medmus_deleted_remote_entity",
 *   confirm = TRUE,
 * )
 */
class RemoteDeletionEntityDeleteAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $transaction = \Drupal::database()->startTransaction();
    /* @var \Drupal\medmus_share\Entity\DeletedRemoteEntityInterface $entity */
    $entity->setDecision(DeletedRemoteEntityInterface::DECISION_DELETE);
    if ($local_entity = $entity->getLocalEntity()) {
      $local_entity->delete();
    }
    $entity->save();
    return $this->t('Delete entities');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('delete', $account, $return_as_object);
  }

}

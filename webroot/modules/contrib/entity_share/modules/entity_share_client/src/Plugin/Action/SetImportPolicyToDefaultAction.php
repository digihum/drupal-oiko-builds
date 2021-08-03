<?php

namespace Drupal\entity_share_client\Plugin\Action;

use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Delete entity action with default confirmation form.
 *
 * @Action(
 *   id = "entity_share_client_set_import_policy_default",
 *   label = @Translation("Set import policy to default"),
 *   type = "entity_import_status",
 *   confirm = TRUE,
 * )
 */
class SetImportPolicyToDefaultAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->setPolicy(EntityImportStatusInterface::IMPORT_POLICY_DEFAULT)->save();
    // Enqueue a re-sync of this entity.
    $queue_helper = \Drupal::service('entity_share_async.queue_helper');
    $queue_helper->enqueue($entity->remote_website->value, $entity->channel_id->value, 'import', [$entity->entity_uuid->value]);
    return $this->t('Set import policy to default');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

}

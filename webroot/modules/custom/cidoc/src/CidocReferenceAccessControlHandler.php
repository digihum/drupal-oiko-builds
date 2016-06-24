<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for CIDOC references.
 *
 * @see \Drupal\cidoc\Entity\CidocReference.
 */
class CidocReferenceAccessControlHandler extends EntityAccessControlHandler {
  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\cidoc\CidocReferenceInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view cidoc references');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit cidoc references');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete cidoc references');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add cidoc references');
  }

}

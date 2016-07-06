<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for CIDOC entities and CIDOC reference entities.
 *
 * @see \Drupal\cidoc\Entity\CidocEntity.
 * @see \Drupal\cidoc\Entity\CidocReference.
 */
class CidocEntityAccessControlHandler extends EntityAccessControlHandler {
  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view label':
        if ($entity->getEntityTypeId() === 'cidoc_property') {
          return AccessResult::allowed();
        }
        // Intentially fall through.
      case 'view':
        // CIDOC references have no published method or property.
        if ($entity->getEntityTypeId() === 'cidoc_entity' && !$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished cidoc entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published cidoc entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit cidoc entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete cidoc entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add cidoc entities');
  }

}

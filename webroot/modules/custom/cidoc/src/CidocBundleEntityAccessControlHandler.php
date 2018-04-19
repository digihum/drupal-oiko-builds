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
class CidocBundleEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view published cidoc entities');

      default:
        return parent::checkAccess($entity, $operation, $account);
    }
  }

}

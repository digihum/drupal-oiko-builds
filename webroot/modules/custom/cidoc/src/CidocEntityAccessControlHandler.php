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
        // Intentionally fall through.
      case 'view':
        // CIDOC references have no published method or property.
        if ($entity->getEntityTypeId() === 'cidoc_entity' && !$entity->isPublished()) {
          return $this->checkGlobalPermissionOrOwnPermission('view unpublished cidoc entities', 'view own unpublished cidoc entities', $account, $entity);
        }
        return AccessResult::allowedIfHasPermission($account, 'view published cidoc entities')->addCacheableDependency($entity);

      case 'update':
        return $this->checkGlobalPermissionOrOwnPermission('edit cidoc entities', 'edit own cidoc entities', $account, $entity);

      case 'delete':
        return $this->checkGlobalPermissionOrOwnPermission('delete cidoc entities', 'delete own cidoc entities', $account, $entity);
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * Return an access result based on two permission + if this our entity.
   *
   * @param $global_permission
   * @param $own_permission
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return
   *   The access result.
   */
  protected function checkGlobalPermissionOrOwnPermission($global_permission, $own_permission, AccountInterface $account, EntityInterface $entity) {
    $global_access = AccessResult::allowedIfHasPermission($account, $global_permission);
    if (method_exists($entity, 'getOwnerId')) {
      $own_access = AccessResult::allowedIfHasPermission($account, $own_permission);
      $access_own_entity = AccessResult::allowedIf($account->id() == $entity->getOwnerId())->addCacheableDependency($entity);
      return $global_access->orIf($own_access->andIf($access_own_entity));
    }
    else {
      return $global_access;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add cidoc entities');
  }

}

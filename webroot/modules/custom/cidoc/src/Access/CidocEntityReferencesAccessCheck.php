<?php

namespace Drupal\cidoc\Access;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for CIDOC Entity references tabs.
 *
 * @ingroup cidoc_access
 */
class CidocEntityReferencesAccessCheck implements AccessInterface {

  /**
   * A static cache of access checks.
   *
   * @var array
   */
  protected $access = array();

  /**
   * Checks routing access for the CIDOC entity.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\cidoc\CidocEntityInterface $cidoc_entity
   *   (optional) A CIDOC entity object. If $cidoc_entity is not specified, then
   *   access is denied.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, CidocEntityInterface $cidoc_entity = NULL) {
    return AccessResult::allowedIf($cidoc_entity && $this->checkAccess($cidoc_entity, $account))->cachePerPermissions()->addCacheableDependency($cidoc_entity);
  }

  /**
   * Checks CIDOC entity property tabs access.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $cidoc_entity
   *   The CIDOC entity to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   *
   * @return bool
   *   TRUE if the operation may be performed, FALSE otherwise.
   */
  public function checkAccess(CidocEntityInterface $cidoc_entity, AccountInterface $account) {
    // Statically cache access by revision ID, language code, user account ID,
    // and operation.
    $langcode = $cidoc_entity->language()->getId();
    $cid = $cidoc_entity->getRevisionId() . ':' . $langcode . ':' . $account->id();

    if (!isset($this->access[$cid])) {
      // There should be at least one reference needing populating.
      $this->access[$cid] = count($cidoc_entity->getReferencesNeedingPopulating());
    }

    return $this->access[$cid];
  }


}

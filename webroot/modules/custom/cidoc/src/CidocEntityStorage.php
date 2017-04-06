<?php

namespace Drupal\cidoc;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for CIDOC entities.
 *
 * This extends the base storage class, adding required special handling for
 * CIDOC entities.
 */
class CidocEntityStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   *
   * Adds cache tags for dependencies.
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    $cache_tags = array(
      $this->entityTypeId . '_values',
      'entity_field_info',
      // @TODO Need more cache tags!
    );
    foreach ($entities as $id => $entity) {
      $this->cacheBackend->set($this->buildCacheId($id), $entity, CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Clear any entities that were tagged with these IDs as dependencies too.
   */
  public function resetCache(array $ids = NULL) {
    parent::resetCache($ids);
    // TODO: Invalidate tags.
  }

}

<?php

/**
 * @file
 * Contains \Drupal\cidoc_graphing\Controller\CidocGraphingController.
 */

namespace Drupal\cidoc_graphing\Controller;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;

class CidocGraphingController extends ControllerBase {
  public function graph($graph) {
    $display = array();
    
    // Just one graph 'all' at the moment.
    // Easy to add more later, though, and load the relevant library.
    switch ($graph) {
      case 'vis':
        $display = [
          '#type' => 'markup',
          '#markup' => '<div class="cidoc-graph-vis">Loading graph...</div>',
          '#attached' => [
            'library' => [
              'cidoc_graphing/graph-' . $graph,
            ],
          ],
        ];
        break;

      default:
        $graph = 'all';
        $display['#attached']['library'][] = 'cidoc_graphing/graph-' . $graph;
        break;
    }

    return $display;
  }

  public function entity() {
    $entity = CidocEntity::load(4377);
    $traversal = \Drupal::service('cidoc.graph_traversal');
    $entities = $traversal->findConnectedVertices($entity, NULL, 2);

    $references = [];
    /** @var \Drupal\cidoc\Entity\CidocReference $reference */
    foreach ($entities as $reference) {
      $references[] = [
        'id' => $reference->id(),
        'name' => $reference->label(),
      ];
    }

    $response = new CacheableJsonResponse($references);
    foreach ($entities as $entity) {
      $response->addCacheableDependency($entity);
    }
//    $definition = $this->entity_type_manager->getDefinition('cidoc_reference');
//    $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());
    return $response;
  }

//  public function reference() {
//    $references = [];
//
//    // Try to get all references.
//    $entity_storage = $this->entity_type_manager->getStorage('cidoc_reference');
//    $entities = $entity_storage->loadMultiple();
//
//    /** @var \Drupal\cidoc\Entity\CidocReference $reference */
//    foreach ($entities as $reference) {
//      $references[] = [
//        'domain' => $reference->domain->getValue()[0]['target_id'],
//        'property' => $reference->getPropertyLabel(),
//        'range' => $reference->range->getValue()[0]['target_id'],
//      ];
//    }
//
//    $response = new CacheableJsonResponse($references);
//    foreach ($entities as $entity) {
//      $response->addCacheableDependency($entity);
//    }
//    $definition = $this->entity_type_manager->getDefinition('cidoc_reference');
//    $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());
//    return $response;
//
//  }
}

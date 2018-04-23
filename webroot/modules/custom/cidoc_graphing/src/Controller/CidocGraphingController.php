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
    $data = $traversal->findConnectedVerticesAndEdges($entity, 2);
    $entities = $data['vertices'];
    $references = $data['edges'];

    $response = [
      'nodes' => [],
      'edges' => [],
    ];
    /** @var \Drupal\cidoc\Entity\CidocEntity $this_entity */
    foreach ($entities as $this_entity) {
      $response['nodes'][] = [
        'id' => $this_entity->id(),
        'label' => $this_entity->label(),
      ];
    }
    /** @var \Drupal\cidoc\Entity\CidocReference $reference */
    foreach ($references as $reference) {
      $response['edges'][] = [
        'property' => $reference->getFriendlyLabel(),
        'from' => $reference->getDomain(),
        'to' => $reference->getRange(),
        'bidirectional' => $reference->getReverseable(),
      ];
    }

    $response = new CacheableJsonResponse($response);
    foreach ($entities as $this_entity) {
      $response->addCacheableDependency($this_entity);
    }
    foreach ($references as $this_entity) {
      $response->addCacheableDependency($this_entity);
    }

    return $response;
  }

}

<?php

/**
 * @file
 * Contains \Drupal\cidoc_graphing\Controller\CidocGraphingController.
 */

namespace Drupal\cidoc_graphing\Controller;

use Drupal\Core\Controller\ControllerBase;

class CidocGraphingController extends ControllerBase {
  public function graph($graph) {
    $display = array();
    
    // Just one graph 'all' at the moment.
    // Easy to add more later, though, and load the relevant library.
    $graph = 'all';
    $display['#attached']['library'][] = 'cidoc_graphing/graph-' . $graph;
    
    return $display;
  }
}

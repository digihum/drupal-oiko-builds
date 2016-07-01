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
    $display['#attached']['library'][] = 'cidoc_graphing/d3';
    
    // @todo do we need to do a check_plain() (or equivalent) on $graph ?
    $display['#attached']['library'][] = 'cidoc_graphing/graph-' . $graph;
    
    return $display;
  }
}

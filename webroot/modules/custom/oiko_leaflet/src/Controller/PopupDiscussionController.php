<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;

/**
 * Class PopupDiscussionController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class PopupDiscussionController extends ControllerBase {

  /**
   * View.
   *
   * @return string
   *   Return Hello string.
   */
  public function view(CidocEntityInterface $cidoc_entity) {
    $view = Views::getView('discussions_listing');
    $view->setArguments([$cidoc_entity->id()]);
    $discussions = $view->render();

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('.sidebar-discussion-content-content', $discussions));
    return $response;

  }

}

<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\oiko_leaflet\Ajax\HistoryPushCommand;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class PopupDiscussionController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class PopupDiscussionController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entity_manager;
  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_manager) {
    $this->entity_manager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

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

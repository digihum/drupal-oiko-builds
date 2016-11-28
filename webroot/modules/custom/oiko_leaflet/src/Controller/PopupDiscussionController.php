<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\oiko_leaflet\Ajax\HistoryPushCommand;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManager;

/**
 * Class PopupDiscussionController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class PopupDiscussionController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var Drupal\Core\Entity\EntityManager
   */
  protected $entity_manager;
  /**
   * {@inheritdoc}
   */
  public function __construct(EntityManager $entity_manager) {
    $this->entity_manager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
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
    $pushData = [
      'type' => 'popup',
      'id' => $cidoc_entity->id(),
      'label' => $cidoc_entity->label(),
    ];
    $response->addCommand(new HistoryPushCommand($pushData, NULL, $cidoc_entity->toUrl()));
    return $response;

  }

}

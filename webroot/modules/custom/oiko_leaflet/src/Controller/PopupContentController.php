<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\oiko_leaflet\Ajax\EventHistoryAddCommand;
use Drupal\oiko_leaflet\Ajax\GAEventCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class PopupContentController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class PopupContentController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
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
    $view_builder = $this->entityTypeManager->getViewBuilder('cidoc_entity');

    $content = $view_builder->view($cidoc_entity, 'popup');

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('.sidebar-information-content-content', $content));
    if (!empty($cidoc_entity->getOwner())) {
      // Add in the GA response too.
      $response->addCommand(new GAEventCommand('pageview', [
        'dimension1' => $cidoc_entity->getOwner()
          ->id()
      ]));
    }
    // Add a response for our event history block.
    $response->addCommand(new EventHistoryAddCommand($cidoc_entity->id(), $cidoc_entity->label()));

    // Send the URL and label of the CIDOC entity to the client in case we need it there.
    $settings = [
      'oiko_app' => [
        'lookups' => [
          $cidoc_entity->id() => [
            'url' => $cidoc_entity->toUrl()->toString(),
            'label' => $cidoc_entity->label(),
          ],
        ],
      ],
    ];
    $response->addCommand(new SettingsCommand($settings, TRUE), TRUE);

    return $response;

  }

}

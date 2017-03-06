<?php

namespace Drupal\oiko_timeline\Controller;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocReference;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ComparativeTimelineController.
 *
 * @package Drupal\oiko_timeline\Controller
 */
class ComparativeTimelineController extends ControllerBase {

  /**
   * entityTypeManager definition.
   *
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * queryInterface definition.
   *
   * @var QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entityTypeManager, QueryFactory $entityQuery) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityQuery = $entityQuery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * Basepage.
   *
   * @return string
   *   Return Hello string.
   */
  public function basePage() {

    $content = array();

    $content['new_timeline'] = array(
      '#theme' => 'comparative_timeline',
    );

    return $content;
  }

  public function fetchData(CidocEntity $cidoc_entity) {
    $data = [
      'id' => $cidoc_entity->id(),
      'label' => $cidoc_entity->label(),
      'logo' => render($cidoc_entity->timeline_logo->view([
        'label' => 'hidden',
        'type' => 'image',
        'settings' => [
          'image_style' => 'comparative_timeline_logo',
        ],
      ])),
      'events' => [],
    ];

    // We need to get all events that happened at this place, and return times and other data for them.
    $events = $cidoc_entity->getChildEventEntities();

    foreach ($events as $event) {
      /** @var CidocEntity $event */
      $temporal = $event->getTemporalInformation();
      if (isset($temporal['minmin']) || isset($temporal['maxmax'])) {
        if (($significance = $event->significance->entity) && ($color = $significance->field_icon_color->getValue()[0]['value'])) {
          $event_color = $color;
        }
        else {
          $event_color = 'blue';
        }
        $data['events'][] = array(
          'type' => $event->bundle() == 'e4_period' ? 'period' : 'event',
          'uri' => $event->toUrl()->toString(),
          'id' => $event->id(),
          'label' => $event->label(),
          'date_title' => $temporal['human'],
          'minmin' => $temporal['minmin'],
          'maxmax' => $temporal['maxmax'],
          'color' => $event_color,
        );
      }
    }

    return new JsonResponse($data);
  }

}

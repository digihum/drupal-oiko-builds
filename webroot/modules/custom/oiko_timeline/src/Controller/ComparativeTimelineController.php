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


    // Give ourselves a container for the whole thing.
    $content['timeline_container'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'comparative-timeline-container',
        ),
      ),
      '#attached' => array(
        'library' =>  array(
          'oiko_timeline/comparative_timeline'
        ),
      ),
    );

    // And for the timeline itself
    $content['timeline_container']['timeline'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'timeline-wrapper',
        ),
      ),
    );

    // And for the timeline itself
    $content['timeline_container']['timeline.js'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'timeline-js-wrapper',
        'class' => array(
          'timeline-js-wrapper',
        ),
      ),
    );


    // Add a nice form for all the places we have in the system.
    $content['timeline_container']['places'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'places-wrapper',
        ),
      ),
    );

    // Places.
    $cidoc_entity_query = $this->entityQuery->get('cidoc_entity');
//      ->condition('bundle', 'e53_place');

    $place_ids = $cidoc_entity_query->execute();

    if (!empty($place_ids)) {
      foreach ($this->entityTypeManager->getStorage('cidoc_entity')
                 ->loadMultiple($place_ids) as $place) {
        /** @var CidocEntity $place */
        $content['timeline_container']['places'][$place->id()] = array(
          '#suffix' => ' | ',
          '#type' => 'link',
          '#attributes' => array(
            'class' => array(
              'event-data-lookup',
            ),
          ),
          '#title' => $place->label(),
          '#url' => Url::fromRoute('oiko_timeline.comparision_data', ['cidoc_entity' => $place->id()]),
        );
      }
    }




    return $content;
  }

  public function fetchData(CidocEntity $cidoc_entity) {
    $data = [
      'id' => $cidoc_entity->id(),
      'label' => $cidoc_entity->label(),
      'events' => [],
    ];

    // We need to get all events that happened at this place, and return times and other data for them.
    $events = $cidoc_entity->getChildEventEntities();

    foreach ($events as $event) {
      /** @var CidocEntity $event */
      $temporal = $event->getTemporalInformation();
      if (isset($temporal['minmin']) || isset($temporal['maxmax'])) {
        $data['events'][] = array(
          'type' => $event->bundle() == 'e4_period' ? 'period' : 'event',
          'id' => $event->id(),
          'label' => $event->label(),
          'date_title' => $temporal['human'],
          'minmin' => $temporal['minmin'],
          'maxmax' => $temporal['maxmax'],
        );
      }
    }

    return new JsonResponse($data);
  }

}

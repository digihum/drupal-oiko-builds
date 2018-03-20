<?php

namespace Drupal\oiko_cidoc\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\oiko_timeline\OikoTimelineHelpersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StudentTranscriptController.
 *
 * @package Drupal\oiko_cidoc\Controller
 */
class StudentTranscriptController extends ControllerBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * entityTypeManager definition.
   *
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Timeline helpers.
   *
   * @var OikoTimelineHelpersInterface
   */
  protected $oikoTimelineHelpers;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entityTypeManager, RendererInterface $renderer,OikoTimelineHelpersInterface $oikoTimelineHelpers) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->oikoTimelineHelpers = $oikoTimelineHelpers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('oiko_timeline.helpers')
    );
  }

  /**
   * Render array for a transcript.
   */
  public function transcript() {
    $response = [];

    // Timeline of entities.
    $storage = $this->entityTypeManager->getStorage('cidoc_entity');
    $query = $storage->getQuery();
    $query->condition('user_id', $this->currentUser()->id());
    $query->accessCheck(TRUE);
    $results = $query->execute();
    // Get the entities.
    $entities = $storage->loadMultiple($results);

    $data = [
      'id' => 0,
      'label' => $this->t('My events'),
      'logo' => '',
    ];
    $cache_metadata = [];
    $this->oikoTimelineHelpers->renderEventsForTimeline($entities, $cache_metadata, $data);
    $definition = $this->entityTypeManager->getDefinition('cidoc_entity');
    $response['timeline'] = [
      '#theme' => 'comparative_timeline',
      '#interactive' => FALSE,
      '#initialData' => $data,
      '#cache' => [
        'contexts' => [
          // We vary by the current user.
          'user',
        ],
        // Start with the tags for a list of entities.
        'tags' => $definition->getListCacheTags(),
      ],
    ];
    // Add the cache data to our render array.
    foreach ($cache_metadata as $entity) {
      $this->renderer->addCacheableDependency($response['timeline'], $entity);
    }

    // Map of entities.
    $response['map']['view'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_entities',
      '#display_id' => 'map',
      '#arguments' => [
        $this->currentUser()->id(),
      ],
    ];


    $response['cidoc_entities']['view'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_entities',
      '#display_id' => 'embed',
      '#arguments' => [
        $this->currentUser()->id(),
      ],
    ];

    $response['nodes']['view'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_nodes',
      '#display_id' => 'embed',
      '#arguments' => [
        $this->currentUser()->id(),
      ],
    ];

    return $response;
  }

}

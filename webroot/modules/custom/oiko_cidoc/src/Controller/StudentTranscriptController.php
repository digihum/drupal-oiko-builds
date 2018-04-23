<?php

namespace Drupal\oiko_cidoc\Controller;

use Drupal\cidoc\CidocEntityViewBuilder;
use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\GraphTraversal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\Entity\Node;
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
   * CIDOC graph traversal helpers.
   *
   * @var \Drupal\cidoc\GraphTraversal
   */
  protected $cidocGraphTraversal;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entityTypeManager, RendererInterface $renderer,OikoTimelineHelpersInterface $oikoTimelineHelpers, GraphTraversal $cidocGraphTraversal) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->oikoTimelineHelpers = $oikoTimelineHelpers;
    $this->cidocGraphTraversal = $cidocGraphTraversal;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('oiko_timeline.helpers'),
      $container->get('cidoc.graph_traversal')
    );
  }

  /**
   * Render array for a transcript.
   *
   * Transcripts are essentially a listing of narratives, followed by other
   * entities added by the user not used in the narratives.
   */
  public function transcript() {
    $response = [];

    // Timeline of entities.
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery();
    $query->condition('uid', $this->currentUser()->id());
    $query->sort('title');
    $query->accessCheck(TRUE);
    $results = $query->execute();
    // Get the entities.
    $narratives = $storage->loadMultiple($results);
    $rendered_entities = [];

    foreach ($narratives as $narrative) {
      $response['narratives'][$narrative->id()] = $this->transcriptNarrative($narrative);
      if (isset($response['narratives'][$narrative->id()]['#entities'])) {
        $rendered_entities = array_merge($rendered_entities, array_keys($response['narratives'][$narrative->id()]['#entities']));
      }
    }

    if ($remaining_entities = $this->getRemainingNarrativeEntities($rendered_entities)) {
      $cidoc_view_builder = $this->cidocViewBuilder();
      $response['others']['detail'] = [
        '#theme_wrappers' => [
          'container' => [
            '#attributes' => [
              'class' => 'student-transcript--detail',
            ],
          ],
        ],
      ];

      $response['others']['detail']['title'] = [
        '#markup' => $this->t('<h2>Other entities</h2>'),
      ];

      $response['others']['detail']['cidoc_entities'] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [],
        '#attributes' => [
          'class' => [
            'student-transcript--list',
          ],
        ],
      ];

      foreach ($remaining_entities as $key => $entity) {
        $response['others']['detail']['cidoc_entities']['#items'][$key] = $cidoc_view_builder->view($entity, 'transcript') + ['#wrapper_attributes' => ['class' => 'student-transcript--list-row']];
      }
    }

    $response['footnote'] = [
      '#type' => 'markup',
      '#markup' => $this->t(CidocEntityViewBuilder::TRANSCRIPT_NOT_MINE_DISCLAIMER),
    ];

    return $response;
  }

  protected function getRemainingNarrativeEntities($exclude = []) {
    $storage = $this->entityTypeManager->getStorage('cidoc_entity');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('user_id', $this->currentUser()->id());
    if (!empty($exclude)) {
      $query->condition('id', $exclude, 'NOT IN');
    }
    $ids = $query->execute();
    return !empty($ids) ? $storage->loadMultiple($ids) : [];
  }

  protected function computeEntitiesInNarrative(Node $narrative) {
    $entities = [];

    foreach ($narrative->field_crm_entities as $field_crm_entity) {
      $entities += $this->cidocGraphTraversal->findConnectedVertices($field_crm_entity->entity, [$this, 'isEntityOwnedByCurrentUser']);
    }

    return $entities;
  }

  public function isEntityOwnedByCurrentUser(CidocEntity $entity) {
    return $entity->getOwnerId() == $this->currentUser()->id();
  }



  /**
   * Compute the render array for a single narrative.
   *
   * @param \Drupal\node\Entity\Node $narrative
   *   The Narrative to compute the render array for.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function transcriptNarrative(Node $narrative) {
    $rendered_narrative = [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript',
          ],
        ],
      ],
    ];

    // These are the entities for this narrative.
    $entities = $this->computeEntitiesInNarrative($narrative);
    $rendered_narrative['#entities'] = $entities;

    // Summary visual grouping.
    $rendered_narrative['summary'] = [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript--summary',
          ],
        ],
      ],
    ];

    // Summary title.
    $rendered_narrative['summary']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => Html::escape($narrative->label()),
    ];

    $rendered_narrative['summary']['description'] = [
      $narrative->body->view(['label' => 'hidden']),
    ];

    // Timeline of entities.
    $data = [
      'id' => 0,
      'label' => $narrative->label(),
      'logo' => '',
    ];
    $cache_metadata = [];
    $this->oikoTimelineHelpers->renderEventsForTimeline($entities, $cache_metadata, $data);
    $cidoc_entity_definition = $this->entityTypeManager->getDefinition('cidoc_entity');
    $cidoc_view_builder = $this->cidocViewBuilder();

    $rendered_narrative['summary']['timeline'] = [
      '#theme' => 'comparative_timeline',
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript--high-level-item',
          ],
        ],
      ],
      '#interactive' => FALSE,
      '#initialData' => $data,
      '#cache' => [
        'contexts' => [
          // We vary by the current user.
          'user',
        ],
        // Start with the tags for a list of entities.
        'tags' => $cidoc_entity_definition->getListCacheTags(),
      ],
    ];
    // Add the cache data to our render array.
    foreach ($cache_metadata as $entity) {
      $this->renderer->addCacheableDependency($rendered_narrative['summary']['timeline'], $entity);
    }

    // Map of entities.
    $rendered_narrative['summary']['map'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_entities',
      '#display_id' => 'map',
      '#arguments' => [
        // Pump the entity ids into the view.
        implode('+', array_keys($entities)),
      ],
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript--high-level-item',
          ],
        ],
      ],
    ];

    // Listing of entities.
    $rendered_narrative['detail'] = [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript--detail',
          ],
        ],
      ],
    ];

    $rendered_narrative['detail']['title'] = [
      '#markup' => $this->t('<h2>In Detail</h2>'),
    ];

    $rendered_narrative['detail']['cidoc_entities'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [],
      '#attributes' => [
        'class' => [
          'student-transcript--list',
        ],
      ],
    ];

    foreach ($entities as $key => $entity) {
      $rendered_narrative['detail']['cidoc_entities']['#items'][$key] = $cidoc_view_builder->view($entity, 'transcript') + ['#wrapper_attributes' => ['class' => 'student-transcript--list-row', $key]];
    }

    return $rendered_narrative;
  }

  /**
   * @return \Drupal\cidoc\CidocEntityViewBuilder
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function cidocViewBuilder() {
    return $this->entityTypeManager->getHandler('cidoc_entity', 'view_builder');
  }

}

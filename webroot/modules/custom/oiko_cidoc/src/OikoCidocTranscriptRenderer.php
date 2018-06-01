<?php

namespace Drupal\oiko_cidoc;

use Drupal\cidoc\CidocEntityViewBuilder;
use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\GraphTraversal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\oiko_timeline\OikoTimelineHelpersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OikoCidocTranscriptRenderer {

  use StringTranslationTrait;

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

  protected function computeEntitiesInNarrative(Node $narrative) {
    $entities = [];

    foreach ($narrative->field_crm_entities as $field_crm_entity) {
      $entities += $this->cidocGraphTraversal->findConnectedVertices($field_crm_entity->entity, [$this, 'isEntityOwnedByCurrentUser']);
    }

    return $entities;
  }

  /**
   * @return \Drupal\cidoc\CidocEntityViewBuilder
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function cidocViewBuilder() {
    return $this->entityTypeManager->getHandler('cidoc_entity', 'view_builder');
  }

  /**
   * @return \Drupal\node\NodeViewBuilder
   */
  protected function nodeViewBuilder() {
    return $this->entityTypeManager->getHandler('node', 'view_builder');
  }

  /**
   * Compute the render array for a single narrative.
   *
   *
   *
   * @param \Drupal\node\Entity\Node $narrative
   *   The Narrative to compute the render array for.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function transcriptNarrative(Node $narrative) {
    $rendered_narrative = [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript  carbonite',
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

    $rendered_narrative['summary']['narrative'] = [
      $this->nodeViewBuilder()->view($narrative, 'transcript'),
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
            'class'=> 'student-transcript--high-level-item carbonite--victim',
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

    $rendered_narrative['summary']['map'] = [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class'=> 'student-transcript--high-level-item carbonite--victim',
          ],
        ],
      ],
    ];

    // Map of entities.
    $rendered_narrative['summary']['map']['view'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_entities',
      '#display_id' => 'map',
      '#arguments' => [
        // Pump the entity ids into the view.
        implode('+', array_keys($entities)),
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

  public function isEntityOwnedByCurrentUser(CidocEntity $entity) {
    return $entity->getOwnerId() == $this->currentUser()->id();
  }

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected function currentUser() {
    if (!$this->currentUser) {
      $this->currentUser = \Drupal::getContainer()->get('current_user');
    }
    return $this->currentUser;
  }
}

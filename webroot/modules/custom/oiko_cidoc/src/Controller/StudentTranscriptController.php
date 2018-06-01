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
use Drupal\oiko_cidoc\OikoCidocTranscriptRenderer;
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
   * CIDOC Transcript renderer.
   *
   * @var \Drupal\oiko_cidoc\OikoCidocTranscriptRenderer
   */
  protected $oikoCidocTranscriptRenderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entityTypeManager, RendererInterface $renderer,OikoTimelineHelpersInterface $oikoTimelineHelpers, GraphTraversal $cidocGraphTraversal, OikoCidocTranscriptRenderer $oikoCidocTranscriptRenderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->oikoTimelineHelpers = $oikoTimelineHelpers;
    $this->cidocGraphTraversal = $cidocGraphTraversal;
    $this->oikoCidocTranscriptRenderer = $oikoCidocTranscriptRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('oiko_timeline.helpers'),
      $container->get('cidoc.graph_traversal'),
      $container->get('oiko_cidoc.transcript_renderer')
    );
  }

  /**
   * The _title_callback for the transcript route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function transcriptTitle() {
    return $this
      ->t('Transcript - @name', [
        '@name' => $this->currentUser()->getDisplayName(),
      ]);
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
      $response['narratives'][$narrative->id()] = $this->oikoCidocTranscriptRenderer->transcriptNarrative($narrative);
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

  public function isEntityOwnedByCurrentUser(CidocEntity $entity) {
    return $entity->getOwnerId() == $this->currentUser()->id();
  }





  /**
   * @return \Drupal\cidoc\CidocEntityViewBuilder
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function cidocViewBuilder() {
    return $this->entityTypeManager->getHandler('cidoc_entity', 'view_builder');
  }

}

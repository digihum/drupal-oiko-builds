<?php

namespace Drupal\oiko_timeline\Controller;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\oiko_timeline\OikoTimelineHelpersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class ComparativeTimelineController.
 *
 * @package Drupal\oiko_timeline\Controller
 */
class ComparativeTimelineController extends ControllerBase {

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
  public function __construct(EntityTypeManager $entityTypeManager, RendererInterface $renderer, OikoTimelineHelpersInterface $oikoTimelineHelpers) {
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

  /**
   * Render a logo in a new render context.
   *
   * @param \Drupal\cidoc\Entity\CidocEntity $cidoc_entity
   * @return mixed
   */
  protected function renderTimelineLogo(CidocEntity $cidoc_entity) {
    return $this->renderer->executeInRenderContext(new RenderContext(), function() use ($cidoc_entity) {
      $view['logo'] = $cidoc_entity->timeline_logo->view([
        'label' => 'hidden',
        'type' => 'image',
        'settings' => [
          'image_style' => 'comparative_timeline_logo',
        ],
        'weight' => 10,
      ]);
      $view['subtitle'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => 'subtitle',
        ],
      ];
      $view['subtitle']['significance'] = $cidoc_entity->significance->view([
        'label' => 'visually_hidden',
        'type' => 'entity_reference_entity_view',
        'settings' => [
          'view_mode' => 'primary_historical_significance_pill',
          'link' => FALSE,
        ],
        'weight' => 2,
      ]);
      $view_builder_entity = \Drupal::entityTypeManager()->getViewBuilder('cidoc_entity');
      if ($spans = $cidoc_entity->getForwardReferencedEntities(['p4_has_time_span'])) {
        $view['subtitle']['cidoc_temporal_summary'] = $view_builder_entity->viewMultiple($spans, 'temporal_summary');
        $view['subtitle']['cidoc_temporal_summary']['weight'] = 1;
      }

      return render($view);
    });
  }

  public function fetchData(CidocEntity $cidoc_entity) {
    $data = [
      'id' => $cidoc_entity->id(),
      'label' => $cidoc_entity->label(),
      'logo' => $this->renderTimelineLogo($cidoc_entity),
      'events' => [],
    ];

    $entities = [$cidoc_entity];

    // We need to get all events that happened at this place, and return times and other data for them.
    $events = $cidoc_entity->getChildEventEntities();

    // Render those events.
    $this->oikoTimelineHelpers->renderEventsForTimeline($events, $entities, $data);

    $response = new CacheableJsonResponse($data);
    foreach ($entities as $entity) {
      $response->addCacheableDependency($entity);
    }
    $definition = $this->entityTypeManager->getDefinition('cidoc_entity');
    $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());
    return $response;
  }
}

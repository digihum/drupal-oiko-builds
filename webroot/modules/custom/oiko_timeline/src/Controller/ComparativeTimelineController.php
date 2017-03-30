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
   * queryInterface definition.
   *
   * @var QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entityTypeManager, QueryFactory $entityQuery, RendererInterface $renderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityQuery = $entityQuery;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.query'),
      $container->get('renderer')
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
      $view = $cidoc_entity->timeline_logo->view([
        'label' => 'hidden',
        'type' => 'image',
        'settings' => [
          'image_style' => 'comparative_timeline_logo',
        ],
      ]);
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

    foreach ($events as $event) {
      /** @var CidocEntity $event */
      $entities[] = $event;
      $temporal = $event->getTemporalInformation();
      if (isset($temporal['minmin']) || isset($temporal['maxmax'])) {
        if ($significance = $event->significance->entity) {
          $significance_id = $significance->id();
          if ($color = $significance->field_icon_color->getValue()[0]['value']) {
            $event_color = $color;
          }
          else {
            $event_color = 'blue';
          }
        }
        else {
          $significance_id = 0;
          $event_color = 'blue';
        }

        $events_uri = $event->toUrl()->toString(TRUE);
        $entities[] = $events_uri;
        $data['events'][] = array(
          'type' => $event->bundle() == 'e4_period' ? 'period' : 'event',
          'crm_type' => $event->bundle(),
          'uri' => $events_uri->getGeneratedUrl(),
          'id' => $event->id(),
          'label' => $event->getFriendlyLabel() . ': ' . $event->label(),
          'date_title' => $temporal['human'],
          'minmin' => $temporal['minmin'],
          'maxmax' => $temporal['maxmax'],
          'color' => $event_color,
          'significance' => $significance_id,
        );
      }
    }
    $response = new CacheableJsonResponse($data);
    foreach ($entities as $entity) {
      $response->addCacheableDependency($entity);
    }
    $definition = $this->entityTypeManager->getDefinition('cidoc_entity');
    $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());
    return $response;
  }

}

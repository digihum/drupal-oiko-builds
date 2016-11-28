<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Block\MainContentBlockPluginInterface;
use Drupal\Core\Block\MessagesBlockPluginInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\oiko_leaflet\Ajax\HistoryPushCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManager;

/**
 * Class PopupShareController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class PopupShareController extends ControllerBase {

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

    $build = [
      '#cache' => [
        'tags' => \Drupal::service('entity.manager')
          ->getDefinition('block')
          ->getListCacheTags(),
      ],
    ];
    $blockViewBuilder = \Drupal::service('entity.manager')
      ->getViewBuilder('block');
    // Load all region content assigned via blocks.
    $cacheable_metadata_list = [];
    $regions = \Drupal::service('block.repository')
      ->getVisibleBlocksPerRegion($cacheable_metadata_list);
    if (isset($regions['sidebar_share'])) {
      foreach ($regions['sidebar_share'] as $key => $block) {
        $block_plugin = $block->getPlugin();
        if ($block_plugin instanceof MainContentBlockPluginInterface) {
          continue;
        }
        elseif ($block_plugin instanceof TitleBlockPluginInterface) {
          continue;
        }
        elseif ($block_plugin instanceof MessagesBlockPluginInterface) {
          continue;
        }
        $build[$key] = $blockViewBuilder->view($block);
      }
    }
    $merged_cacheable_metadata = CacheableMetadata::createFromRenderArray($build);
    foreach ($cacheable_metadata_list as $cacheable_metadata) {
      $merged_cacheable_metadata = $merged_cacheable_metadata->merge($cacheable_metadata);
    }
    $merged_cacheable_metadata->applyTo($build);

    $share_links = [
      'title' => [
        '#markup' => '<h2>Share</h2><p>Use the buttons below to share a link straight to this point in history!</p>',
      ],
      'block' => $build,
    ];


    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('.sidebar-share-content-content', $share_links));
    $pushData = [
      'type' => 'popup',
      'id' => $cidoc_entity->id(),
      'label' => $cidoc_entity->label(),
    ];
    $response->addCommand(new HistoryPushCommand($pushData, NULL, $cidoc_entity->toUrl()));
    return $response;

  }

}

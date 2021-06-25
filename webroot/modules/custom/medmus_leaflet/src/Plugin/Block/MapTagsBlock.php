<?php

namespace Drupal\medmus_leaflet\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\oiko_leaflet\ItemColorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'MapTagsBlock' block.
 *
 * @Block(
 *  id = "map_tags_block",
 *  admin_label = @Translation("Map tags block"),
 * )
 */
class MapTagsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $eventTypeStorage;

  /**
   * @var \Drupal\oiko_leaflet\ItemColorInterface
   */
  protected $colorizer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('oiko_leaflet.item_color')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RendererInterface $renderer, EntityTypeManagerInterface $entity_type_manager, ItemColorInterface $colorizer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->eventTypeStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->colorizer = $colorizer;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $list_items = [];

    $renderer = $this->renderer;

    $list_items[] = [
      '#markup' => $this->t('Creations of literary works'),
      '#wrapper_attributes' => [
        'data-legend-tag' => MEDMUS_LEAFLET_TAGS_ID_CREATIONS_OF_LITERARY_WORKS,
      ],
    ];

    $list_items[] = [
      '#markup' => $this->t('Productions of manuscript sources'),
      '#wrapper_attributes' => [
        'data-legend-tag' => MEDMUS_LEAFLET_TAGS_ID_PRODUCTIONS_OF_MANUSCRIPT_SOURCES,
      ],
    ];

    $list_items[] = [
      '#markup' => $this->t("Author's activities"),
      '#wrapper_attributes' => [
        'data-legend-tag' => MEDMUS_LEAFLET_TAGS_ID_AUTHORS_ACTIVITIES,
      ],
    ];

    $list_items[] = [
      '#markup' => $this->t('Other historical events'),
      '#wrapper_attributes' => [
        'data-legend-tag' => MEDMUS_LEAFLET_TAGS_ID_OTHER_HISTORICAL_EVENTS,
      ],
    ];

    $list_items[] = [
      '#markup' => $this->t('Only creations of works with model/contrafactum/metrical affinities'),
      '#wrapper_attributes' => [
        'data-legend-tag' => MEDMUS_LEAFLET_TAGS_ID_ONLY_CREATIONS_OF_WORKS_WITH_MODEL_CONTRA,
      ],
    ];

    if (!empty($list_items)) {
      $build['map_tags_block'] = array(
        '#theme' => 'item_list',
        '#items' => $list_items,
        '#attributes' => [
          'class' => [
            'map-legend-icons',
            'js-map-tags-filterable-widget',
          ],
        ],
        '#attached' => [
          'library' => [
            'medmus_leaflet/tagsBlock',
          ],
        ],
      );
      $renderer->addCacheableDependency($build['map_tags_block'], $this->colorizer);
    }

    return $build;
  }

}

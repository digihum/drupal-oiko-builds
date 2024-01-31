<?php

namespace Drupal\oiko_leaflet\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\oiko_leaflet\ItemColorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'MapLegendBlock' block.
 *
 * @Block(
 *  id = "map_legend_block",
 *  admin_label = @Translation("Map legend block"),
 * )
 */
class MapLegendBlock extends BlockBase implements ContainerFactoryPluginInterface {


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
  public function defaultConfiguration() {
    return array(
      'marker_description' => 'Events at a specific location are marked on the map using coloured markers like this on. Hover, tap or click to see more information about the event at the location.',
      'cluster_description' => 'When more than one event is happening in the same place, or you need to zoom in to see them we group them together like this. Tap or click to reveal the events that have been grouped together.',
      'path_description' => 'Events that are moving something are indicated with lines. If we know the direction of travel then arrows will additionally be placed on the line.',
      'area_description' => 'If an event took place over an area, then we\'ll show it as a translucent area. Hover, tap or click to see more information about the event.',
      'empire_description' => 'Global Empires are shown as coloured areas with stripes. Hover or tap for a tooltip with the information for the Empire.',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map specific options'),
    ];

    $form['map']['marker_description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Marker description text'),
      '#default_value' => $this->configuration['marker_description'],
    );

    $form['map']['cluster_description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Cluster description text'),
      '#default_value' => $this->configuration['cluster_description'],
    );

    $form['map']['path_description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Path description text'),
      '#default_value' => $this->configuration['path_description'],
    );

    $form['map']['area_description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Area description text'),
      '#default_value' => $this->configuration['area_description'],
    );

    $form['map']['empire_description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Empire description text'),
      '#default_value' => $this->configuration['empire_description'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['marker_description'] = $form_state->getValue(['map', 'marker_description']);
    $this->configuration['cluster_description'] = $form_state->getValue(['map', 'cluster_description']);
    $this->configuration['path_description'] = $form_state->getValue(['map', 'path_description']);
    $this->configuration['area_description'] = $form_state->getValue(['map', 'area_description']);
    $this->configuration['empire_description'] = $form_state->getValue(['map', 'empire_description']);
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['map_legend_block']['#markup'] = 'Implement MapLegendBlock.';

    // Get a nice list of the taxononmy terms and their colours.

    $oiko_leaflet_module_path = \Drupal::service('extension.list.module')->getPath('oiko_leaflet');

    $icons = [
      'blue' => \Drupal::service('file_url_generator')->generateAbsoluteString($oiko_leaflet_module_path . '/images/icon-blue.png'),
      'green' => \Drupal::service('file_url_generator')->generateAbsoluteString($oiko_leaflet_module_path . '/images/icon-green.png'),
      'purple' => \Drupal::service('file_url_generator')->generateAbsoluteString($oiko_leaflet_module_path . '/images/icon-purple.png'),
      'red' => \Drupal::service('file_url_generator')->generateAbsoluteString($oiko_leaflet_module_path . '/images/icon-red.png'),
      'yellow' => \Drupal::service('file_url_generator')->generateAbsoluteString($oiko_leaflet_module_path . '/images/icon-yellow.png'),
      'turquoise' => \Drupal::service('file_url_generator')->generateAbsoluteString($oiko_leaflet_module_path . '/images/icon-turquoise.png'),
    ];

    $list_items = [];

    $renderer = $this->renderer;

    // @TODO: Decide if this should actually filter by Entity Class if colored by it.
    $terms = $this->eventTypeStorage->loadByProperties(array('vid' => 'event_types'));
    $show_icons = $this->colorizer->getColorSystem() == ItemColorInterface::COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE;
    if ($terms) {
      foreach ($terms as $term) {
        if ($show_icons) {
          if ($color = $term->field_icon_color->value) {
            if (isset($icons[$color])) {
              $image = [
                '#theme' => 'image',
                '#uri' => $icons[$color],
                '#alt' => $this->t('A @type marker', ['@type' => $term->label()]),
              ];
              $renderer->addCacheableDependency($image, $term);

              $list_items[] = [
                '#markup' => $this->t('@image&nbsp;&nbsp;@name', [
                  '@name' => $term->label(),
                  '@image' => $renderer->render($image),
                ]),
                '#wrapper_attributes' => [
                  'data-legend-category' => $term->id(),
                ],
              ];
            }
          }
        }
        else {
          $list_items[] = [
            '#markup' => $this->t('@name', [
              '@name' => $term->label(),
            ]),
            '#wrapper_attributes' => [
              'data-legend-category' => $term->id(),
            ],
          ];
        }
      }
    }

    if ($show_icons) {
      $image = [
        '#theme' => 'image',
        '#uri' => $icons['blue'],
      ];
      $list_items[] = [
        '#markup' => $this->t('@image&nbsp;&nbsp;@name', [
          '@name' => t('Other'),
          '@image' => $renderer->render($image),
        ]),
        '#wrapper_attributes' => [
          'data-legend-category' => 0,
        ],
      ];
    }
    else {
      $list_items[] = [
        '#markup' => $this->t('@name', [
          '@name' => t('Other'),
        ]),
        '#wrapper_attributes' => [
          'data-legend-category' => 0,
        ],
      ];
    }

    if (!empty($list_items)) {
      $build['map_legend_block'] = array(
        '#theme' => 'item_list',
        '#items' => $list_items,
        '#attributes' => [
          'class' => [
            'map-legend-icons',
            'js-map-legend-filterable-widget',
          ],
        ],
        '#attached' => [
          'library' => [
            'oiko_leaflet/legendBlock',
          ],
        ],
      );
      $renderer->addCacheableDependency($build['map_legend_block'], $this->colorizer);
    }


    // Add the examples of various features.

    $features = [];


    $items = [
      'marker',
      'cluster',
      'path',
      'area',
      'empire',
    ];
    foreach ($items as $item) {
      $image_uri = $oiko_leaflet_module_path . '/images/features/' . $item . '.png';
      if (!empty($this->configuration[$item . '_description']) && file_exists($image_uri)) {
        $features[] = [
          'image' => [
            '#theme' => 'image',
            '#uri' => \Drupal::service('file_url_generator')->generateAbsoluteString($image_uri),
            '#attributes' => [
              'class' => [
                'map-legend-features--image',
              ],
            ],
            '#alt' => $this->t('Example of a @type feature', ['@type' => $item]),
          ],
          'description' => [
            '#markup' => Xss::filterAdmin($this->configuration[$item . '_description']),
          ],
        ];
      }
    }

    if (!empty($features)) {
      $build['features'] = [
        '#theme' => 'item_list',
        '#items' => $features,
        '#attributes' => [
          'class' => [
            'map-legend-features',
            'no-bullet',
            'hide-on-timeline',
          ],
        ],
      ];
    }

    return $build;
  }

}

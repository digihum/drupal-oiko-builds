<?php

namespace Drupal\oiko_leaflet\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'MapLegendBlock' block.
 *
 * @Block(
 *  id = "map_legend_block",
 *  admin_label = @Translation("Map legend block"),
 * )
 */
class MapLegendBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['map_legend_block']['#markup'] = 'Implement MapLegendBlock.';

    // Get a nice list of the taxononmy terms and their colours.

    $icons = [
      'blue' => file_create_url(drupal_get_path('module', 'oiko_leaflet') . '/images/icon-blue.png'),
      'green' => file_create_url(drupal_get_path('module', 'oiko_leaflet') . '/images/icon-green.png'),
      'purple' => file_create_url(drupal_get_path('module', 'oiko_leaflet') . '/images/icon-purple.png'),
      'red' => file_create_url(drupal_get_path('module', 'oiko_leaflet') . '/images/icon-red.png'),
      'yellow' => file_create_url(drupal_get_path('module', 'oiko_leaflet') . '/images/icon-yellow.png'),
      'turquoise' => file_create_url(drupal_get_path('module', 'oiko_leaflet') . '/images/icon-turquoise.png'),
    ];

    $list_items = [];

    $renderer = \Drupal::service('renderer');

    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties(array('vid' => 'event_types'));
    if ($terms) {
      foreach ($terms as $term) {
        if ($color = $term->field_icon_color->value) {
          if (isset($icons[$color])) {
            $image = [
              '#theme' => 'image',
              '#uri' => $icons[$color],
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
    }

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
    }

    return $build;
  }

}

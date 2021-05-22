<?php

namespace Drupal\oiko_leaflet;


use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Event colorizer.
 */
interface ItemColorInterface {

  /**
   * Color entities by Primary Historical Significance.
   */
  const COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE = 'phs';

  /**
   * Color entities by Entity class.
   */
  const COLOR_SYSTEM_CIDOC_ENTITY_CLASS = 'entity_class';

  const ITEM_COLORS = [
    'blue' => 'Blue',
    'green' => 'Green',
    'purple' => 'Purple',
    'red' => 'Red',
    'yellow' => 'Yellow',
    'turquoise' => 'Turquoise',
  ];

  /**
   * Get the color system in use.
   *
   * @return string
   *   The color system.
   */
  public function getColorSystem(RefinableCacheableDependencyInterface $cacheableContext = NULL);

  /**
   * Get the color of a given CidicEntityInterface event.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $event
   *   The event to get the color of.
   */
  public function getColorForCidocEvent(CidocEntityInterface $event, RefinableCacheableDependencyInterface $cacheabilityMetadata);

}

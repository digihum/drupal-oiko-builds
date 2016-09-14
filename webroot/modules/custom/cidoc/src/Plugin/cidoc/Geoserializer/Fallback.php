<?php

namespace Drupal\cidoc\Plugin\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Geoserializer\GeoserializerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Null geospatial plugin to cover bundles that do not have specific handling.
 *
 * @CidocGeoserializer(
 *   id = "fallback",
 *   name = "Fallback",
 *   hidden = TRUE,
 * )
 */
class Fallback extends GeoserializerPluginBase {
  public function getGeospatialData(CidocEntityInterface $entity) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

}
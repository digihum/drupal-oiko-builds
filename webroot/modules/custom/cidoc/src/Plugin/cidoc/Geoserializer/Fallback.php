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

}

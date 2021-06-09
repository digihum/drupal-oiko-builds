<?php

namespace Drupal\oiko_leaflet\Plugin\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Geoserializer\GeoserializerPluginBase;

/**
 * Null geospatial plugin to cover bundles that do not have specific handling.
 *
 * @CidocGeoserializer(
 *   id = "oiko_leaflet_fake_data",
 *   name = "Oiko Leaflet Fake Data",
 *   hidden = TRUE,
 * )
 */
class OikoLeafletFakeData extends GeoserializerPluginBase {
  public function getGeospatialData(CidocEntityInterface $entity) {

    // Return some fake geospatial data.
    $points[] = [
      'type' => 'oiko_leaflet_fake_point',
    ];

    // Add labels to the points.
    foreach ($points as $id => $point) {
      $points[$id] = $this->addCommonPointValues($point, $entity);
    }

    return $points;
  }

}

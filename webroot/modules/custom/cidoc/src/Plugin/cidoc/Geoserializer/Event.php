<?php

namespace Drupal\cidoc\Plugin\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Geoserializer\GeoserializerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Null geospatial plugin to cover bundles that do not have specific handling.
 *
 * @CidocGeoserializer(
 *   id = "event",
 *   name = "Event",
 * )
 */
class Event extends GeoserializerPluginBase {
  public function getGeospatialData(CidocEntityInterface $entity) {
    $points = [];
    // Try and fetch the geodata from the related places.

    $place_entities = $entity->getForwardReferencedEntities(['p7_took_place_at']);
    foreach ($place_entities as $place_entity) {
      if ($place_entity->field_geodata->count()) {
        $entity->addCacheableDependency($place_entity);
        foreach ($place_entity->field_geodata->getValue() as $value) {
          $new_points = leaflet_process_geofield($value['value']);
          foreach ($new_points as $k => $v) {
            $new_points[$k]['location'] = $place_entity->label();
            if ($new_points[$k]['type'] !== 'point') {
              $new_points[$k]['centroid'] = [
                'lat' => $value['lat'],
                'lon' => $value['lon'],
              ];
            }
          }
          $points = array_merge($points, $new_points);
        }
      }
    }

    // Add labels to the points.
    foreach ($points as $id => $point) {
      $points[$id] = $this->addCommonPointValues($point, $entity);
    }

    return $this->filterDataPointsToSiteSettings($this->addTemporalDataToPoints($points, $entity));
  }

}

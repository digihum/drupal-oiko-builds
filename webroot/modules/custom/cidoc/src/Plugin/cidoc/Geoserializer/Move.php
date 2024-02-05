<?php

namespace Drupal\cidoc\Plugin\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Geoserializer\GeoserializerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Geospatial plugin to cover moves.
 *
 * @CidocGeoserializer(
 *   id = "move",
 *   name = "Move",
 * )
 */
class Move extends GeoserializerPluginBase {
  public function getGeospatialData(CidocEntityInterface $entity) {
    $points = [];
    // Try and fetch the geodata from the related places.
    $from_points = [];
    $to_points = [];

    $from_entities = $entity->getForwardReferencedEntities(['p27_moved_from']);
    foreach ($from_entities as $place_entity) {
      $values = [];
      if ($place_entity->field_geodata->count()) {
        $entity->addCacheableDependency($place_entity);
        foreach ($place_entity->field_geodata->getValue() as $value) {
          $values[] = $value['value'];
        }
        $new_points = \Drupal::service('leaflet.service')->leafletProcessGeofield($values);
        foreach ($new_points as $k => $v) {
          $new_points[$k]['location'] = $place_entity->label();
        }
        $from_points = array_merge($from_points, $new_points);
      }
    }

    $to_entities = $entity->getForwardReferencedEntities(['p26_moved_to']);
    foreach ($to_entities as $place_entity) {
      $values = [];
      if ($place_entity->field_geodata->count()) {
        $entity->addCacheableDependency($place_entity);
        foreach ($place_entity->field_geodata->getValue() as $value) {
          $values[] = $value['value'];
        }
        $new_points = \Drupal::service('leaflet.service')->leafletProcessGeofield($values);
        foreach ($new_points as $k => $v) {
          $new_points[$k]['location'] = $place_entity->label();
        }
        $to_points = array_merge($to_points, $new_points);
      }
    }

    $points = array_merge($points, $from_points);
    $points = array_merge($points, $to_points);

    // Now we want to add 'arrows' between the points.
    foreach ($from_points as $from_point) {
      if ($from_point['type'] == 'point') {
        foreach ($to_points as $to_point) {
          if ($to_point['type'] == 'point') {
            // We can only handle lines between points at the moment.
            $points[] = array(
              'type' => 'linestring',
              'directional' => TRUE,
              'location' => $this->t('@from to @to', array('@from' => $from_point['location'], '@to' => $to_point['location'])),
              'points' => array(
                array(
                  'lat' => $from_point['lat'],
                  'lon' => $from_point['lon'],
                ),
                array(
                  'lat' => $to_point['lat'],
                  'lon' => $to_point['lon'],
                ),
              ),
            );

          }
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

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

    $from_entities = $entity->getForwardReferences(['p27_moved_from']);
    foreach ($from_entities as $place_entity) {
      $values = [];
      foreach ($place_entity->field_geodata->getValue() as $value) {
        $values[] = $value['value'];
      }
      $from_points = array_merge($from_points, leaflet_process_geofield($values));
    }

    $to_entities = $entity->getForwardReferences(['p26_moved_to']);
    foreach ($to_entities as $place_entity) {
      $values = [];
      foreach ($place_entity->field_geodata->getValue() as $value) {
        $values[] = $value['value'];
      }
      $to_points = array_merge($to_points, leaflet_process_geofield($values));
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

    return $this->addTemporalDataToPoints($points, $entity);
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
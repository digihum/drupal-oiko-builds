<?php

namespace Drupal\medmus_leaflet\Controller;

use Consolidation\AnnotatedCommand\ParameterInjection;
use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\cidoc\Geoserializer\GeoserializerPluginManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oiko_leaflet\Ajax\EventHistoryAddCommand;
use Drupal\oiko_leaflet\Ajax\GAEventCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class PopupContentController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class RelatedMapMarkersController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * Geo serliazier plugin managr.
   *
   * @var \Drupal\cidoc\Geoserializer\GeoserializerPluginManagerInterface
   */
  protected $geoserializerPluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GeoserializerPluginManagerInterface $geoserializerPluginManager) {
    $this->entity_type_manager = $entity_type_manager;
    $this->geoserializerPluginManager = $geoserializerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.cidoc.geoserializer')
    );
  }

  protected function generateFakePointForEntity(CidocEntityInterface $cidocEntity) {
    if ($fake_plugin = $this->geoserializerPluginManager->createInstance('oiko_leaflet_fake_data')) {
      foreach ($fake_plugin->getGeospatialData($cidocEntity) as $fake_point) {
        return $fake_point;
      }
    }
  }

  /**
   * View.
   *
   * @return array
   *   The generated array of data.
   */
  protected function generateDataForEntity(CidocEntityInterface $cidoc_entity, CacheableResponseInterface $response = NULL) {
    if (isset($response)) {
      $response->addCacheableDependency($cidoc_entity);
    }

    $data = [];

    // Always add ourselves.

    if ($geospatial_data = $cidoc_entity->getGeospatialData()) {
      $data = array_merge($geospatial_data);
    }

    $fake_cicle_radius_inner = 3000;
    $creation_point = NULL;

    if ($cidoc_entity->bundle() == 'e65_creation') {

      // Make a fake circle we can use to place any fake points we need to make on.
      if (($geo = $cidoc_entity->getGeospatialData()) && count($geo) == 1) {
        foreach ($geo as $k => $v) {
          if ($v['type'] == 'point') {
            $creation_point = $v;
            $fake_circle = $this->generateCircleAtPoint($v, $fake_cicle_radius_inner, $fake_cicle_radius_inner * 3);
            break;
          }
        }
      }
      if (empty($fake_circle)) {
        return [];
      }

      // We want to get the works created by this event.
      $created_works = array_filter($cidoc_entity->getForwardReferencedEntities(['p94_has_created']), function ($cidocEntity) {
        return $cidocEntity->bundle() == 'ec1_work';
      });

      $related_to_work_nodes = [];
      $edges = [];

      $relationship = CidocProperty::load('pc2_is_contrafactum_of');

      // Now we want to get the works related to these created works.
      foreach ($created_works as $cidocEntity) {

        foreach ($cidocEntity->getForwardReferencedEntities(['pc2_is_contrafactum_of']) as $relatedWork) {
          // Ensure that this related work appears somewhere in our list of nodes.
          if (!in_array($relatedWork, $related_to_work_nodes)) {
            $related_to_work_nodes[] = $relatedWork;
            if (isset($response)) {
              $response->addCacheableDependency($relatedWork);
            }
          }
          // Add the edge.
          $edges[] = [
            'from' => $creation_point,
            'to' => $relatedWork,
            'label' => $this->t('@from <strong><em>@relationship</em></strong> @to', [
              '@from' => $cidocEntity->getName(),
              '@to' => $relatedWork->getName(),
              '@relationship' => $relationship->getFriendlyLabel(),
            ]),
          ];
        }

        // Now process the models.
        foreach ($cidocEntity->getReverseReferencedEntities(['pc2_is_contrafactum_of']) as $relatedWork) {
          // Ensure that this related work appears somewhere in our lists of nodes.
          if (!in_array($relatedWork, $related_to_work_nodes)) {
            $related_to_work_nodes[] = $relatedWork;
            if (isset($response)) {
              $response->addCacheableDependency($relatedWork);
            }
          }
          // Add the edge, but in the opposite direction to the one above.
          $edges[] = [
            'from' => $relatedWork,
            'to' => $creation_point,
            'label' => $this->t('@from <strong><em>@relationship</em></strong> @to', [
              '@from' => $relatedWork->getName(),
              '@to' => $cidocEntity->getName(),
              '@relationship' => $relationship->getReverseFriendlyLabel(),
            ]),
          ];
        }
      }

      // Now process the two lists of nodes to generate items we can place on a
      // map. We aim for two circles, centered on the requested item. The inner
      // circle has the directly related items placed upon it, the other has the
      // indirectly related items.
      if (!empty($edges)) {
        $fake_circle_outer = $fake_circle;

        // Generate fake points for each of the entities.
        $fake_outer_points = array_map([$this, 'generateFakePointForEntity'], $related_to_work_nodes);

        // Pop the points on the appropriate circle.
        $leaflet_outer_points = $this->generatePointsOnCircle($fake_outer_points, $fake_circle_outer);

        $leaflet_edges = [];

        foreach ($edges as $edge) {
          $leaflet_from_point = NULL;
          $leaflet_to_point = NULL;

          if (is_array($edge['from'])) {
            $leaflet_from_point = $edge['from'];
          }
          elseif ($keys = array_keys($related_to_work_nodes, $edge['from'])) {
            foreach ($keys as $k) {
              $leaflet_from_point = $leaflet_outer_points[$k];
              break;
            }
          }

          if (is_array($edge['to'])) {
            $leaflet_to_point = $edge['to'];
          }
          elseif ($keys = array_keys($related_to_work_nodes, $edge['to'])) {
            foreach ($keys as $k) {
              $leaflet_to_point = $leaflet_outer_points[$k];
              break;
            }
          }

          if (isset($leaflet_from_point, $leaflet_to_point)) {
            $leaflet_edges[] =  [
              'type' => 'linestring',
              'directional' => TRUE,
              'popup' => $edge['label'],
              // Unused but helpful for humans.
              'was_fake' => TRUE,
              'color' => 'deepblue',
              'points' => [
                [
                  'lat' => $leaflet_to_point['lat'],
                  'lon' => $leaflet_to_point['lon'],
                ],
                [
                  'lat' => $leaflet_from_point['lat'],
                  'lon' => $leaflet_from_point['lon'],
                ],
              ],
            ];
          }

        }

        // We want a sqaure that all our points will sit in, so increase the
        // size of the fake circle by 50% and work out where that is.
        $fake_circle_for_square = $fake_circle;
        $fake_circle_for_square['radius'] = $fake_cicle_radius_inner * 1.5;
        // Now generate 4 points on the circle, giving the extremes of lat/lng.
        $extremes = $this->generatePointsOnCircle(array_fill(0, 4, []), $fake_circle_for_square);
        $lat_min = min(array_column($extremes, 'lat'));
        $lat_max = max(array_column($extremes, 'lat'));
        $lon_min = min(array_column($extremes, 'lon'));
        $lon_max = max(array_column($extremes, 'lon'));
        // Form this into a valid 'Drupal leaflet' polygon.
        $polygon = [
          'type' => 'polygon',
          'points' => [
            ['lat' => $lat_min, 'lon' => $lon_min],
            ['lat' => $lat_max, 'lon' => $lon_min],
            ['lat' => $lat_max, 'lon' => $lon_max],
            ['lat' => $lat_min, 'lon' => $lon_max],
          ],
          'color' => 'white',
          'fillOpacity' => 0.975,
        ];

        $data = array_merge(
          [$polygon],
          $leaflet_edges,
          $leaflet_outer_points,
        );




      }

      // We want all of the added data to show on the map always.
      foreach ($data as $i => $row) {
        unset($data[$i]['temporal']);
      }

      return $data;
    }
    elseif ($cidoc_entity->bundle() == 'ec1_work') {
      $creation_entities = array_filter($cidoc_entity->getReverseReferencedEntities(['p94_has_created']), function ($cidocEntity) {
        return $cidocEntity->bundle() == 'e65_creation';
      });
      $data_arrays = array_map([$this, 'generateDataForEntity'], $creation_entities, array_fill(0, count($creation_entities), $response));
      return array_reduce($data_arrays, 'array_merge', array());
    }

    return $data;
  }

  /**
   * View.
   *
   * @return string
   *   Return Hello string.
   */
  public function fetch(CidocEntityInterface $cidoc_entity) {
    $response = new CacheableJsonResponse();
    return $response->setData($this->generateDataForEntity($cidoc_entity, $response));
  }

  /**
   * @param $point
   * @param int $fake_cicle_radius_inner
   *
   * @return mixed
   */
  protected function generateCircleAtPoint($point, int $fake_cicle_radius_inner, $offset_east = 0) {
    $fake_circle = $point;
    $fake_circle['type'] = 'circle';
    $fake_circle['radius'] = $fake_cicle_radius_inner;
    $fake_circle['style'] = [
      'fillOpacity' => '0.1',
      'weight' => '2',
    ];
    unset($fake_circle['popup']);

    if (!empty($offset_east)) {
      // Offset the point by this number of meters.
      // Convert to radians.
      $fake_circle['lat'] = deg2rad($fake_circle['lat']);
      $fake_circle['lon'] = deg2rad($fake_circle['lon']);

      $original_circle = $fake_circle;

      $point_angle = 0.5 * pi();
      // https://gis.stackexchange.com/questions/134513/calculating-a-point-lat-lon-from-another-lat-lon-point-angle
      // δ = distance r / Earth radius (both in the same units: meters).
      $delta = $offset_east / 6371009;
      // lat_P2 = asin(sin lat_O ⋅ cos δ + cos lat_O ⋅ sin δ ⋅ cos θ ).
      $fake_circle['lat'] = asin(sin($original_circle['lat']) * cos($delta) + cos($original_circle['lat']) * sin($delta) * cos($point_angle));
      // lon_P2 = lon_O + atan((sin θ ⋅ sin δ ⋅ cos lat_O) / (cos δ − sin lat_O ⋅ sin lat_P2)).
      $fake_circle['lon'] = $original_circle['lon'] + atan((sin($point_angle) * sin($delta) * cos($original_circle['lat'])) / (cos($delta) - sin($original_circle['lat'] * sin($original_circle['lat']))));

      // Convert back to degrees.
      $fake_circle['lat'] = rad2deg($fake_circle['lat']);
      $fake_circle['lon'] = rad2deg($fake_circle['lon']);
    }

    return $fake_circle;
  }

  protected function generatePointsOnCircle($points, $circle) {
    $number_of_points = count($points);

    // Convert to radians.
    $circle['lat'] = deg2rad($circle['lat']);
    $circle['lon'] = deg2rad($circle['lon']);
    $i = 0;
    foreach ($points as $k => $fake_point) {
      $point_on_circle = $fake_point;
      $point_on_circle['type'] = 'point';
      $point_angle = 2 * pi() * ($i + 1) / $number_of_points;
      // https://gis.stackexchange.com/questions/134513/calculating-a-point-lat-lon-from-another-lat-lon-point-angle
      // δ = distance r / Earth radius (both in the same units: meters).
      $delta = $circle['radius'] / 6371009;
      // lat_P2 = asin(sin lat_O ⋅ cos δ + cos lat_O ⋅ sin δ ⋅ cos θ ).
      $point_on_circle['lat'] = asin(sin($circle['lat']) * cos($delta) + cos($circle['lat']) * sin($delta) * cos($point_angle));
      // lon_P2 = lon_O + atan((sin θ ⋅ sin δ ⋅ cos lat_O) / (cos δ − sin lat_O ⋅ sin lat_P2)).
      $point_on_circle['lon'] = $circle['lon'] + atan((sin($point_angle) * sin($delta) * cos($circle['lat'])) / (cos($delta) - sin($circle['lat'] * sin($circle['lat']))));
      // Unused but helpful for humans.
      $point_on_circle['was_fake'] = TRUE;

      // Convert back to degrees.
      $point_on_circle['lat'] = rad2deg($point_on_circle['lat']);
      $point_on_circle['lon'] = rad2deg($point_on_circle['lon']);
      unset($point_on_circle['color']);
      $point_on_circle['markerClass'] = 'oiko-leaflet-marker-work';
      $points[$k] = $point_on_circle;
      $i++;
    }

    return $points;
  }

}

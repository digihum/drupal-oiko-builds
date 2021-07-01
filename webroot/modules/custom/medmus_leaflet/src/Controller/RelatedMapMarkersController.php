<?php

namespace Drupal\medmus_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\cidoc\Geoserializer\GeoserializerInterface;
use Drupal\cidoc\Geoserializer\GeoserializerPluginManagerInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\medmus_leaflet\Utility\RelatedMapMarkersResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GeoserializerPluginManagerInterface $geoserializerPluginManager, ModuleHandlerInterface $moduleHandler) {
    $this->entity_type_manager = $entity_type_manager;
    $this->geoserializerPluginManager = $geoserializerPluginManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.cidoc.geoserializer'),
      $container->get('module_handler')
    );
  }

  /**
   * @return GeoserializerInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getFakeGeoserializerPlugin() {
    if ($fake_plugin = $this->geoserializerPluginManager->createInstance('medmus_leaflet_fake_data')) {
      return $fake_plugin;
    }
  }

  protected function workHasMusic(CidocEntityInterface $work) {
    $has_music_responses = array_filter($this->moduleHandler->invokeAll('medmus_leaflet_work_has_music', [$work]));
    return !empty($has_music_responses);
  }

  /**
   * Get related works.
   *
   * @return RelatedMapMarkersResponse
   *   The generated response data..
   */
  protected function generateDataForEntity(CidocEntityInterface $cidoc_entity) {
    $responseData = new RelatedMapMarkersResponse($cidoc_entity);

    // If we created a work, then we want to add that to the map.
    if ($cidoc_entity->bundle() == 'e65_creation') {
      // We want to get the works created by this event.
      $created_works = array_filter($cidoc_entity->getForwardReferencedEntities(['p94_has_created']), function ($cidocEntity) {
        return $cidocEntity->bundle() == 'ec1_work';
      });

      // Loop over each of those created works.
      foreach ($created_works as $createdWorkEntity) {
        $source_points = [];
        // We are going to attribute each of these works to the creation event for
        // geo-location purposes.
        foreach ($cidoc_entity->getGeospatialData() as $geoDatum) {
          if ($geoDatum['type'] == 'point') {
            $geoDatum['label'] = $this->getFakeGeoserializerPlugin()
              ->getPointLabel($createdWorkEntity);
            $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()
              ->getPointPopup($createdWorkEntity);
            if ($this->workHasMusic($createdWorkEntity)) {
              $geoDatum['markerClass'] = 'medmus-leaflet-marker-music-upside-down';
            }
            $source_points[] = $responseData->addSourcePoint($geoDatum, $cidoc_entity, $createdWorkEntity);
          }
          elseif ($geoDatum['type'] == 'polygon' && !empty($geoDatum['centroid']['lat']) && !empty($geoDatum['centroid']['lon'])) {
            // We got a polygon, convert it to a point.
            $geoDatum['type'] = 'point';
            $geoDatum['lat'] = $geoDatum['centroid']['lat'];
            $geoDatum['lon'] = $geoDatum['centroid']['lon'];
            unset($geoDatum['points'], $geoDatum['centroid']);
            $geoDatum['label'] = $this->getFakeGeoserializerPlugin()
              ->getPointLabel($createdWorkEntity);
            $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()
              ->getPointPopup($createdWorkEntity);
            if ($this->workHasMusic($createdWorkEntity)) {
              $geoDatum['markerClass'] = 'medmus-leaflet-marker-music-upside-down';
            }
            $source_points[] = $responseData->addSourcePoint($geoDatum, $cidoc_entity, $createdWorkEntity);
          }

        }

        // Now we work to get contrafactums of those works.
        $relationship = CidocProperty::load('pc2_is_contrafactum_of');
        foreach ($createdWorkEntity->getForwardReferencedEntities(['pc2_is_contrafactum_of']) as $relatedWork) {
          $lineLabel = $this->t('@from <strong><em>@relationship</em></strong> @to', [
            '@from' => $createdWorkEntity->getName(),
            '@to' => $relatedWork->getName(),
            '@relationship' => $relationship->getFriendlyLabel(),
          ]);
          // Determine if _this_ work has a creation event with geospatial data.
          $relatedWorkCreationEvents = array_filter($relatedWork->getReverseReferencedEntities(['p94_has_created']), function ($cidocEntity) {
            return $cidocEntity->bundle() == 'e65_creation';
          });
          $relatedWorkAdded = FALSE;
          foreach ($relatedWorkCreationEvents as $relatedWorkCreationEventEntity) {
            foreach ($relatedWorkCreationEventEntity->getGeospatialData() as $geoDatum) {
              if ($geoDatum['type'] == 'point') {
                $relatedWorkAdded = TRUE;
                foreach ($source_points as $source_point_id) {
                  $geoDatum['label'] = $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork);
                  $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork);
                  if ($this->workHasMusic($relatedWork)) {
                    $geoDatum['markerClass'] = 'medmus-leaflet-marker-music-upside-down';
                  }
                  $responseData->addRealTargetPoint($source_point_id, $geoDatum, FALSE, $lineLabel, $relatedWork, $relatedWorkCreationEventEntity);
                }
              }
              elseif ($geoDatum['type'] == 'polygon' && !empty($geoDatum['centroid']['lat']) && !empty($geoDatum['centroid']['lon'])) {
                // We got a polygon, convert it to a point.
                $geoDatum['type'] = 'point';
                $geoDatum['lat'] = $geoDatum['centroid']['lat'];
                $geoDatum['lon'] = $geoDatum['centroid']['lon'];
                unset($geoDatum['points'], $geoDatum['centroid']);
                $relatedWorkAdded = TRUE;
                foreach ($source_points as $source_point_id) {
                  $geoDatum['label'] = $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork);
                  $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork);
                  if ($this->workHasMusic($relatedWork)) {
                    $geoDatum['markerClass'] = 'medmus-leaflet-marker-music-upside-down';
                  }
                  $responseData->addRealTargetPoint($source_point_id, $geoDatum, FALSE, $lineLabel, $relatedWork, $relatedWorkCreationEventEntity);
                }
              }
            }
          }
          if (!$relatedWorkAdded) {
            // This related work did not have a real geographic point, so
            // display it as a fake point instead.
            foreach ($source_points as $source_point_id) {
              $fake_target = [
                "label" => $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork),
                'popup' => $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork),
              ];
              if ($this->workHasMusic($relatedWork)) {
                $fake_target['markerClass'] = 'medmus-leaflet-marker-music';
              }
              $responseData->addFakeTargetPoint($source_point_id, $fake_target, FALSE, $lineLabel, $relatedWork);
            }
          }
        }

        // Now process the models.
        foreach ($createdWorkEntity->getReverseReferencedEntities(['pc2_is_contrafactum_of']) as $relatedWork) {
          $lineLabel = $this->t('@from <strong><em>@relationship</em></strong> @to', [
            '@from' => $createdWorkEntity->getName(),
            '@to' => $relatedWork->getName(),
            '@relationship' => $relationship->getReverseFriendlyLabel(),
          ]);
          // Determine if _this_ work has a creation event with geospatial data.
          $relatedWorkCreationEvents = array_filter($relatedWork->getReverseReferencedEntities(['p94_has_created']), function ($cidocEntity) {
            return $cidocEntity->bundle() == 'e65_creation';
          });
          $relatedWorkAdded = FALSE;
          foreach ($relatedWorkCreationEvents as $relatedWorkCreationEventEntity) {
            foreach ($relatedWorkCreationEventEntity->getGeospatialData() as $geoDatum) {
              // Process the data for real target points.
              if ($geoDatum['type'] == 'point') {
                $relatedWorkAdded = TRUE;
                foreach ($source_points as $source_point_id) {
                  $geoDatum['label'] = $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork);
                  $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork);
                  if ($this->workHasMusic($relatedWork)) {
                    $geoDatum['markerClass'] = 'medmus-leaflet-marker-music-upside-down';
                  }
                  $responseData->addRealTargetPoint($source_point_id, $geoDatum, TRUE, $lineLabel, $relatedWork, $relatedWorkCreationEventEntity);
                }
              }
              elseif ($geoDatum['type'] == 'polygon' && !empty($geoDatum['centroid']['lat']) && !empty($geoDatum['centroid']['lon'])) {
                // We got a polygon, convert it to a point.
                $geoDatum['type'] = 'point';
                $geoDatum['lat'] = $geoDatum['centroid']['lat'];
                $geoDatum['lon'] = $geoDatum['centroid']['lon'];
                unset($geoDatum['points'], $geoDatum['centroid']);
                $relatedWorkAdded = TRUE;
                foreach ($source_points as $source_point_id) {
                  $geoDatum['label'] = $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork);
                  $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork);
                  if ($this->workHasMusic($relatedWork)) {
                    $geoDatum['markerClass'] = 'medmus-leaflet-marker-music-upside-down';
                  }
                  $responseData->addRealTargetPoint($source_point_id, $geoDatum, TRUE, $lineLabel, $relatedWork, $relatedWorkCreationEventEntity);
                }
              }
            }
          }
          if (!$relatedWorkAdded) {
            // This related work did not have a real geographic point, so
            // display it as a fake point instead.
            foreach ($source_points as $source_point_id) {
              $fake_target = [
                "label" => $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork),
                'popup' => $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork),
              ];
              if ($this->workHasMusic($relatedWork)) {
                $fake_target['markerClass'] = 'medmus-leaflet-marker-music';
              }
              $responseData->addFakeTargetPoint($source_point_id, $fake_target, TRUE, $lineLabel, $relatedWork);
            }
          }
        }
      }
    }
    // This is a work, find the creation events and recursively call to
    // generate the data.
    elseif ($cidoc_entity->bundle() == 'ec1_work') {

      // Get the creation entities.
      $creation_entities = array_filter($cidoc_entity->getReverseReferencedEntities(['p94_has_created']), function ($cidocEntity) {
        return $cidocEntity->bundle() == 'e65_creation';
      });

      // Generate the data for each of those creations.
      $creation_responses = array_map(function ($creation_entity) {
        return $this->generateDataForEntity($creation_entity);
      }, $creation_entities);

      // Reduce the array of data to a single response.
      $responseData = array_reduce($creation_responses, function ($carry, $item) {
        if (isset($carry)) {
          return $carry->mergeWith($item);
        }
        else {
          return $item;
        }
      });
    }

    return $responseData;
  }

  /**
   * Get the related points for the given entity.
   *
   * We pass through to a helper method on this controller to do most of the
   * work.
   *
   * @return CacheableJsonResponse
   *   The JSON response.
   *
   */
  public function fetch(CidocEntityInterface $cidoc_entity) {
    $graphData = $this->generateDataForEntity($cidoc_entity);
    $response = new CacheableJsonResponse();
    return $response
      ->setData($graphData->toJsonData())
      ->addCacheableDependency($graphData);
  }
}

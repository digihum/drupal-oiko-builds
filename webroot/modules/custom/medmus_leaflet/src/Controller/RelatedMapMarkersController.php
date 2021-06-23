<?php

namespace Drupal\medmus_leaflet\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\cidoc\Geoserializer\GeoserializerInterface;
use Drupal\cidoc\Geoserializer\GeoserializerPluginManagerInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

  /**
   * @return GeoserializerInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getFakeGeoserializerPlugin() {
    if ($fake_plugin = $this->geoserializerPluginManager->createInstance('medmus_leaflet_fake_data')) {
      return $fake_plugin;
    }
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
            $source_points[] = $responseData->addSourcePoint($geoDatum, $cidoc_entity);
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
                  $responseData->addRealTargetPoint($source_point_id, $geoDatum, FALSE, $lineLabel, $createdWorkEntity, $relatedWorkCreationEventEntity);
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
              $responseData->addFakeTargetPoint($source_point_id, $fake_target, FALSE, $lineLabel, $createdWorkEntity);
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
              if ($geoDatum['type'] == 'point') {
                $relatedWorkAdded = TRUE;
                foreach ($source_points as $source_point_id) {
                  $geoDatum['label'] = $this->getFakeGeoserializerPlugin()->getPointLabel($relatedWork);
                  $geoDatum['popup'] = $this->getFakeGeoserializerPlugin()->getPointPopup($relatedWork);
                  $responseData->addRealTargetPoint($source_point_id, $geoDatum, TRUE, $lineLabel, $createdWorkEntity, $relatedWorkCreationEventEntity);
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
              $responseData->addFakeTargetPoint($source_point_id, $fake_target, TRUE, $lineLabel, $createdWorkEntity);
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
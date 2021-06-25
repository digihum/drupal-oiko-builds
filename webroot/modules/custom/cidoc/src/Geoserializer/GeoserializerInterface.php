<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

interface GeoserializerInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Get the (leaflet) geospatial data for this entity.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   An array of leaflet geospatial data.
   */
  public function getGeospatialData(CidocEntityInterface $entity);

  /**
   * An easy way to apply sitewide filtering to data points before returning.
   *
   * @param array $points
   *   The array of data points.
   */
  public function filterDataPointsToSiteSettings($points);

  /**
   * Get the label for the given entity.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $entity
   *   The entity of get the label for.
   *
   * @return mixed
   *   The label for the given point.
   */
  public function getPointLabel(CidocEntityInterface $entity);

  /**
   * Get the popup text for the given entity.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $entity
   *   The entity of get the popup text for.
   *
   * @return mixed
   *   The popup text for the given point.
   */
  public function getPointPopup(CidocEntityInterface $entity);

  /**
   * Get any tags for the given point and entity.
   *
   * @param array $point
   * @param \Drupal\cidoc\CidocEntityInterface $entity
   *
   * @return mixed
   */
  public function getPointTags(array $point, CidocEntityInterface $entity);

}

<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

interface GeoserializerInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ContainerFactoryPluginInterface {

  public function getGeospatialData(CidocEntityInterface $entity);

  /**
   * An easy way to apply sitewide filtering to data points before returning.
   *
   * @param array $points
   *   The array of data points.
   */
  public function filterDataPointsToSiteSettings($points);

}

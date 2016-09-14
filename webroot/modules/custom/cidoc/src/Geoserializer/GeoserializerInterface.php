<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

interface GeoserializerInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ContainerFactoryPluginInterface {

  public function getGeospatialData(CidocEntityInterface $entity);

}

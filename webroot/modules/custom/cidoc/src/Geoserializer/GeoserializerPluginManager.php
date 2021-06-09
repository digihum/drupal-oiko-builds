<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class GeoserializerPluginManager extends DefaultPluginManager implements GeoserializerPluginManagerInterface {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/cidoc/Geoserializer', $namespaces, $module_handler, 'Drupal\cidoc\Geoserializer\GeoserializerInterface', 'Drupal\cidoc\Annotation\CidocGeoserializer');
    $this->setCacheBackend($cache_backend, 'cidoc_geoserializer');
    $this->alterInfo('cidoc_geoserializer_info');
  }

  public function getFallbackPluginId($plugin_id, array $configuration = array()) {
    // We can always fallback to here.
    return 'fallback';
  }


}

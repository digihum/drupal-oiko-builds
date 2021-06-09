<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

interface GeoserializerPluginManagerInterface extends FallbackPluginManagerInterface, PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

}

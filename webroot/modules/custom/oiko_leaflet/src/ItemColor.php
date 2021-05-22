<?php

namespace Drupal\oiko_leaflet;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

final class ItemColor implements ItemColorInterface, CacheableDependencyInterface {

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * ItemColor constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('oiko_leaflet.settings');
  }

  /**
   * @inheritdoc
   */
  public function getColorSystem(RefinableCacheableDependencyInterface $cacheableContext = NULL) {
    if (!is_null($cacheableContext)) {
      $cacheableContext->addCacheableDependency($this);
    }
    return $this->config->get('color_system');
  }

  /**
   * @inheritdoc
   */
  public function getColorForCidocEvent(CidocEntityInterface $event, RefinableCacheableDependencyInterface $cacheabilityMetadata = NULL) {
    if (is_null($cacheabilityMetadata)) {
      $cacheabilityMetadata = $event;
    }
    switch ($this->getColorSystem($cacheabilityMetadata)) {
      case ItemColorInterface::COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE:
        return $this->colorByPrimaryHistoricalSignificance($event, $cacheabilityMetadata);

      case ItemColorInterface::COLOR_SYSTEM_CIDOC_ENTITY_CLASS:
        return $this->colorByCidocEntityClass($event, $cacheabilityMetadata);

      default:
        return 'blue';
    }
  }

  /**
   * Get the color of the entity by its primary historical significance.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $event
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheabilityMetadata
   *
   * @return mixed|string
   */
  protected function colorByPrimaryHistoricalSignificance(CidocEntityInterface $event, RefinableCacheableDependencyInterface $cacheabilityMetadata) {
    if ($significance = $event->significance->entity) {
      $cacheabilityMetadata->addCacheableDependency($significance);
      if ($color = $significance->field_icon_color->getValue()[0]['value']) {
        $event_color = $color;
      }
      else {
        $event_color = 'blue';
      }
    }
    else {
      $event_color = 'blue';
    }
    return $event_color;
  }

  /**
   * Get the color of the entity by its class.
   *
   * @param \Drupal\cidoc\CidocEntityInterface $event
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheabilityMetadata
   *
   * @return mixed
   */
  protected function colorByCidocEntityClass(CidocEntityInterface $event, RefinableCacheableDependencyInterface $cacheabilityMetadata) {
    $cacheabilityMetadata->addCacheableDependency($event->bundle->entity);
    return $event->bundle->entity->getThirdPartySetting('oiko_leaflet', 'item_color', 'blue');
  }

  /**
   * @inheritdoc
   */
  public function getCacheContexts() {
    return $this->config->getCacheContexts();
  }

  /**
   * @inheritdoc
   */
  public function getCacheTags() {
    return $this->config->getCacheTags();
  }

  /**
   * @inheritdoc
   */
  public function getCacheMaxAge() {
    return $this->config->getCacheMaxAge();
  }


}

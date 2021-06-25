<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Site\Settings;
use Drupal\oiko_leaflet\ItemColorInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class GeoserializerPluginBase extends PluginBase implements GeoserializerInterface {

  /**
   * @var \Drupal\oiko_leaflet\ItemColorInterface
   */
  protected $colorizer;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, \Drupal\oiko_leaflet\ItemColorInterface $colorizer, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->colorizer = $colorizer;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oiko_leaflet.item_color'),
      $container->get('module_handler')
    );
  }

  public function getPluginName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPointLabel(CidocEntityInterface $entity) {
    return $entity->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getPointPopup(CidocEntityInterface $entity) {
    $args = array(
      '@entity_type' => $entity->getFriendlyLabel(),
      '@label' => $entity->getName(),
      '@color' => $this->colorizer->getColorForCidocEvent($entity),
    );

    if ($significance = $entity->significance->entity) {
      $args['@category'] = $significance->label();
      if ($this->colorizer->getColorSystem($entity) == ItemColorInterface::COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE) {
        $label = '<div class="category-label category-label--@color">@category</div> <em>@entity_type</em>: @label';
      }
      else {
        $label = '<em>@category</em> <div class="category-label category-label--@color">@entity_type</div>: @label';
      }
    }
    else {
      if ($this->colorizer->getColorSystem($entity) == ItemColorInterface::COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE) {
        $label = '<em>@entity_type</em>: @label';
      }
      else {
        $label = '<div class="category-label category-label--@color">@entity_type</div>: @label';
      }
    }

    return $this->t($label, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function getPointTags(array $point, CidocEntityInterface $entity) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function addCommonPointValues(array $point, CidocEntityInterface $entity) {
    $point['label'] = $this->getPointLabel($entity);
    $point['id'] = $entity->id();
    $point['color'] = $this->colorizer->getColorForCidocEvent($entity);
    $point['popup'] = $this->getPointPopup($entity);

    if ($significance = $entity->significance->entity) {
      $entity->addCacheableDependency($significance);
      $point['significance_id'] = $significance->id();
      $point['significance'] = $significance->label();
    }

    $point['tags'] = $this->getPointTags($point, $entity);
    // Allow modules to alter the tags.
    $this->moduleHandler->alter('cidoc_geoserializer_point_tags', $point['tags'], $point, $entity);

    return $point;
  }

  /**
   * Add the temporal data to points (where available).
   */
  protected function addTemporalDataToPoints(array $points, CidocEntityInterface $entity) {
    // Try and fetch temporal data from the related time.

    $temporalData = $entity->getTemporalInformation();
    if  (!empty($temporalData)) {
      $temporalData = [$temporalData];
    }

    // We want to add all this temporal data to each point above.
    if (!empty($temporalData) && !empty($points)) {
      $temporalPoints = [];
      foreach ($points as $point) {
        foreach ($temporalData as $temporalDatum) {
          $point['temporal'] = array(
            'minmin' => $temporalDatum['minmin'],
            'maxmax' => $temporalDatum['maxmax'],
          );
          $temporalPoints[] = $point;
        }
      }

      $points = $temporalPoints;
    }

    return $points;
  }

  /**
   * An easy way to apply sitewide filtering to data points before returning.
   *
   * @param array $points
   *   The array of data points.
   */
  public function filterDataPointsToSiteSettings($points) {
    if (!Settings::get('cidoc_show_entities_without_temporal_data_on_map', TRUE)) {
      $points = array_filter($points, function ($point) {
        return isset($point['temporal']);
      });
    }
    return $points;
  }

}

<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\oiko_leaflet\ItemColorInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class GeoserializerPluginBase extends PluginBase implements GeoserializerInterface {

  /**
   * @var \Drupal\oiko_leaflet\ItemColorInterface
   */
  protected $colorizer;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, \Drupal\oiko_leaflet\ItemColorInterface $colorizer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->colorizer = $colorizer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oiko_leaflet.item_color')
    );
  }

  public function getPluginName() {
    return $this->pluginDefinition['name'];
  }

  protected function addCommonPointValues(array $point, CidocEntityInterface $entity) {
    $point['label'] = $entity->getName();
    $point['id'] = $entity->id();
    $point['color'] = $this->colorizer->getColorForCidocEvent($entity);

    if ($significance = $entity->significance->entity) {
      $entity->addCacheableDependency($significance);
      $point['significance_id'] = $significance->id();
      $point['significance'] = $significance->label();
      if ($this->colorizer->getColorSystem($entity) == ItemColorInterface::COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE) {
        $label = '<div class="category-label category-label--@color">@category</div> <em>@entity_type</em>: @label';
      }
      else {
        $label = '<em>@category</em> <div class="category-label category-label--@color">@entity_type</div>: @label';
      }
      $point['popup'] = $this->t($label, array(
        '@category' => $point['significance'],
        '@entity_type' => $entity->getFriendlyLabel(),
        '@label' => $entity->getName(),
        '@color' => $point['color'],
      ));
    }
    else {
      $point['popup'] = $this->t('<div class="category-label category-label--@color">@entity_type</div>: @label', array(
        '@entity_type' => $entity->getFriendlyLabel(),
        '@label' => $entity->getName(),
        '@color' => $point['color'],
      ));
    }



    return $point;
  }

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

}

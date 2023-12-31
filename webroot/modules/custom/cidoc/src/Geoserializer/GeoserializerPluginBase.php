<?php

namespace Drupal\cidoc\Geoserializer;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Plugin\PluginBase;

abstract class GeoserializerPluginBase extends PluginBase implements GeoserializerInterface {

  // Here, provide default implementations for all methods of the interface (for which it makes sense).

  public function getPluginName() {
    return $this->pluginDefinition['name'];
  }

  protected function addCommonPointValues(array $point, CidocEntityInterface $entity) {
    $point['label'] = $entity->getName();
    $point['id'] = $entity->id();

    if ($significance = $entity->significance->entity) {
      $entity->addCacheableDependency($significance);
      $point['significance_id'] = $significance->id();
      $point['significance'] = $significance->label();
      // Convert cultural significance to color.
      if ($color = $significance->field_icon_color->getValue()[0]['value']) {
        $point['color'] = $color;
      }
      else {
        $point['color'] = 'blue';
      }
      $point['popup'] = $this->t('<div class="category-label category-label--@color">@category</div> <em>@entity_type</em>: @label', array(
        '@category' => $point['significance'],
        '@entity_type' => $entity->getFriendlyLabel(),
        '@label' => $entity->getName(),
        '@color' => $point['color'],
      ));
    }
    else {
      $point['popup'] = $this->t('<em>@entity_type</em>: @label', array(
        '@entity_type' => $entity->getFriendlyLabel(),
        '@label' => $entity->getName(),
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
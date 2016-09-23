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
    $point['popup'] = $this->t('@entity_type: @link', array(
      '@entity_type' => $entity->bundleLabel(),
      '@link' => $entity->toLink($entity->getName())->toString(),
    ));
    $point['id'] = $entity->id();

    // Convert cultural significance to color.
    if (($significance = $entity->significance->entity) && ($color = $significance->field_icon_color->getValue()[0]['value'])) {
      $point['color'] = $color;
    }
    else {
      $point['color'] = 'blue';
    }
    return $point;
  }

  protected function addTemporalDataToPoints(array $points, CidocEntityInterface $entity) {
    // Try and fetch temporal data from the related time.

    $temporalData = $entity->getTemporalInformation();
    // @TODO: convert this to an array of return values.
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
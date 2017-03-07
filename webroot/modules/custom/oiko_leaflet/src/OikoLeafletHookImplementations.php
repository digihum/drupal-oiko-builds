<?php

namespace Drupal\oiko_leaflet;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocEntityBundle;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Render\Element\Link;
use Drupal\Core\Url;


/**
 * Class OikoTimelineHookImplementations.
 *
 * @package Drupal\oiko_timeline
 */
class OikoLeafletHookImplementations {

  public function extra_field_info() {
    $extra = [];
    foreach (CidocEntityBundle::loadMultiple() as $bundle) {
      $extra['cidoc_entity'][$bundle->id()]['display']['map_link'] = array(
        'label' => t('Map link'),
        'weight' => 0,
        'visible' => FALSE,
      );
    }
    return $extra;
  }

  public function entity_view_alter(array &$build, \Drupal\Core\Entity\EntityInterface $entity, \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display) {
    if ($entity->getEntityTypeId() == 'cidoc_entity') {
      // If we'd get any results on the timeline, add a link to it.
      /** @var CidocEntity $entity */
      if ($display->getComponent('map_link') && $entity->hasGeospatialData()) {
        $build['map_link'] = \Drupal\Core\Link::createFromRoute(t('View %title on the map.', ['%title' => $entity->label()]), 'entity.cidoc_entity.canonical', ['cidoc_entity' => $entity->id()])
          ->toRenderable();
      }
    }
  }
}


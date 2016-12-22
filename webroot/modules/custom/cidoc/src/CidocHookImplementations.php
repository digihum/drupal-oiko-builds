<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocEntityBundle;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Render\Element\Link;
use Drupal\Core\Url;


/**
 * Class CidocHookImplementations.
 *
 * @package Drupal\cidoc
 */
class CidocHookImplementations {

  public function entity_extra_field_info() {
    $extra = array();

    $bundles = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('cidoc_entity');
    $bundles = CidocEntityBundle::loadMultiple(array_keys($bundles));
    foreach ($bundles as $bundle => $bundle_entity) {
      $extra['cidoc_entity'][$bundle]['form']['class_description'] = array(
        'label' => t('Class description'),
        'weight' => -20,
      );

      foreach (array(CidocProperty::DOMAIN_ENDPOINT => FALSE, CidocProperty::RANGE_ENDPOINT => TRUE) as $source_field => $reverse) {
        /** @var CidocEntityBundle $bundle_entity */
        if ($applicable_properties = $bundle_entity->getAllEditableProperties($reverse)) {
          foreach ($applicable_properties as $property_name => $property_entity) {
            /** @var CidocProperty $property_entity */
            if ($reverse) {
              // If this is a bidirectional property, skip.
              if ($property_entity->bidirectional) {
                continue;
              }
              $label = t('Reverse CIDOC Property: %name', ['%name' => $property_entity->reverse_label]);
            }
            else {
              $label = t('CIDOC Property: %name', ['%name' => $property_entity->label()]);
            }
            $extra['cidoc_entity'][$bundle]['form']['cidoc_properties:' . $source_field . ':' . $property_name] = array(
              'label' => $label,
              'weight' => 20 + intval($reverse),
            );

            $extra['cidoc_entity'][$bundle]['display']['cidoc_properties:' . $source_field . ':' . $property_name] = array(
              'label' => $label,
              'weight' => 20 + intval($reverse),
              'visible' => TRUE,
            );
          }
        }
      }
    }

    return $extra;
  }
}


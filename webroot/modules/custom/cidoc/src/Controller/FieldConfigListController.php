<?php

namespace Drupal\cidoc\Controller;

use Drupal\cidoc\Entity\CidocEntityBundle;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Routing\RouteMatchInterface;
use \Drupal\field_ui\Controller\FieldConfigListController as OriginalFieldConfigListController;

class FieldConfigListController extends OriginalFieldConfigListController {

  public function listing($entity_type_id = NULL, $bundle = NULL, RouteMatchInterface $route_match = NULL) {
    $base = parent::listing($entity_type_id, $bundle, $route_match);
    $rows = [];

    if ($bundle_entity = CidocEntityBundle::load($bundle)) {
      foreach ([
         CidocProperty::DOMAIN_ENDPOINT => FALSE,
         CidocProperty::RANGE_ENDPOINT => TRUE,
       ] as $source_field => $reverse) {
        /** @var CidocEntityBundle $bundle_entity */
        if ($applicable_properties = $bundle_entity->getAllEditableProperties($reverse)) {
          foreach ($applicable_properties as $property_name => $property_entity) {
            /** @var CidocProperty $property_entity */
            if ($reverse) {
              // If this is a bidirectional property, skip.
              if ($property_entity->bidirectional) {
                continue;
              }
              $label = t('Reverse: %name', ['%name' => $property_entity->reverse_label]);
            }
            else {
              $label = t('%name', ['%name' => $property_entity->label()]);
            }
            $rows[] = [
              $property_entity->toLink($label, 'edit-form'),
              $source_field == CidocProperty::DOMAIN_ENDPOINT ? t('Domain') : t('Range'),
            ];
          }
        }
      }
    }

    $base['cidoc_properties'] = [
      '#theme' => 'table',
      '#weight' => 100,
      '#caption' => t('CIDOC properties are not strictly fields, but we list them here for ease of use.'),
      '#header' => [
        t('Property'),
        t('Domain/Range'),
      ],
      '#rows' => $rows,
      '#access' => !empty($rows),
    ];
    return $base;
  }

}

<?php

namespace Drupal\oiko_cidoc;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;


/**
 * Class OikoCidocHookImplementations.
 *
 * @package Drupal\oiko_cidoc
 */
class OikoCidocHookImplementations {

  public function entity_base_field_info(EntityTypeInterface $entity_type) {
    if ($entity_type->id() == 'cidoc_entity') {
      $fields = array();
      $fields['significance'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Primary historical significance'))
        ->setTranslatable(FALSE)
        ->setRequired(FALSE)
        ->setSetting('target_type', 'taxonomy_term')
        ->setSetting('handler', 'default:taxonomy_term')
        ->setSetting('handler_settings', array(
          'target_bundles' => array(
            'event_types' => 'event_types',
          ),
        ))
        ->setDisplayOptions('form', array(
          'type' => 'options_select',
          'weight' => -1,
        ))
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      return $fields;
    }
  }
}

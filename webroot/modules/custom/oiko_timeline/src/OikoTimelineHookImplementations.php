<?php

namespace Drupal\oiko_timeline;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;


/**
 * Class OikoTimelineHookImplementations.
 *
 * @package Drupal\oiko_timeline
 */
class OikoTimelineHookImplementations {

  public function entity_base_field_info(EntityTypeInterface $entity_type) {
    if ($entity_type->id() == 'cidoc_entity') {
      $fields = array();
      $fields['timeline_preselect_option'] = BaseFieldDefinition::create('boolean')
        ->setLabel(t('Comparative timeline selector'))
        ->setTranslatable(FALSE)
        ->setDescription(t('Determine if this entity should be immediately available in the comparative timeline add dropdown without searching.'))
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('form', array(
          'type' => 'boolean_checkbox',
          'weight' => 10,
          'settings' => array(
          ),
        ))
        ->setDefaultValue(FALSE)
        ->setDisplayConfigurable('view', TRUE);

      return $fields;
    }
  }
}


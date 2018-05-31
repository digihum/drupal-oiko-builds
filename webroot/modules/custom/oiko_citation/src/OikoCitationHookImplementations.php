<?php

namespace Drupal\oiko_citation;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;


/**
 * Class OikoCitationHookImplementations.
 *
 * @package Drupal\oiko_citation
 */
class OikoCitationHookImplementations {

  public function entity_base_field_info(EntityTypeInterface $entity_type) {
    if ($entity_type->id() == 'cidoc_entity') {
      $fields = array();
      // Add the citations field.
      $fields['citation'] = BaseFieldDefinition::create('entity_reference_revisions')
        ->setLabel(t('General citations'))
        ->setTranslatable(FALSE)
        ->setRevisionable(TRUE)
        ->setRequired(FALSE)
        ->setSetting('target_type', 'paragraph')
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setSetting('handler', 'default:paragraph')
        ->setSetting('handler_settings', array(
          'target_bundles' => array(
            'book' => 'book',
            'uri' => 'uri',
          ),
          'target_bundles_drag_drop' => array(
            'book' => array(
              'enabled' => TRUE,
              'weight' => -5,
            ),
            'uri' => array(
              'enabled' => TRUE,
              'weight' => -4,
            ),
          ),
        ))
        ->setDisplayOptions('form', array(
          'type' => 'entity_reference_paragraphs',
          'weight' => -1,
          'settings' => array(
            'title' => 'Citation',
            'title_plural' => 'Citations',
            'edit_mode' => 'preview',
            'add_mode' => 'button',
          ),
        ))
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      return $fields;
    }

    if ($entity_type->id() == 'cidoc_reference') {
      $fields = array();
      // Add the citations field.
      $fields['citation'] = BaseFieldDefinition::create('entity_reference_revisions')
        ->setLabel(t('Citations'))
        ->setTranslatable(FALSE)
        ->setRevisionable(TRUE)
        ->setRequired(FALSE)
        ->setSetting('target_type', 'paragraph')
        ->setDescription('')
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setSetting('handler', 'default:paragraph')
        ->setSetting('handler_settings', array(
          'target_bundles' => array(
            'book' => 'book',
            'uri' => 'uri',
          ),
          'target_bundles_drag_drop' => array(
            'book' => array(
              'enabled' => TRUE,
              'weight' => -5,
            ),
            'uri' => array(
              'enabled' => TRUE,
              'weight' => -4,
            ),
          ),
        ))
        ->setDisplayOptions('form', array(
          'type' => 'entity_reference_citations',
          'weight' => 1,
          'settings' => array(
            'title' => 'Citation',
            'title_plural' => 'Citations',
            'edit_mode' => 'preview',
          ),
        ))
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      return $fields;
    }
  }
}

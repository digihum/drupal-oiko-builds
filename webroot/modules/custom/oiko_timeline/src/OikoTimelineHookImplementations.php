<?php

namespace Drupal\oiko_timeline;

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


      $fields['timeline_logo'] = BaseFieldDefinition::create('image')
        ->setLabel(t('Comparative timeline image label'))
        ->setTranslatable(FALSE)
        ->setDescription(t('A logo or image that will be displayed in this entity\'s  comparative timeline lane when this entity is added for comparision.'))
        ->setSetting('file_directory', 'ct-logos/[date:custom:Y]-[date:custom:m]')
        ->setSetting('alt_field', TRUE)
        ->setSetting('alt_field_required', FALSE)
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('form', array(
          'type' => 'image_image',
          'weight' => 10,
          'settings' => array(
          ),
        ))
        ->setDefaultValue(FALSE)
        ->setDisplayConfigurable('view', TRUE);

      return $fields;
    }
  }

  public function extra_field_info() {
    $extra = [];
    foreach (CidocEntityBundle::loadMultiple() as $bundle) {
      $extra['cidoc_entity'][$bundle->id()]['display']['timeline_link'] = array(
        'label' => t('Timeline Comparision link'),
        'weight' => 0,
        'visible' => TRUE,
      );
    }
    return $extra;
  }

  public function entity_view_alter(array &$build, \Drupal\Core\Entity\EntityInterface $entity, \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display) {
    if ($entity->getEntityTypeId() == 'cidoc_entity') {
      // If we'd get any results on the timeline, add a link to it.
      /** @var CidocEntity $entity */
//      if ($display->getComponent('timeline_link') && $entity->hasChildEventEntities()) {
//        $build['timeline_link'] = \Drupal\Core\Link::createFromRoute(t('Start comparing %title with other items on the Comparative Timeline', ['%title' => $entity->label()]), 'oiko_timeline.comparative_timeline_controller_basePage', [], ['query' => ['items' => $entity->id()]])
//          ->toRenderable();
//      }
    }
  }
}


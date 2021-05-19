<?php

namespace Drupal\medmus_share;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for CIDOC entities.
 */
class DeletedRemoteEntityViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['medmus_deleted_remote_entity']['decision']['filter'] = array(
      'id' => 'in_operator',
      'options callback' => '\\Drupal\\medmus_share\\Entity\\DeletedRemoteEntity::getAvailableDecisions',
    );

    $data['medmus_deleted_remote_entity']['entity_type_id']['filter'] = array(
      'id' => 'in_operator',
      'options callback' => '\\Drupal\\medmus_share\\DeletedRemoteEntityViewsData::getAvailableEntityTypeIDs',
    );

    $data['medmus_deleted_remote_entity']['uuid__cidoc_entity']['relationship'] = array(
      'title' => $this->t('CIDOC Entity'),
      'description' => $this->t('A link to the local copy of the deleted remote CIDOC Entity, if available.'),
      'id' => 'standard',
      'base' => 'cidoc_entity',
      'base field' => 'uuid',
      'relationship field' => 'entity_uuid',
      'extra' => [
        [
          'left_field' => 'entity_type_id',
          'value' => 'cidoc_entity',
        ],
      ],
      'label' => $this->t('CIDOC Entity'),
    );

    $data['medmus_deleted_remote_entity']['uuid__cidoc_reference']['relationship'] = array(
      'title' => $this->t('CIDOC Reference'),
      'description' => $this->t('A link to the local copy of the deleted remote CIDOC Reference, if available.'),
      'id' => 'standard',
      'base' => 'cidoc_reference',
      'base field' => 'uuid',
      'relationship field' => 'entity_uuid',
      'label' => $this->t('CIDOC Reference'),
      'extra' => [
        [
          'left_field' => 'entity_type_id',
          'value' => 'cidoc_reference',
        ],
      ],
    );


    return $data;
  }

  public static function getAvailableEntityTypeIDs() {
    $return = [];
    $entity_type_manager = \Drupal::service('entity_type.manager');
    foreach ($entity_type_manager->getDefinitions() as $name => $type) {
      $return[$name] = $type->getLabel();
    }
    // Remove ones not in our dataset.
    $present_in_db = \Drupal::database()
      ->select('medmus_deleted_remote_entity', 'e')
      ->fields('e', array('entity_type_id'))
      ->distinct()
      ->execute()
      ->fetchCol();
    return array_intersect_key($return, array_fill_keys($present_in_db, TRUE));
  }

}

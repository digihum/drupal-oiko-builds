<?php

namespace Drupal\entity_share_client;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for CIDOC entities.
 */
class EntityImportStatusViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

//    $data['entity_import_status']['table']['base'] = array(
//      'field' => 'id',
//      'title' => $this->t('CIDOC entity'),
//      'help' => $this->t('The CIDOC entity ID.'),
//    );

    $data['entity_import_status']['policy']['filter'] = array(
      'id' => 'in_operator',
      'options callback' => '\\Drupal\\entity_share_client\\Entity\\EntityImportStatus::getAvailablePolicies',
    );
    return $data;
  }

}

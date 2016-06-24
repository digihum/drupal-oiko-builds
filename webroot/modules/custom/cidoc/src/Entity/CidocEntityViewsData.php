<?php

namespace Drupal\cidoc\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for CIDOC entities.
 */
class CidocEntityViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['cidoc_entity']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('CIDOC entity'),
      'help' => $this->t('The CIDOC entity ID.'),
    );

    return $data;
  }

}

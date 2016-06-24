<?php

namespace Drupal\cidoc\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for CIDOC references.
 */
class CidocReferenceViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['cidoc_reference']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('CIDOC reference'),
      'help' => $this->t('The CIDOC reference ID.'),
    );

    return $data;
  }

}

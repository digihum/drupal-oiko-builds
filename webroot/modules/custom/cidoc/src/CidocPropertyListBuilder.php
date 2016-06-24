<?php

namespace Drupal\cidoc;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of CIDOC property entities.
 */
class CidocPropertyListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('CIDOC property');
    $header['id'] = $this->t('Machine name');
    return $header + parent::buildHeader();
  }

  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no CIDOC properties yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    return $row + parent::buildRow($entity);
  }

}

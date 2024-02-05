<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of CIDOC entities.
 *
 * @ingroup cidoc
 */
class CidocEntityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['internal_name'] = $this->t('Internal name');
    $header['bundle'] = $this->t('Class');
    return $header + parent::buildHeader();
  }

  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no CIDOC entities yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\cidoc\Entity\CidocEntity */
    $row['name'] = Link::fromTextAndUrl(
      $entity->label(),
      new Url(
        'entity.cidoc_entity.canonical', array(
          'cidoc_entity' => $entity->id(),
        )
      )
    );
    $row['internal_name'] = $entity->getName(FALSE);
    $row['bundle'] = $entity->bundleLabel();
    return $row + parent::buildRow($entity);
  }

}

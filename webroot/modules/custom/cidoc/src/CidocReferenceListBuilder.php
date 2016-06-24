<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of CIDOC references.
 *
 * @ingroup cidoc
 */
class CidocReferenceListBuilder extends EntityListBuilder {
  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['property'] = $this->t('Property');
    $header['id'] = $this->t('Reference ID');
    $header['domain'] = $this->t('From');
    $header['range'] = $this->t('To');
    return $header + parent::buildHeader();
  }

  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no CIDOC entity property references yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\cidoc\Entity\CidocReference */
    $row['property'] = $entity->getPropertyLabel();
    $row['id'] = $this->l(
      $entity->id(),
      new Url(
        'entity.cidoc_reference.canonical', array(
          'cidoc_reference' => $entity->id(),
        )
      )
    );

    /** @var EntityInterface $domain_entity */
    $domain_entity = $entity->domain->entity;
    $row['domain'] = $domain_entity ? $this->l($domain_entity->label(), $domain_entity->toUrl()) : NULL;
    /** @var EntityInterface $range_entity */
    $range_entity = $entity->range->entity;
    $row['range'] = $range_entity ? $this->l($range_entity->label(), $range_entity->toUrl()) : NULL;
    return $row + parent::buildRow($entity);
  }

}

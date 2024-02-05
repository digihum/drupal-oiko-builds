<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of CIDOC references.
 *
 * @ingroup cidoc
 */
class CidocReferenceListBuilder extends EntityListBuilder {

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
    $row['id'] = Link::fromTextAndUrl(
      $entity->id(),
      new Url(
        'entity.cidoc_reference.canonical', array(
          'cidoc_reference' => $entity->id(),
        )
      )
    );

    $row['range'] = $row['domain'] = NULL;
    if ($domain_entity = $entity->domain->entity) {
      /** @var \Drupal\cidoc\CidocEntityInterface $domain_entity */
      $row['domain'] = Link::fromTextAndUrl($domain_entity->getName(), $domain_entity->toUrl());
    }
    /** @var \Drupal\cidoc\CidocEntityInterface $range_entity */
    if ($range_entity = $entity->range->entity) {
      $row['range'] = Link::fromTextAndUrl($range_entity->getName(), $range_entity->toUrl());
    }
    return $row + parent::buildRow($entity);
  }

}

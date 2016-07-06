<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder handler for Cidoc entities.
 *
 * @ingroup cidoc
 */
class CidocEntityViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    /** @var \Drupal\cidoc\CidocEntityInterface[] $entities */
    if (empty($entities)) {
      return;
    }

    parent::buildComponents($build, $entities, $displays, $view_mode);

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('cidoc_reference');

    foreach ($entities as $id => $entity) {
      if ($displays[$entity->bundle()]->getComponent('cidoc_properties')) {
        /** @var \Drupal\cidoc\CidocEntityInterface $entity */
        // Direct properties.
        if ($grouped_references = $entity->getProperties()) {
          foreach ($grouped_references as $property => $references) {
            $build[$id]['cidoc_properties']['ranges'][$property] = $view_builder->viewMultiple($references, 'domain');
          }
        }

        // Reverse properties.
        if ($grouped_references = $entity->getProperties(NULL, TRUE)) {
          $properties = CidocProperty::loadMultiple(array_keys($grouped_references));
          foreach ($grouped_references as $property => $references) {
            if (!$properties[$property]->bidirectional) {
              $build[$id]['cidoc_properties']['domains'][$property] = $view_builder->viewMultiple($references, 'range');
            }
          }
        }

        if (isset($build[$id]['cidoc_properties'])) {
          $build[$id]['cidoc_properties'] += array(
            '#type' => 'item',
            '#title' => t('CIDOC properties'),
          );
        }
      }
    }
  }

}

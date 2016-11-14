<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\views\Views;

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
        if ($grouped_references = $entity->getReferences()) {
          foreach ($grouped_references as $property => $references) {
            $build[$id]['cidoc_properties']['ranges'][$property] = $view_builder->viewMultiple($references, 'domain');
          }
        }

        // Reverse properties.
        if ($grouped_references = $entity->getReferences(NULL, TRUE)) {
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

  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    if ($view_mode == 'full') {
      return $this->viewMultiple(array($entity), $view_mode, $langcode);
    }
    else {
      return parent::view($entity, $view_mode, $langcode);
    }
  }


  public function viewMultiple(array $entities = array(), $view_mode = 'full', $langcode = NULL) {
    if ($view_mode == 'full') {
      $build = [];
      foreach ($entities as $entity) {
        $view_name = 'all_events';
        $display_name = 'embed_1';

        // Check that the view is valid and the display still exists.
        $view = Views::getView($view_name);
        if (!$view || !$view->access($display_name)) {
          drupal_set_message(t('The reference view %view_name cannot be found.', array('%view_name' => $view_name)), 'warning');
          break;
        }
        $build[$entity->id()] = $view->render($display_name);
        if (isset($build[$entity->id()]['#attached'])) {
          $build[$entity->id()]['#attached']['drupalSettings']['oiko_leaflet']['popup'] = [
            'id' => $entity->id(),
            'label' => $entity->label(),
          ];
        }
      }

      return $build;
    }
    else {
      return parent::viewMultiple($entities, $view_mode, $langcode);
    }
  }


}

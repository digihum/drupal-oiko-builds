<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Url;
use Drupal\oiko_timeline\Controller\ComparativeTimelineController;
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
    $view_builder_entity = \Drupal::entityTypeManager()->getViewBuilder('cidoc_entity');

    foreach ($entities as $id => $entity) {
      /** @var \Drupal\cidoc\CidocEntityInterface $entity */
      foreach (array(
                 CidocProperty::DOMAIN_ENDPOINT => FALSE,
                 CidocProperty::RANGE_ENDPOINT => TRUE
               ) as $source_field => $reverse) {
        if ($grouped_references = $entity->getReferences(NULL, $reverse)) {
          foreach ($grouped_references as $property => $references) {
            if ($displays[$entity->bundle()]->getComponent('cidoc_properties:' . $source_field . ':' . $property)) {
              $build[$id]['cidoc_properties:' . $source_field . ':' . $property] = $view_builder->viewMultiple($references, $source_field);
            }
          }
        }
      }

      // Add the all properties field.
      if ($displays[$entity->bundle()]->getComponent('cidoc_properties')) {
        foreach (array(
                   CidocProperty::DOMAIN_ENDPOINT => FALSE,
                   CidocProperty::RANGE_ENDPOINT => TRUE
                 ) as $source_field => $reverse) {
          if ($grouped_references = $entity->getReferences(NULL, $reverse)) {
            foreach ($grouped_references as $property => $references) {
              $build[$id]['cidoc_properties'][$source_field . ':' . $property] = $view_builder->viewMultiple($references, $source_field);
            }
          }
        }
      }

      // Temporal summary
      if ($displays[$entity->bundle()]->getComponent('cidoc_temporal_summary')) {
        if ($spans = $entity->getForwardReferencedEntities(['p4_has_time_span'])) {
          $build[$id]['cidoc_temporal_summary'] = $view_builder_entity->viewMultiple($spans, 'temporal_summary');
        }
      }

      // Add the admin links if requested.
      if ($displays[$entity->bundle()]->getComponent('cidoc_admin_links')) {
        $links = [];
        if (($route = Url::fromRoute('entity.cidoc_entity.edit_preview', ['cidoc_entity' => $entity->id()])) && $route->access()) {
          $links[] = [
            '#type' => 'link',
            '#title' => $this->t('View for editing'),
            '#url' => $route,
          ];
        }
        if (($route = Url::fromRoute('entity.cidoc_entity.edit_form', ['cidoc_entity' => $entity->id()])) && $route->access()) {
          $links[] = [
            '#type' => 'link',
            '#title' => $this->t('Edit'),
            '#url' => $route,
          ];
        }
        if (($route = Url::fromRoute('entity.cidoc_entity.populate_properties', ['cidoc_entity' => $entity->id()])) && $route->access()) {
          $links[] = [
            '#type' => 'link',
            '#title' => $this->t('Populate properties'),
            '#url' => $route,
          ];
        }
        $build[$id]['cidoc_admin_links'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('<span class="fa fa-pencil">&nbsp;&nbsp;</span>Editor links'),
          '#attributes' => [
            'class' => [
              'inline',
            ],
          ],
          '#items' => $links,
          '#theme_wrappers' => [
            'container' => [
              '#attributes' => [
                'class' => ['fancy-titles'],
              ],
            ],
          ],
        ];
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
        $request = \Drupal::request();
        if ($request->get('type', 'map') == 'timeline') {
          $timeline_controller = ComparativeTimelineController::create(\Drupal::getContainer());
          $build[$entity->id()] = $timeline_controller->basePage();
        }
        else {
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
      }

      return $build;
    }
    else {
      return parent::viewMultiple($entities, $view_mode, $langcode);
    }
  }


}

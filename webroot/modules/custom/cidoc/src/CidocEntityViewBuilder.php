<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Url;
use Drupal\oiko_app\Controller\AppPageController;
use Drupal\oiko_timeline\Controller\ComparativeTimelineController;
use Drupal\views\Views;

/**
 * View builder handler for Cidoc entities.
 *
 * @ingroup cidoc
 */
class CidocEntityViewBuilder extends EntityViewBuilder {

  const TRANSCRIPT_NOT_MINE_DISCLAIMER = 'References marked in <em>italics</em> and with an asterisk <em>*</em> are references to entities <strong>not</strong> created as part of this transcript of work. The references themselves however, <em>were</em> created as part of this transcript.';

  const TRANSCRIPT_NOT_MINE_PLAIN = 'This entity was not created by this user, but the reference to it was.';

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

      // Add the all properties (for transcript) field.
      if ($displays[$entity->bundle()]->getComponent('cidoc_properties_transcript')) {
        $build[$id]['cidoc_properties_transcript'] = $this->buildTranscriptPropertyTables($entity);
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

  /**
   * Build a nice set of tables for the student transcript.
   *
   * We loop over all properties on our entity grouping them into a single simple table, if we can.
   * Complicated properties with additional fields will get split out into their own tables.
   *
   * @param \Drupal\cidoc\Entity\CidocEntity $cidoc_entity
   */
  protected function buildTranscriptPropertyTables(CidocEntity $cidoc_entity) {
    $cidoc_reference_view_builder = \Drupal::entityTypeManager()->getViewBuilder('cidoc_reference');

    $build = [];

    $simple_properties = [];
    $complex_properties = [];

    foreach (array(
               CidocProperty::RANGE_ENDPOINT => FALSE,
               CidocProperty::DOMAIN_ENDPOINT => TRUE
             ) as $target_field => $reverse) {
      if ($grouped_references = $cidoc_entity->getReferences(NULL, $reverse)) {
        foreach ($grouped_references as $property => $references) {
          // @TOOD: Ensure that this user made the reference.
          $property_entity = $this->fetchCidocPropertyEntity($property);
          if ($property_entity->hasCidocPropertyProperties()) {
            // This is a complicated property, with property properties.
            foreach ($references as $id => $reference_entity) {
              // Build out the property properties.
              $property_properties = [];
              foreach ($property_entity->listCidocPropertyProperties() as $property_property) {
                if (!isset($complex_properties[$property]['#property_properties'][$property_property])) {
                  $complex_properties[$property]['#property_properties'][$property_property] = $reference_entity->{$property_property}->getFieldDefinition()->getLabel();
                }
                if (!isset($complex_properties[$property]['#property_label'])) {
                  $complex_properties[$property]['#property_label'] = $reverse ? $property_entity->getReverseFriendlyLabel() : $property_entity->getFriendlyLabel();
                }
                $property_properties[$property_property] = $reference_entity->{$property_property}->view(['label' => 'hidden', 'settings' => ['link' => FALSE]]);
              }
              $referenced_entity = $reference_entity->{$target_field}->entity;
              // @TODO: Factor this out into an argument.
              if ($referenced_entity->getOwnerId() != \Drupal::currentUser()->id()) {
                $label = $this->t('<em title="@not_mine_description">@label*</em>', ['@label' => $referenced_entity->label(), '@not_mine_description' => self::TRANSCRIPT_NOT_MINE_PLAIN]);
              }
              else {
                $label = $this->t('@label', ['@label' => $referenced_entity->label()]);
              }
              $complex_properties[$property][] = [
                '#property_properties' => $property_properties,
                'citations' => $reference_entity->citation->view(['label' => 'hidden']),
                'reference' => $label,
              ];
            }
          }
          else {
            foreach ($references as $id => $reference_entity) {
              $referenced_entity = $reference_entity->{$target_field}->entity;
              // @TODO: Factor this out into an argument.
              if ($referenced_entity->getOwnerId() != \Drupal::currentUser()->id()) {
                $label = $this->t('<em title="@not_mine_description">@label*</em>', ['@label' => $referenced_entity->label(), '@not_mine_description' => self::TRANSCRIPT_NOT_MINE_PLAIN]);
              }
              else {
                $label = $this->t('@label', ['@label' => $referenced_entity->label()]);
              }
              $simple_properties[$property][] = [
                'property' => $reverse ? $property_entity->getReverseFriendlyLabel() : $property_entity->getFriendlyLabel(),
                'citations' => $reference_entity->citation->view(['label' => 'hidden']),
                'reference' => $label,
              ];
            }
          }
        }
      }
    }

    if (!empty($simple_properties)) {
      // Build a simple table of properties!
      $build['simple_properties'] = [
        '#theme' => 'table',
        '#rows' => [],
        '#header' => [
          $this->t('Property'),
          $this->t('Reference'),
          $this->t('Citations'),
        ],
      ];

      foreach ($simple_properties as $simple_property_list) {
        foreach ($simple_property_list as $simple_property) {
          $build['simple_properties']['#rows'][] = [
            ['data' => $simple_property['property']],
            ['data' => $simple_property['reference']],
            ['data' => $simple_property['citations']],
          ];
        }
      }
    }

    // And now check and render the more complicated references.
    if (!empty($complex_properties)) {
      foreach ($complex_properties as $property => $complex_property_list) {
        $build['complex_properties'][$property] = [
          '#theme' => 'table',
          '#rows' => [],
          '#header' => [],
          '#caption' => $complex_property_list['#property_label'],
        ];

        foreach ($complex_property_list['#property_properties'] as $property_property) {
          $build['complex_properties'][$property]['#header'][] = $property_property;
        }

        $build['complex_properties'][$property]['#header'][] = $this->t('Reference');
        $build['complex_properties'][$property]['#header'][] = $this->t('Citations');


        // We don't want to iterate over the list of property properties.
        unset($complex_property_list['#property_properties']);
        // We don't want to iterate over the list of property label.
        unset($complex_property_list['#property_label']);

        foreach ($complex_property_list as $complex_property) {
          $row = [];
          foreach ($complex_property['#property_properties'] as $property_property) {
            $row[] = ['data' => $property_property];
          }
          $row[] = ['data' => $complex_property['reference']];
          $row[] = ['data' => $complex_property['citations']];
          $build['complex_properties'][$property]['#rows'][] = $row;

        }
      }
    }


    return $build;
  }

  protected function fetchCidocPropertyEntity($property_name) {
    return CidocProperty::load($property_name);
  }

  protected function fetchFieldName($property_name, $field_name) {
    return $field_name;
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
      // @TODO: this should be implemented elsewhere.
      $build = [];
      foreach ($entities as $entity) {
        $request = \Drupal::request();
        $app_controller = AppPageController::create(\Drupal::getContainer());
        $build[$entity->id()] = $app_controller->app();
        $build[$entity->id()]['#attached']['drupalSettings']['oiko_leaflet']['popup'] = [
          'id' => $entity->id(),
          'label' => $entity->label(),
        ];
      }

      return $build;
    }
    else {
      return parent::viewMultiple($entities, $view_mode, $langcode);
    }
  }


}

<?php

/**
 * @file
 * Contains cidoc.theme.inc.
 *
 * Theme callbacks for CIDOC entities & properties.
 */

use Drupal\Core\Render\Element;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Prepares variables for CIDOC entity templates.
 *
 * Default template: cidoc_entity.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_cidoc_entity(array &$variables) {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

/**
 * Prepares variables for CIDOC reference templates.
 *
 * Default template: cidoc_reference.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_cidoc_reference(array &$variables) {
  /** @var \Drupal\cidoc\Entity\CidocReference $entity */
  $entity = $variables['elements']['#cidoc_reference'];
  if ($variables['elements']['#view_mode'] === 'range') {
    $variables['elements']['domain']['#title'] = $entity->getReverseLabel();
  }
  if ($variables['elements']['#view_mode'] === 'domain') {
    $variables['elements']['range']['#title'] = $entity->getPropertyLabel();
  }

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

/**
 * Prepares variables for a custom entity type creation list templates.
 *
 * Default template: cidoc-reference-content-add-list.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - content: An array of CIDOC properties.
 *
 * @see block_content_add_page()
 */
function template_preprocess_cidoc_reference_content_add_list(&$variables) {
  $variables['properties'] = array();
  $query = \Drupal::request()->query->all();
  foreach ($variables['content'] as $property) {
    $url = new Url(
      'entity.cidoc_reference.add_form',
      array(
        'cidoc_property' => $property->id(),
      ),
      array(
        'query' => $query,
      )
    );
    $variables['properties'][$property->id()] = array(
      'link' => Link::fromTextAndUrl($property->label(), $url),
      'description' => array(
        '#markup' => $property->label(),
      ),
      'title' => $property->label(),
      'localized_options' => array(
        'query' => $query,
      ),
    );
  }
}

/**
 * Prepares variables for the CIDOC property references widget template.
 *
 * Default template: cidoc-properties-references-widget.html.twig.
 */
function template_preprocess_cidoc_properties_references_widget(&$vars) {
  $vars['headers'] = array();
  if ($vars['element']['#headers']) {
    $vars['headers'] = $vars['element']['#headers'];
  }
  $rows = array();
  foreach (Element::children($vars['element']['references']) as $reference_id) {
    $rows[$reference_id] = array();
    foreach (Element::children($vars['element']['references'][$reference_id]['subform'], TRUE) as $subform_key) {
      $rows[$reference_id][$subform_key] = $vars['element']['references'][$reference_id]['subform'][$subform_key];
    }
    foreach (Element::children($vars['element']['references'][$reference_id], TRUE) as $child_key) {
      if ($child_key === 'subform') {
        continue;
      }
      $rows[$reference_id][$child_key] = $vars['element']['references'][$reference_id][$child_key];
    }

    // Sort row by headers.
    $rows[$reference_id] = array_merge($vars['headers'], $rows[$reference_id]);
  }
  $vars['rows'] = $rows;
}

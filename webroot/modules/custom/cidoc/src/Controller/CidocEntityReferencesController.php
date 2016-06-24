<?php

namespace Drupal\cidoc\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Controller\ControllerBase;

/**
 * Class CidocEntityReferencesController.
 *
 * @package Drupal\cidoc\Controller
 */
class CidocEntityReferencesController extends ControllerBase {

  /**
   * Displays the properties on a CIDOC entity that need populating.
   *
   * @param CidocEntityInterface $cidoc_entity
   *   The CIDOC entity.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function propertiesForPopulation(CidocEntityInterface $cidoc_entity) {
    $page = array(
      '#title' => t('The following properties on this %bundle need embellishing:', ['%bundle' => $cidoc_entity->bundleLabel()]),
      '#theme' => 'item_list',
      '#items' => array(),
    );
    $source_id = $cidoc_entity->id();
    $references = $cidoc_entity->getReferencesNeedingPopulating();

    // The $endpoints_data array will be keyed by CIDOC entity IDs, with each
    // mapped to an inner array of reference IDs mapped to property names.
    $endpoints_data = array();
    $properties = array();
    foreach ($references as $id => $reference_endpoints) {
      list($reference_id, $property) = explode(':', $id, 2);
      list($domain, $range) = explode('>', $reference_endpoints, 2);
      // If this reference is from this entity, use its range.
      if ($domain == $source_id) {
        $endpoints_data[$range]['>' . $reference_id] = $property;
      }
      // If this reference is to this entity, use its domain.
      if ($range == $source_id) {
        $endpoints_data[$domain]['<' . $reference_id] = $property;
      }
      $properties[] = $property;
    }
    $endpoint_entities = CidocEntity::loadMultiple(array_keys($endpoints_data));
    /** @var CidocProperty $property */
    foreach (CidocProperty::loadMultiple($properties) as $property_key => $property) {
      $page['#items'][$property_key] = array(
        '#theme' => 'item_list',
        '#title' => $property->label(),
        '#items' => array(),
      );
    }
    foreach ($endpoints_data as $endpoint_id => $entity_references) {
      foreach ($entity_references as $reference_id => $property_key) {
        $reverse = $reference_id[0] === '<';

        /** @var CidocEntityInterface $endpoint_entity */
        $endpoint_entity = $endpoint_entities[$endpoint_id];
        $page['#items'][$property_key]['#items'][$endpoint_id] = array(
          '#prefix' => $endpoint_entity->bundleLabel() . ': ',
          '#type' => 'link',
          '#url' => $endpoint_entity->toUrl('edit-form'),
          '#title' => $endpoint_entity->label(),
        );
        if ($reverse) {
          $page['#items'][$property_key]['#items'][$endpoint_id]['#suffix'] = t(' (reverse reference)');
        }
      }
    }
    return array('wrapper' => $page);
  }

  /**
   * The _title_callback for the entity.cidoc_entity.populate_properties route.
   *
   * @param CidocEntityInterface $cidoc_entity
   *   The CIDOC entity.
   *
   * @return string
   *   The page title.
   */
  public function propertiesForPopulationTitle(CidocEntityInterface $cidoc_entity) {
    return $this->t('Properties of %label needing population', array('%label' => $cidoc_entity->label()));
  }


}

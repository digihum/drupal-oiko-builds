<?php

namespace Drupal\cidoc\Plugin\views\filter;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter class which allows filtering by entity bundles.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cidoc_bundle")
 */
class CIDOCBundle extends Bundle {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      parent::getValueOptions();
      // Append our magical event types option.
      $this->valueOptions['--all-events--'] = $this->t('All CIDOC event types');
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (in_array('--all-events--', $this->value, TRUE)) {
      // Change the '--all-events--' value to be all the CIDOC event types.
      unset($this->value[array_search('--all-events--', $this->value, TRUE)]);
      // Add the additional values in.
      foreach (\Drupal::service('cidoc.entity_type_helper')->getEventTypes() as $type) {
        $this->value[$type] = $type;
      }

    }
    parent::query();
  }

  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $bundle_entity_type = $this->entityType->getBundleEntityType();
    $bundle_entity_storage = $this->entityManager->getStorage($bundle_entity_type);

    foreach (array_keys($this->value) as $bundle) {
      if ($bundle == '--all-events--') {
        foreach (\Drupal::service('cidoc.entity_type_helper')->getEventTypes() as $type) {
          if ($bundle_entity = $bundle_entity_storage->load($type)) {
            $dependencies[$bundle_entity->getConfigDependencyKey()][] = $bundle_entity->getConfigDependencyName();
          }
        }
      }
    }

    return $dependencies;
  }


}

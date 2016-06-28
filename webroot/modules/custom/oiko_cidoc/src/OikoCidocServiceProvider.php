<?php

/**
 * @file
 * Contains Drupal\oiko_cidoc\OikoCidocServiceProvider
 */

namespace Drupal\oiko_cidoc;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class OikoCidocServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides language_manager class to test domain language negotiation.
    $definition = $container->getDefinition('cidoc_spec.entity_factory');
    $arguments = $definition->getArguments();
    $arguments[0] = array();
    // Add the original directory of entities.
    $arguments[0][] = realpath(DRUPAL_ROOT . '/../vendor/computerminds/cidoc-crm/yaml');
    // Include our extra directories of entities.
    $arguments[0][] = realpath(__DIR__ . '/../cidoc_spec/yaml');

    $definition->setArguments($arguments);
  }
}
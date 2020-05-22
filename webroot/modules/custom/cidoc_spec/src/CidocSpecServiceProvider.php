<?php

namespace Drupal\cidoc_spec;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Change the CIDOC entity and property factories.
 */
class CidocSpecServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('cidoc_spec.entity_factory');
    $arguments = $definition->getArguments();
    $arguments[0] = isset($arguments[0]) ? $arguments[0] : array();
    // Add the original directory of entities.
    $arguments[0][] = realpath(DRUPAL_ROOT . '/../vendor/computerminds/cidoc-crm/yaml');

    $definition->setArguments($arguments);

    $definition = $container->getDefinition('cidoc_spec.property_factory');
    $arguments = $definition->getArguments();
    $arguments[0] = isset($arguments[0]) ? $arguments[0] : array();
    // Add the original directory of entities.
    $arguments[0][] = realpath(DRUPAL_ROOT . '/../vendor/computerminds/cidoc-crm/yaml');

    $definition->setArguments($arguments);
  }
}

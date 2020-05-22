<?php

namespace Drupal\medmus_cidoc;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Change the CIDOC entity and property factories to use our folder.
 */
class MedmusCidocServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('cidoc_spec.entity_factory');
    $arguments = $definition->getArguments();
    $arguments[0] = isset($arguments[0]) ? $arguments[0] : array();
    // Include our extra directories of entities.
    $arguments[0][] = realpath(__DIR__ . '/../cidoc_spec/yaml');

    $definition->setArguments($arguments);

    $definition = $container->getDefinition('cidoc_spec.property_factory');
    $arguments = $definition->getArguments();
    $arguments[0] = isset($arguments[0]) ? $arguments[0] : array();
    // Include our extra directories of entities.
    $arguments[0][] = realpath(__DIR__ . '/../cidoc_spec/yaml');

    $definition->setArguments($arguments);
  }
}

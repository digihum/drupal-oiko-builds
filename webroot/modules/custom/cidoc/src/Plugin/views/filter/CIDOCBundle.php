<?php

namespace Drupal\cidoc\Plugin\views\filter;

use Drupal\cidoc\EntityTypeHelperInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\views\Plugin\views\filter\Bundle;
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
   * The cidoc entity type helper service.
   *
   * @var \Drupal\cidoc\EntityTypeHelperInterface
   */
  protected $cidocEntityTypeHelper;

  /**
   * Constructs a Bundle object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   * @param \Drupal\cidoc\EntityTypeHelperInterface $cidoc_helper_service
   *   The cidoc helper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $bundle_info_service, EntityTypeHelperInterface $cidoc_helper_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_manager, $bundle_info_service);
    $this->cidocEntityTypeHelper = $cidoc_helper_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('cidoc.entity_type_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      parent::getValueOptions();
      // Append our magical event types option.
      $this->valueOptions['--all-events--'] = (string) $this->t('All CIDOC event types');
      // And all 'main' types.
      $this->valueOptions['--all-main--'] = (string) $this->t('Main CIDOC event types');
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
      foreach ($this->cidocEntityTypeHelper->getEventTypes() as $type) {
        $this->value[$type] = $type;
      }
    }
    if (in_array('--all-main--', $this->value, TRUE)) {
      // Change the '--all-main--' value to be all the CIDOC event types.
      unset($this->value[array_search('--all-main--', $this->value, TRUE)]);
      // Add the additional values in.
      foreach ($this->cidocEntityTypeHelper->getMainTypes() as $type) {
        $this->value[$type] = $type;
      }

    }
    parent::query();
  }
}

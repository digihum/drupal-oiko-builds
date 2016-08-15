<?php

namespace Drupal\cidoc;

use ComputerMinds\CIDOC_CRM\EntityTraversal;
use ComputerMinds\CIDOC_CRM\EntityFactory;
use Drupal\cidoc_spec\DrupalCidocManager;

/**
 * Class EntityTypeHelper.
 *
 * @package Drupal\cidoc
 */
class EntityTypeHelper implements EntityTypeHelperInterface {

  /**
   * ComputerMinds\CIDOC_CRM\EntityTraversal definition.
   *
   * @var ComputerMinds\CIDOC_CRM\EntityTraversal
   */
  protected $cidoc_spec_entity_traversal;

  /**
   * ComputerMinds\CIDOC_CRM\EntityFactory definition.
   *
   * @var ComputerMinds\CIDOC_CRM\EntityFactory
   */
  protected $cidoc_spec_entity_factory;

  /**
   *
   */
  protected $drupal_manager;

  /**
   * Constructor.
   */
  public function __construct(EntityTraversal $cidoc_spec_entity_traversal, EntityFactory $cidoc_spec_entity_factory, DrupalCidocManager $drupal_manager) {
    $this->cidoc_spec_entity_traversal = $cidoc_spec_entity_traversal;
    $this->cidoc_spec_entity_factory = $cidoc_spec_entity_factory;
    $this->drupal_manager = $drupal_manager;
  }

  /**
   * Return the Drupal bundles that correspond to CIDOC event types.
   */
  public function getEventTypes() {
    // @TODO: cache this.
    $event_entity = $this->cidoc_spec_entity_factory->getEntity('e4_period');
    $all_defined_subclasses = $this->cidoc_spec_entity_traversal->getAllSubclasses($event_entity);
    $all_enabled_subclasses = $this->drupal_manager->filterCRMEntitiesToOnlyEnabled($all_defined_subclasses);
    $all_drupal_bundles = $this->drupal_manager->convertCRMNamesToDrupalIdentifiers($all_enabled_subclasses);
    return $all_drupal_bundles;
  }

}

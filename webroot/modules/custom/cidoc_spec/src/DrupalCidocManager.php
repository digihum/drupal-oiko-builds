<?php

namespace Drupal\cidoc_spec;

use ComputerMinds\CIDOC_CRM\EntityFactory;
use ComputerMinds\CIDOC_CRM\PropertyFactory;
use Drupal\Core\Config\ConfigFactoryInterface;

class DrupalCidocManager  {

  /**
   * The aggregator.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * ComputerMinds\CIDOC_CRM\EntityFactory definition.
   *
   * @var \ComputerMinds\CIDOC_CRM\EntityFactory
   */
  protected $cidoc_spec_entity_factory;

  /**
   * ComputerMinds\CIDOC_CRM\PropertyFactory definition.
   *
   * @var \ComputerMinds\CIDOC_CRM\PropertyFactory
   */
  protected $cidoc_spec_property_factory;

  /**
   * Constructs a CIDOC Manager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(
    EntityFactory $cidoc_spec_entity_factory,
    PropertyFactory $cidoc_spec_property_factory,
    ConfigFactoryInterface $config_factory
  ) {
    $this->cidoc_spec_entity_factory = $cidoc_spec_entity_factory;
    $this->cidoc_spec_property_factory = $cidoc_spec_property_factory;
    $this->config = $config_factory->get('cidoc_spec.settings');
  }

  /**
   * Get an associative array of crm entity names to labels.
   *
   * @param bool $sort
   *   Should the array be sorted in lexicographic order.
   * @return array
   *   An associative array of crm entity names to labels.
   *
   * @throws \ComputerMinds\CIDOC_CRM\FactoryException
   */
  public function getCRMEntityNamesAndLabels($sort = TRUE) {
    $entities = array();

    foreach ($this->cidoc_spec_entity_factory->listEntities() as $entityName) {
      $entities[$entityName] = $this->cidoc_spec_entity_factory->getEntity($entityName)->getLabel();
    }

    if ($sort) {
      ksort($entities, SORT_NATURAL);
    }
  
    return $entities;
  }

  /**
   * Get an associative array of crm property names to labels.
   *
   * @param bool $sort
   *   Should the array be sorted in lexicographic order.
   * @return array
   *   An associative array of crm property names to labels.
   *
   * @throws \ComputerMinds\CIDOC_CRM\FactoryException
   */
  public function getCRMPropertyNamesAndLabels($sort = TRUE) {
    $properties = array();

    foreach ($this->cidoc_spec_property_factory->listProperties() as $propertyName) {
      $properties[$propertyName] = $this->cidoc_spec_property_factory->getProperty($propertyName)->getLabel();
    }

    if ($sort) {
      ksort($properties, SORT_NATURAL);
    }

    return $properties;
  }

  /**
   * Convert a lovely CRM name into a Drupal identifier.
   *
   * We need this because in certain places Drupal likes to have identifiers
   * that have a maximum length of 32 characters.
   *
   * @param string $name
   *  The CRM name to convert.
   *
   * @return string
   *   The converted name.
   */
  public function convertCRMNameToDrupalIdentifier($name) {
    return strtolower(substr($name, 0, 32));
  }

  public function convertCRMNamesToDrupalIdentifiers(array $names) {
    return array_map(array($this, 'convertCRMNameToDrupalIdentifier'), $names);
  }

  protected function getAllCRMNames() {
    return $this->cidoc_spec_entity_factory->listEntities() + $this->cidoc_spec_property_factory->listProperties();
  }

  public function getCRMNameToDrupalIdentifierMappings() {
    $crm_names = $this->getAllCRMNames();

    return array_combine($crm_names, array_map(array($this, 'convertCRMNameToDrupalIdentifier'), $crm_names));
  }

  public function getDrupalIdentifierToCRMNameMappings() {
    return array_reverse($this->getCRMNameToDrupalIdentifierMappings());
  }

  /**
   * Convert a Drupal identifier into a lovely CRM name.
   *
   * We need this because in certain places Drupal likes to have identifiers
   * that have a maximum length of 32 characters.
   *
   * @param string $name
   *  The Drupal identifier to convert.
   *
   * @return string
   *   The converted name.
   */
  public function convertDrupalIdentifierToCRMName($name) {
    $mappings = $this->getDrupalIdentifierToCRMNameMappings();
    return isset($mappings[$name]) ? $mappings[$name] : NULL;
  }

  public function getEnabledCRMEntityNames() {
    $entities = $this->config->get('enabled_entities');
    if (empty($entities)) {
      $entities = $this->cidoc_spec_entity_factory->listEntities();
    }
    return $entities;
  }

  public function getEnabledCRMPropertyNames() {
    $properties = $this->config->get('enabled_properties');
    if (empty($properties)) {
      $properties = $this->cidoc_spec_property_factory->listProperties();
    }
    return $properties;
  }

  public function isCRMPropertyEnabled($crm_property_name) {
    return in_array($crm_property_name, $this->getEnabledCRMPropertyNames(), TRUE);
  }

  public function filterCRMPropertiesToOnlyEnabled($crm_properties) {
    return array_intersect($crm_properties, $this->getEnabledCRMPropertyNames());
  }

  public function filterCRMEntitiesToOnlyEnabled($crm_entities) {
    return array_intersect($crm_entities, $this->getEnabledCRMEntityNames());
  }
}

<?php

namespace Drupal\cidoc\Entity;

use ComputerMinds\CIDOC_CRM\FactoryException;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Defines the CIDOC entity bundle (class) entity.
 *
 * @ConfigEntityType(
 *   id = "cidoc_entity_bundle",
 *   label = @Translation("CIDOC entity class"),
 *   handlers = {
 *     "list_builder" = "Drupal\cidoc\CidocEntityBundleListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cidoc\Form\CidocEntityBundleForm",
 *       "edit" = "Drupal\cidoc\Form\CidocEntityBundleForm",
 *       "delete" = "Drupal\cidoc\Form\CidocEntityBundleDeleteForm"
 *     },
 *     "access" = "Drupal\cidoc\CidocBundleEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\cidoc\CidocEntityBundleHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cidoc_entity_bundle",
 *   admin_permission = "administer cidoc entities",
 *   bundle_of = "cidoc_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/cidoc-entity-classes/{cidoc_entity_bundle}",
 *     "add-form" = "/admin/structure/cidoc-entity-classes/add",
 *     "edit-form" = "/admin/structure/cidoc-entity-classes/{cidoc_entity_bundle}/edit",
 *     "delete-form" = "/admin/structure/cidoc-entity-classes/{cidoc_entity_bundle}/delete",
 *     "collection" = "/admin/structure/cidoc-entity-classes"
 *   },
 *   config_export = {
 *     "crm_entity",
 *     "id",
 *     "label",
 *     "friendly_label",
 *     "weight",
 *     "group",
 *     "description",
 *     "examples",
 *     "geoserializers",
 *     "uuid",
 *   }
 * )
 */
class CidocEntityBundle extends ConfigEntityBundleBase implements RevisionableEntityBundleInterface {
  /**
   * The CIDOC entity bundle (class) ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The CIDOC entity bundle (class) label.
   *
   * @var string
   */
  protected $label;

  /**
   * A human readable for the property.
   *
   * @var string
   */
  public $friendly_label;

  /**
   * The weight of the bundle.
   *
   * @var integer
   */
  protected $weight = 0;

  /**
   * The group the bundle belongs to.
   *
   * @var string
   */
  protected $group = 'auxiliary';

  /**
   * The CIDOC entity bundle (class) description.
   *
   * @var string
   */
  protected $description;

  /**
   * The CIDOC entity bundle (class) examples.
   *
   * @var string
   */
  protected $examples;

  /**
   * The enabled Geoserializers.
   *
   * @var array
   */
  protected $geoserializers = [];

  protected $crm_entity;

  function getCRMEntityName() {
    return $this->crm_entity;
  }

  function getCRMEntity() {
    /** @var \ComputerMinds\CIDOC_CRM\EntityFactory $entity_factory */
    $entity_factory = \Drupal::service('cidoc_spec.entity_factory');
    try {
      return $entity_factory->getEntity($this->crm_entity);
    }
    catch (FactoryException $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFriendlyLabel() {
    return !empty($this->friendly_label) ? $this->friendly_label : $this->label();
  }

  /**
   * @return int
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * @return string
   */
  public function getGroup() {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getExamples() {
    return $this->examples;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoserializers() {
    return array_filter($this->geoserializers);
  }

  public function getGeoserializerOptions() {
    $options = [];
    $plugin_manager = \Drupal::service('plugin.manager.cidoc.geoserializer');

    foreach ($plugin_manager->getDefinitions() as $definition) {
      if (empty($definition['hidden'])) {
        $options[$definition['id']] = t($definition['name']);
      }
    }

    return $options;
  }

  /**
   * Ensure that any endpoint fields configured to autocreate with this bundle
   * are reconfigured, otherwise autocreation gets disabled entirely.
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    // Load up fields configured to use any of these bundles for autocreation.
    $entity_ids = array_keys($entities);
    // Query by filtering on the ID as this is more efficient than filtering
    // on the entity_type property directly.
    $fields = \Drupal::entityQuery('field_config')
      ->condition('id', 'cidoc_reference.', 'STARTS_WITH')
      ->condition('field_name', array(CidocProperty::DOMAIN_ENDPOINT, CidocProperty::RANGE_ENDPOINT))
      ->condition('settings.handler_settings.auto_create_bundle', $entity_ids)
      ->execute();
    /** @var FieldConfig $field */
    foreach (FieldConfig::loadMultiple($fields) as $field) {
      $instance_settings = $field->getSetting('handler_settings');
      // Use the first available target bundle that is not being deleted.
      $instance_settings['auto_create_bundle'] = key(array_diff($instance_settings['target_bundles'], $entity_ids));
      $field->setSetting('handler_settings', $instance_settings);
      $field->save();
    }

    parent::preDelete($storage, $entities);
  }

  /**
   * Gets all applicable editable properties that are valid from/to CIDOC
   * entities of this type/bundle.
   *
   * @param bool $reverse
   *   Optionally, set to TRUE to get applicable properties that would be
   *   references to entities of this class instead of being references from
   *   this CIDOC entity class.
   * @param bool $load_entities
   *   Optionally, set to FALSE to avoid loading the property entities.
   * @return array
   *   Returns an associative array of CIDOC property entity ids, mapped to
   *   their entity objects unless $load_entities was falsey.
   */
  public function getAllEditableProperties($reverse = FALSE, $load_entities = TRUE) {
    $endpoint = $reverse ? 'range' : 'domain';

    $query = \Drupal::entityQuery('cidoc_property')
      ->condition('editability.' . $endpoint, TRUE);
    $group = $query->orConditionGroup()
      ->condition($endpoint . '_bundles.*', '*')
      ->condition($endpoint . '_bundles.*', $this->id());
    $properties = $query->condition($group)
      ->execute();

    if ($load_entities) {
      $properties = CidocProperty::loadMultiple($properties);
    }

    return $properties;
  }

  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    if ($a->getGroup() != $b->getGroup()) {
      return $a->getGroup() == 'main' ? -1 : 1;
    }
    else {
      return parent::sort($a, $b);
    }
  }

  /**
   * Add bundle as an allowed endpoint class to any properties' endpoint fields
   * that should allow any bundles.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if (!$update) {
      $bundle_id = $this->id();
      $property_ids = \Drupal::entityQuery('cidoc_property', 'OR')
        ->condition('domain_bundles.*', '*')
        ->condition('range_bundles.*', '*')
        ->execute();
      /** @var CidocProperty $property */
      foreach (CidocProperty::loadMultiple($property_ids) as $property_id => $property) {
        if (in_array('*', $property->domain_bundles, TRUE)) {
          $field_instance = FieldConfig::loadByName('cidoc_reference', $property_id, 'domain');
          $settings = $field_instance->getSetting('handler_settings');
          $settings['target_bundles'][$bundle_id] = $bundle_id;
          $field_instance->setSetting('handler_settings', $settings);
          $field_instance->save();
        }
        if (in_array('*', $property->range_bundles, TRUE)) {
          $field_instance = FieldConfig::loadByName('cidoc_reference', $property_id, 'range');
          $settings = $field_instance->getSetting('handler_settings');
          $settings['target_bundles'][$bundle_id] = $bundle_id;
          $field_instance->setSetting('handler_settings', $settings);
          $field_instance->save();
        }
      }
    }

    parent::postSave($storage, $update);
  }

  public function shouldCreateNewRevision() {
    return TRUE;
  }

  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!empty($this->description)) {
      $this->description = preg_replace('/\r\n|\r|\n/', "\r\n", trim($this->description));
    }

    if (!empty($this->examples)) {
      $this->examples = preg_replace('/\r\n|\r|\n/', "\r\n", trim($this->examples));
    }
  }


}

<?php

namespace Drupal\cidoc\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Defines the CIDOC property entity.
 *
 * These entities represent the types of CidocReferences one can create.
 *
 * @ConfigEntityType(
 *   id = "cidoc_property",
 *   label = @Translation("CIDOC property"),
 *   handlers = {
 *     "list_builder" = "Drupal\cidoc\CidocPropertyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cidoc\Form\CidocPropertyForm",
 *       "edit" = "Drupal\cidoc\Form\CidocPropertyForm",
 *       "delete" = "Drupal\cidoc\Form\CidocPropertyDeleteForm"
 *     },
 *     "access" = "Drupal\cidoc\CidocEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\cidoc\CidocPropertyHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cidoc_property",
 *   admin_permission = "administer cidoc entities",
 *   bundle_of = "cidoc_reference",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "bidirectional",
 *     "reverse_label",
 *     "friendly_label",
 *     "reverse_friendly_label",
 *     "domain_bundles",
 *     "range_bundles",
 *     "editability",
 *     "timesubwidget",
 *     "widget_description",
 *     "autocomplete_description",
 *     "child_events",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/cidoc-properties/{cidoc_property}",
 *     "add-form" = "/admin/structure/cidoc-properties/add",
 *     "edit-form" = "/admin/structure/cidoc-properties/{cidoc_property}/edit",
 *     "delete-form" = "/admin/structure/cidoc-properties/{cidoc_property}/delete",
 *     "collection" = "/admin/structure/cidoc-properties"
 *   }
 * )
 */
class CidocProperty extends ConfigEntityBundleBase implements RevisionableEntityBundleInterface {

  use StringTranslationTrait;

  const DOMAIN_ENDPOINT = 'domain';
  const RANGE_ENDPOINT = 'range';

  /**
   * The CIDOC property ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The CIDOC property label.
   *
   * @var string
   */
  protected $label;

  /**
   * The reverse CIDOC name of this property.
   *
   * @var string
   */
  public $reverse_label;

  /**
   * A human readable for the property.
   *
   * @var string
   */
  public $friendly_label;

  /**
   * The reverse human-readable name of this property.
   *
   * @var string
   */
  public $reverse_friendly_label;

  /**
   * Whether a property is considered symmetric or not.
   *
   * @var bool
   */
  public $bidirectional = FALSE;

  /**
   * List of entity bundles that can be used as property domains (sources).
   *
   * Bundle key arrays are of the form 'entity:bundle', eg. 'node:article', or
   * 'entity:*' for all bundles of the type.
   *
   * @var array
   */
  public $domain_bundles = array();

  /**
   * List of entity bundles that can be used as property ranges (targets).
   *
   * This is the same format as domain bundles.
   *
   * @var array
   */
  public $range_bundles = array();

  /**
   * Which endpoints the property is editable on directly.
   *
   * @var array
   */
  public $editability = array();

  /**
   * Which endpoints the use a time subwidget.
   *
   * @var array
   */
  public $timesubwidget = array();

  /**
   * Descriptions for the widgets.
   *
   * @var array
   */
  public $widget_description = array();

  /**
   * Descriptions for the autocomplete widgets.
   *
   * @var array
   */
  public $autocomplete_description = array();

  /**
   * Which endpoints lead to events.
   *
   * @var array
   */
  public $child_events = array();

  /**
   * {@inheritdoc}
   */
  public function getFriendlyLabel() {
    return !empty($this->friendly_label) ? $this->friendly_label : $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseFriendlyLabel() {
    return !empty($this->reverse_friendly_label) ? $this->reverse_friendly_label : $this->getReverseLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseLabel() {
    return !empty($this->reverse_label) ? $this->reverse_label : $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Widget settings.
    $entity_form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('cidoc_reference.' . $this->id() . '.default');
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create(array(
        'targetEntityType' => 'cidoc_reference',
        'bundle' => $this->id(),
        'mode' => 'default',
        'status' => TRUE,
      ));
      $entity_form_display->save();
    }

    // Default display settings.
    $default_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('cidoc_reference.' . $this->id() . '.default');
    if (!$default_display) {
      $default_display = EntityViewDisplay::create(array(
        'targetEntityType' => 'cidoc_reference',
        'bundle' => $this->id(),
        'mode' => 'default',
        'status' => TRUE,
      ));
      $default_display->save();
    }

    // Ensure endpoint fields are attached to CIDOC property if necessary.
    $endpoints = array(
      self::DOMAIN_ENDPOINT => $this->t('From'),
      self::RANGE_ENDPOINT => $this->t('To'),
    );
    $opposites = array_combine(array_reverse(array_keys($endpoints)), array_keys($endpoints));
    foreach ($endpoints as $endpoint_field => $endpoint_label) {
      $opposite_field = $opposites[$endpoint_field];

      $field_storage = FieldStorageConfig::loadByName('cidoc_reference', $endpoint_field);
      $field_instance = FieldConfig::loadByName('cidoc_reference', $this->id(), $endpoint_field);

      if (empty($field_storage)) {
        $field_storage = FieldStorageConfig::create(array(
          'field_name' => $endpoint_field,
          'entity_type' => 'cidoc_reference',
          'cardinality' => 1,
          'type' => 'entity_reference',
          'locked' => TRUE,
          'settings' => array(
            'target_type' => 'cidoc_entity',
          ),
        ));
        $field_storage->save();
      }
      if ($field_storage) {
        $instance_settings = array(
          'handler' => 'default:cidoc_entity',
          'handler_settings' => array(
            'auto_create' => TRUE,
            'sort' => array(
              'field' => 'name',
              'direction' => 'ASC',
            ),
          ),
        );
        foreach ($this->getBundles($endpoint_field) as $selected_bundle) {
          if ($selected_bundle === '*') {
            $selected_bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('cidoc_entity');
            foreach (array_keys($selected_bundles) as $target_bundle) {
              $instance_settings['handler_settings']['target_bundles'][$target_bundle] = $target_bundle;
            }
          }
          else {
            $instance_settings['handler_settings']['target_bundles'][$selected_bundle] = $selected_bundle;
          }
        }

        // The EntityAutocomplete form element needs a bundle specified for new
        // entities, though the selection handler can ignore it.
        $instance_settings['handler_settings']['auto_create_bundle'] = reset($instance_settings['handler_settings']['target_bundles']);

        if (empty($field_instance)) {
          // Attach field instance.
          $field_instance = FieldConfig::create(array(
            'field_storage' => $field_storage,
            'bundle' => $this->id(),
            'label' => $endpoint_label,
            'settings' => $instance_settings,
          ));
          $field_instance->save();

          $entity_form_display->setComponent($endpoint_field, array(
            'type' => 'squid_entity_reference_autocomplete_tags',
          ))->save();

          $default_display->setComponent($endpoint_field, array(
            'label' => 'inline',
            'type' => 'entity_reference_label',
          ))->save();

          // Hide the field instance on its own display mode.
          $other_display = \Drupal::entityTypeManager()
            ->getStorage('entity_view_display')
            ->load('cidoc_reference.' . $this->id() . '.' . $opposite_field);
          if (!$other_display) {
            $other_display = EntityViewDisplay::create(array(
              'targetEntityType' => 'cidoc_reference',
              'bundle' => $this->id(),
              'mode' => $opposite_field,
              'status' => TRUE,
            ));
            $other_display->removeComponent('property');
          }
          $other_display->setComponent($endpoint_field, array(
            'label' => 'inline',
            'type' => 'entity_reference_label',
          ))->save();

          // Opposite form widgets settings.
          $other_form_mode = \Drupal::entityTypeManager()
            ->getStorage('entity_form_display')
            ->load('cidoc_reference.' . $this->id() . '.' . $opposite_field);
          if (!$other_form_mode) {
            $other_form_mode = EntityFormDisplay::create(array(
              'targetEntityType' => 'cidoc_reference',
              'bundle' => $this->id(),
              'mode' => $opposite_field,
              'status' => TRUE,
            ));
            $other_form_mode->removeComponent('langcode');
          }
          $other_form_mode->setComponent($endpoint_field, array(
            'type' => 'squid_entity_reference_autocomplete_tags',
            'settings' => array(
              'match_operator' => 'STARTS_WITH',
              'size' => '60',
              'placeholder' => 'Start typing to find a match or create new entity...',
            ),
          ))->save();
        }
        else {
          $field_instance->setSettings($instance_settings);
          $field_instance->save();
        }
      }
    }

    // Clear the cached field definitions as some settings affect the field
    // definitions.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Get the list of allowed bundles, in a specified direction, or both.
   *
   * @return array
   */
  public function getBundles($direction = NULL) {
    $bundles = array();

    if ((!$direction || $direction == self::DOMAIN_ENDPOINT) && is_array($this->domain_bundles)) {
      $bundles += $this->domain_bundles;
    }

    if ((!$direction || $direction == self::RANGE_ENDPOINT) && is_array($this->range_bundles)) {
      $bundles += $this->range_bundles;
    }

    return $bundles;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    $values['editability']['domain'] = TRUE;
    $values['editability']['range'] = TRUE;
    $values['timesubwidget']['domain'] = FALSE;
    $values['timesubwidget']['range'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (empty($this->label)) {
      $this->label = $this->id();
    }

    // If no reverse label is specified, fill it with the label.
    if (empty($this->reverse_label)) {
      $this->reverse_label = $this->label;
    }
  }

  /**
   * Get whether the property is editable at an endpoint.
   */
  public function isEditable($endpoint) {
    $editability = FALSE;
    switch ($endpoint) {
      case 'domain':
      case 'range':
        $editability = !empty($this->editability[$endpoint]);
        break;
    }
    return $editability;
  }

  /**
   * Get whether the property should use a time subwidget.
   */
  public function isTimeSubwidget($endpoint) {
    $usage = FALSE;
    switch ($endpoint) {
      case 'domain':
      case 'range':
        $usage = !empty($this->timesubwidget[$endpoint]);
        break;
    }
    return $usage;
  }

  /**
   * Get a widget description.
   */
  public function getWidgetDescription($endpoint) {
    return isset($this->widget_description[$endpoint]) ? $this->widget_description[$endpoint] : '';
  }

  /**
   * Get an autocomplete widget description
   */
  public function getAutocompleteWidgetDescription($endpoint) {
    return isset($this->autocomplete_description[$endpoint]) ? $this->autocomplete_description[$endpoint] : '';
  }

  /**
   * Get whether the property leads to child event data.
   */
  public function isChildEvents($endpoint) {
    $val = FALSE;
    switch ($endpoint) {
      case 'domain':
      case 'range':
        $val = !empty($this->child_events[$endpoint]);
        break;
    }
    return $val;
  }

  public function shouldCreateNewRevision() {
    return TRUE;
  }

  /**
   * Determine if this property has property fields of its own.
   */
  public function hasCidocPropertyProperties() {
    return !empty($this->listCidocPropertyProperties());

  }

  /**
   * List the property property fields.
   */
  public function listCidocPropertyProperties() {
    $property_properties = [];
    // Fetch the fields for this bundle.
    $entityManager = \Drupal::service('entity_field.manager');
    $fields = $entityManager->getFieldDefinitions('cidoc_reference', $this->id());
    foreach ($fields as $field_name => $field) {
      // @TODO: There *must* be a better way to do this.
      if (strpos($field_name, 'field_') !== FALSE) {
        $property_properties[] = $field_name;
      }
    }
    return $property_properties;
  }


}

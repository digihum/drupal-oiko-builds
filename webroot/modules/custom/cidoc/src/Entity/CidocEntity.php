<?php

namespace Drupal\cidoc\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\UserInterface;

/**
 * Defines the CIDOC entity entity.
 *
 * @ingroup cidoc
 *
 * @ContentEntityType(
 *   id = "cidoc_entity",
 *   label = @Translation("CIDOC entity"),
 *   bundle_label = @Translation("CIDOC entity class"),
 *   handlers = {
 *     "view_builder" = "Drupal\cidoc\CidocEntityViewBuilder",
 *     "list_builder" = "Drupal\cidoc\CidocEntityListBuilder",
 *     "views_data" = "Drupal\cidoc\Entity\CidocEntityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\cidoc\Form\CidocEntityForm",
 *       "add" = "Drupal\cidoc\Form\CidocEntityForm",
 *       "edit" = "Drupal\cidoc\Form\CidocEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\cidoc\CidocEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\cidoc\CidocEntityHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "cidoc_entity",
 *   revision_table = "cidoc_entity_revision",
 *   revision_data_table = "cidoc_entity_field_revision",
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user_id",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer cidoc entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "bundle" = "bundle",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/cidoc-entity/{cidoc_entity}",
 *     "edit_preview" = "/cidoc-entity/{cidoc_entity}/edit-preview",
 *     "add-form" = "/cidoc-entity/add/{cidoc_entity_bundle}",
 *     "edit-form" = "/cidoc-entity/{cidoc_entity}/edit",
 *     "delete-form" = "/cidoc-entity/{cidoc_entity}/delete",
 *     "collection" = "/admin/cidoc/cidoc-entities",
 *   },
 *   bundle_entity_type = "cidoc_entity_bundle",
 *   field_ui_base_route = "entity.cidoc_entity_bundle.edit_form"
 * )
 */
class CidocEntity extends EditorialContentEntityBase implements CidocEntityInterface {

  use EntityChangedTrait;

  /**
   * Geospatial data for this entity.
   *
   * @var array
   */
  protected $geospatial_data = NULL;

  /**
   * Temporal information for this entity.
   *
   * @var array
   */
  protected $temporal_information = NULL;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
      'status' => \Drupal::currentUser()->hasPermission('add cidoc entities as published'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName($fallback = TRUE) {
    $name = $this->get('internal_name')->value;
    if (!$name && $fallback) {
      $name = $this->label();
    }
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function bundleLabel() {
    return $this->bundle->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getFriendlyLabel() {
    return $this->bundle->entity->getFriendlyLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    $uri = parent::toUrl($rel, $options);
    $uri_options = $uri->getOptions();
    $uri_options['attributes']['data-cidoc-id'] = $this->id();
    $uri_options['attributes']['data-cidoc-label'] = $this->label();
    return $uri->setOptions($uri_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the CIDOC entity.'))
      ->setReadOnly(TRUE);
    $fields['bundle'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('The CIDOC entity class.'))
      ->setSetting('target_type', 'cidoc_entity_bundle')
      ->setRequired(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the CIDOC entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the CIDOC entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDescription(t('The name of the CIDOC entity.'))
      ->setSettings(array(
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -4,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['internal_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Internal name'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('An internal name to provide further clarification beyond the normal name.'))
      ->setSettings(array(
        'max_length' => 255,
      ))
      ->setDefaultValue('')
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'string',
        'weight' => -3,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['content'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Content'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('Descriptive text.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode']
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the CIDOC entity.'))
      ->setDisplayOptions('form', array(
        'type' => 'language_select',
        'weight' => 10,
      ))
      ->setDisplayConfigurable('form', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setRevisionable(TRUE)
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['populated'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Content populated'))
      ->setDescription(t('A boolean indicating whether the CIDOC entity has been populated.'))
      ->setDefaultValue(FALSE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    // Find and delete reference entities that use this as a domain or range.
    /** @var CidocEntity $entity */
    foreach ($entities as $entity) {
      foreach ($entity->getReferences(NULL, FALSE) as $references) {
        foreach ($references as $reference) {
          /** @var CidocReference $reference */
          $reference->delete();
        }
      }
      foreach ($entity->getReferences(NULL, TRUE) as $references) {
        foreach ($references as $reference) {
          /** @var CidocReference $reference */
          $reference->delete();
        }
      }
    }
    parent::postDelete($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferences($property_name = NULL, $reverse = FALSE, $load_entities = TRUE) {
    $endpoint = $reverse ? 'range' : 'domain';

    $grouped = array();

    // Only continue if the field exists to query.
    if (FieldStorageConfig::loadByName('cidoc_reference', $endpoint)) {

      $query = \Drupal::entityQuery('cidoc_reference')
        ->condition($endpoint, $this->id());
      if ($property_name) {
        $query->condition('property', $property_name);
      }
      $query->addTag('cidoc_entity_get_properties');
      $references = $query->execute();

      if ($load_entities) {
        $references = CidocReference::loadMultiple(array_keys($references));
      }

      foreach ($references as $key => $value) {
        if (is_object($value)) {
          $grouped[$value->bundle()][$key] = $value;
        }
        else {
          $grouped[$value][$key] = $key;
        }
      }
    }

    return $grouped;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencesNeedingPopulating() {
    $source_id = $this->id();

    $query = \Drupal::entityQuery('cidoc_reference');
    $or_group = $query->orConditionGroup();
    $domain_and_group = $query->andConditionGroup()
      ->condition('domain', $source_id)
      ->condition('range.entity.populated', 0);
    $range_and_group = $query->andConditionGroup()
      ->condition('range', $source_id)
      ->condition('domain.entity.populated', 0);
    $or_group->condition($domain_and_group)
      ->condition($range_and_group);
    $query->condition($or_group)
      ->addTag('cidoc_get_properties_for_population');
    return $query->execute();
  }

  protected $stubReferences = [];

  /**
   * Add a stub reference that will get created on save.
   *
   * @param $property
   *
   * @param $referenced_entity
   *   The other entity at the end of the reference.
   * @param bool $i_am_domain
   *   Is this entity the domain of the reference, or the range.
   */
  public function addStubReference(CidocProperty $property, CidocEntityInterface $referenced_entity, $i_am_domain = TRUE) {
    $this->stubReferences[] = array(
      'property' => $property,
      'referenced_entity' => $referenced_entity,
      'i_am_domain' => $i_am_domain,
    );
  }

  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if (!empty($this->stubReferences)) {
      // We need to create reference entities for these stubs.
      foreach ($this->stubReferences as $k => $reference) {
        $property = $reference['property'];
        $referenced_entity = $reference['referenced_entity'];
        $i_am_domain = $reference['i_am_domain'];
        $values = array(
          'property' => $property->get('id'),
          'user_id' => $this->getOwnerId(),
          'langcode' => $this->get('langcode'),
        );
        $reference_entity = \Drupal::entityTypeManager()->getStorage('cidoc_reference')->create($values);
        if ($i_am_domain) {
          $reference_entity->set('domain', $this);
          $reference_entity->set('range', $referenced_entity);
        }
        else {
          $reference_entity->set('domain', $referenced_entity);
          $reference_entity->set('range', $this);
        }
        // Unset now, so we won't end up in a loop in the save.
        unset($this->stubReferences[$k]);
        $reference_entity->save();


      }
    }
    parent::postSave($storage, $update);
  }

  /**
   * Get reverse referenced entities from us.
   *
   * @param array $properties
   * @param boolean $loaded
   *
   * @deprecated
   * @return array
   */
  public function getReverseReferences($properties = [], $loaded = TRUE) {
    return $this->getReverseReferencedEntities($properties, $loaded);
  }

  /**
   * Get reverse referenced entities from us.
   *
   * @param array $properties
   * @param boolean $loaded
   *
   * @deprecated
   * @return array
   */
  public function getForwardReferences($properties = [], $loaded = TRUE) {
    return $this->getForwardReferencedEntities($properties, $loaded);
  }

  /**
   * Get reverse referenced entities from us.
   *
   * @TODO: Refactor this out into \Drupal\cidoc\GraphTraversal.
   *
   * @param array $properties
   * @param boolean $loaded
   *
   * @return array
   */
  public function getReverseReferencedEntities($properties = [], $loaded = TRUE) {
    $query = \Drupal::entityQuery('cidoc_reference');
    $query->condition('range', $this->id());
    if (!empty($properties)) {
      $query->condition('property', $properties, 'IN');
    }
    $query->addTag('cidoc_get_reverse_references_properties');
    $references = $query->execute();

    $entities = [];
    if (!empty($references)) {
      foreach (\Drupal::entityTypeManager()->getStorage('cidoc_reference')->loadMultiple($references) as $reference) {
        /** @var CidocReference $domain */
        $domain_entity_id = $reference->getDomain();
        if ($loaded) {
          $e = \Drupal::entityTypeManager()->getStorage('cidoc_entity')->load($domain_entity_id);
          $entities[$domain_entity_id] = $e;
        }
        else {
          $entities[$domain_entity_id] = $domain;
        }

      }
    }
    return array_filter($entities);
  }

  /**
   * Get forward referenced entities.
   *
   * @TODO: Refactor this out into \Drupal\cidoc\GraphTraversal.
   *
   * @param array $properties
   * @param boolean $loaded
   *
   * @return array
   */
  public function getForwardReferencedEntities($properties = [], $loaded = TRUE) {
    $query = \Drupal::entityQuery('cidoc_reference');
    $query->condition('domain', $this->id());
    if (!empty($properties)) {
      $query->condition('property', $properties, 'IN');
    }
    $query->addTag('cidoc_get_domain_references_properties');
    $references = $query->execute();

    $entities = [];
    if (!empty($references)) {
      foreach (\Drupal::entityTypeManager()->getStorage('cidoc_reference')->loadMultiple($references) as $reference) {
        /** @var CidocReference $domain */
        $range_entity_id = $reference->getRange();
        if ($loaded) {
          $e = \Drupal::entityTypeManager()->getStorage('cidoc_entity')->load($range_entity_id);
          $entities[$range_entity_id] = $e;
        }
        else {
          $entities[$range_entity_id] = $domain;
        }

      }
    }
    return array_filter($entities);
  }

  /**
   * Return the temporal information for this entity if we can.
   *
   * @return array
   *   The array of temporal information.
   */
  public function getTemporalInformation() {
    if (!isset($this->temporal_information)) {
      $this->temporal_information = [];
      $time_spans = $this->getForwardReferences(['p4_has_time_span'], TRUE);
      foreach ($time_spans as $time_span) {
        $date_value = $time_span->field_date->getValue();
        if (!empty($date_value[0]['value'])) {
          $this->temporal_information = [
            'machine' => $date_value[0]['value'],
            'human' => $date_value[0]['human_value'],
            'minmin' => $date_value[0]['minmin'],
            'maxmax' => $date_value[0]['maxmax'],
          ];
          $this->addCacheableDependency($time_span);
          break;
        }
      }
    }

    return $this->temporal_information;
  }

  /**
   * Return an array of geospatial data for this entity.
   */
  public function getGeospatialData() {
    if (!isset($this->geospatial_data)) {
      $data = [];
      // Get the geoserializer plugins for my bundle.
      $activeSerializers = $this->bundle->entity->getGeoserializers();
      $plugin_manager = \Drupal::service('plugin.manager.cidoc.geoserializer');
      foreach ($activeSerializers as $activeSerializer) {
        $data = array_merge($data, $plugin_manager->createInstance($activeSerializer)->getGeospatialData($this));
      }

      $this->geospatial_data = $data;
    }

    return $this->geospatial_data;
  }

  public function hasGeospatialData() {
    $data = $this->getGeospatialData();
    return !empty($data);
  }

  public function hasChildEventEntities() {
    $children = $this->getChildEventEntities(TRUE);
    return !empty($children);
  }

  public function getChildEventEntities($return_on_first_match = FALSE) {
    // Set up our arrays for traversing the graph.
    $entities_to_walk = [];
    $entities_walked = [];

    // This is our return value.
    $all_children = [];

    // Start by traversing myself.
    $entities_to_walk[$this->id()] = $this;

    while (!empty($entities_to_walk)) {
      $current_node = array_shift($entities_to_walk);
      $entities_walked[$current_node->id()] = TRUE;

      // Process the forward references.
      /** @var \Drupal\cidoc\Entity\CidocEntityBundle $bundle */
      $bundle = $current_node->bundle->entity;
      $forward_fields = [];
      if ($forward_properties = $bundle->getAllEditableProperties(FALSE)) {
        /** @var \Drupal\cidoc\Entity\CidocProperty $forward_property */
        foreach ($forward_properties as $forward_property) {
          // If the range of this reference can lead us to event data, we're interested.
          if ($forward_property->isChildEvents('range')) {
            $forward_fields[] = $forward_property->id();
          }
        }
      }
      if (!empty($forward_fields)) {
        $children = $current_node->getForwardReferencedEntities($forward_fields, TRUE);
        /** @var \Drupal\cidoc\CidocEntityInterface $child */
        foreach ($children as $child) {
          if (!isset($entities_walked[$child->id()]) && !isset($entities_to_walk[$child->id()])) {
            $all_children[$child->id()] = $child;
            $entities_to_walk[$child->id()] = $child;
            // An early exit if requested.
            if ($return_on_first_match) {
              break 2;
            }
          }
        }
      }

      $reverse_fields = [];
      if ($reverse_properties = $bundle->getAllEditableProperties(TRUE)) {
        /** @var \Drupal\cidoc\Entity\CidocProperty $reverse_property */
        foreach ($reverse_properties as $reverse_property) {
          // If the domain of this reference can lead us to event data, we're interested.
          if ($reverse_property->isChildEvents('domain')) {
            $reverse_fields[] = $reverse_property->id();
          }
        }
      }

      if (!empty($reverse_fields)) {
        $children = $current_node->getReverseReferencedEntities($reverse_fields, TRUE);
        /** @var \Drupal\cidoc\CidocEntityInterface $child */
        foreach ($children as $child) {
          if (!isset($entities_walked[$child->id()]) && !isset($entities_to_walk[$child->id()])) {
            $all_children[$child->id()] = $child;
            $entities_to_walk[$child->id()] = $child;
            // An early exit if requested.
            if ($return_on_first_match) {
              break 2;
            }
          }
        }
      }
    }

    return $all_children;
  }
}

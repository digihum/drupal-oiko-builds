<?php

namespace Drupal\cidoc\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
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
 *   admin_permission = "administer cidoc entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/cidoc-entity/{cidoc_entity}",
 *     "add-form" = "/cidoc-entity/add/{cidoc_entity_bundle}",
 *     "edit-form" = "/cidoc-entity/{cidoc_entity}/edit",
 *     "delete-form" = "/cidoc-entity/{cidoc_entity}/delete",
 *     "collection" = "/admin/cidoc/cidoc-entities",
 *   },
 *   bundle_entity_type = "cidoc_entity_bundle",
 *   field_ui_base_route = "entity.cidoc_entity_bundle.edit_form"
 * )
 */
class CidocEntity extends ContentEntityBase implements CidocEntityInterface {
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
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
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? NODE_PUBLISHED : NODE_NOT_PUBLISHED);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
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
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setTranslatable(TRUE)
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
      ->setTranslatable(TRUE)
      ->setDescription(t('Descriptive text.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the CIDOC entity is published.'))
      ->setDefaultValue(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
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
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['populated'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Content populated'))
      ->setDescription(t('A boolean indicating whether the CIDOC entity has been populated.'))
      ->setDefaultValue(FALSE);

    // @TODO: This should not really be part of the CIDOC module.
    // Add a field for types of entity.
    $fields['significance'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Primary historical significance'))
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', array(
        'target_bundles' => array(
          'event_types' => 'event_types',
        ),
      ))
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => -1,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // @TODO: This should not really be part of the CIDOC module.
    // Add the citations field.
    $fields['citation'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('General citations'))
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setSetting('target_type', 'paragraph')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', array(
        'target_bundles' => array(
          'book' => 'book',
          'uri' => 'uri',
        ),
        'target_bundles_drag_drop' => array(
          'book' => array(
            'enabled' => TRUE,
            'weight' => -5,
          ),
          'uri' => array(
            'enabled' => TRUE,
            'weight' => -4,
          ),
        ),
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_paragraphs',
        'weight' => -1,
        'settings' => array(
          'title' => 'Citation',
          'title_plural' => 'Citations',
          'edit_mode' => 'preview',
          'add_mode' => 'button',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    // Find and delete reference entities that use this as a domain or range.
    /** @var CidocEntity $entity */
    foreach ($entities as $entity) {
      foreach ($entity->getProperties(NULL, FALSE) as $references) {
        foreach ($references as $reference) {
          /** @var CidocReference $reference */
          $reference->delete();
        }
      }
      foreach ($entity->getProperties(NULL, TRUE) as $references) {
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
  public function getProperties($property_name = NULL, $reverse = FALSE, $load_entities = TRUE) {
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
   * @return array
   */
  public function getReverseReferences($properties = [], $loaded = TRUE) {
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
   * Get reverse referenced entities from us.
   *
   * @param array $properties
   * @param boolean $loaded
   *
   * @return array
   */
  public function getForwardReferences($properties = [], $loaded = TRUE) {
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
    $time_spans = $this->getForwardReferences(['p4_has_time_span'], TRUE);
    foreach ($time_spans as $time_span) {
      $date_value = $time_span->field_date->getValue();
      if (!empty($date_value[0]['value'])) {
        return [
          'machine' => $date_value[0]['value'],
          'human' => $date_value[0]['human_value'],
          'minmin' => $date_value[0]['minmin'],
          'maxmax' => $date_value[0]['maxmax'],
        ];
      }
    }
    return [];
  }

  /**
   * Return an array of geospatial data for this entity.
   */
  public function getGeospatialData() {
    $data = [];
    // Get the geoserializer plugins for my bundle.
    $activeSerializers = $this->bundle->entity->getGeoserializers();
    $plugin_manager = \Drupal::service('plugin.manager.cidoc.geoserializer');
    foreach ($activeSerializers as $activeSerializer) {
      $data = array_merge($data, $plugin_manager->createInstance($activeSerializer)->getGeospatialData($this));
    }

    return $data;
  }
}

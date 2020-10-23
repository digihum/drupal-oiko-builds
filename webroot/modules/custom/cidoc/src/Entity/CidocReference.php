<?php

namespace Drupal\cidoc\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\cidoc\CidocReferenceInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Defines the CIDOC reference entity.
 *
 * These entities represent the actual relationships between CidocEntities.
 * They are entities themselves so we can store data about the relationship
 * on them. This is so that we can implement CIDOC-CRM subproperties.
 *
 * @ingroup cidoc
 *
 * @ContentEntityType(
 *   id = "cidoc_reference",
 *   label = @Translation("CIDOC reference"),
 *   bundle_label = @Translation("CIDOC property"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\cidoc\CidocReferenceListBuilder",
 *     "views_data" = "Drupal\cidoc\Entity\CidocReferenceViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\cidoc\Form\CidocReferenceForm",
 *       "add" = "Drupal\cidoc\Form\CidocReferenceForm",
 *       "edit" = "Drupal\cidoc\Form\CidocReferenceForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "domain" = "Drupal\cidoc\Form\CidocReferenceForm",
 *       "range" = "Drupal\cidoc\Form\CidocReferenceForm"
 *     },
 *     "access" = "Drupal\cidoc\CidocEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\cidoc\CidocReferenceHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "cidoc_reference",
 *   revision_table = "cidoc_reference_revision",
 *   revision_data_table = "cidoc_reference_field_revision",
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user_id",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   admin_permission = "administer cidoc entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "bundle" = "property",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/cidoc-reference/{cidoc_reference}",
 *     "add-form" = "/cidoc-reference/add/{cidoc_property}",
 *     "edit-form" = "/cidoc-reference/{cidoc_reference}/edit",
 *     "delete-form" = "/cidoc-reference/{cidoc_reference}/delete",
 *     "collection" = "/admin/cidoc/cidoc-references",
 *   },
 *   bundle_entity_type = "cidoc_property",
 *   field_ui_base_route = "entity.cidoc_property.edit_form"
 * )
 */
class CidocReference extends EditorialContentEntityBase implements CidocReferenceInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * Whether the reference is considered reverseable or not.
   *
   * @var bool
   */
  public $reverseable = NULL;

  /**
   * {@inheritdoc}
   */
  public function label($langcode = NULL) {
    return $this->t('@bundle (id: @id)', array(
      '@id' => $this->id(),
      '@bundle' => $this->getPropertyLabel(),
    ));
  }

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
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Maintain reverse references.
    if ($this->getReverseable() && \Drupal::state()->get('cidoc.maintain_reverse_relationships', 1)) {
      $fields = $update ? $this->original->getFields(FALSE) : $this->getFields(FALSE);

      // Remove fields that need to be different, or are just correct to be
      // different, between the forward & reverse references.
      $skip = array_flip(array(
        'id',
        'uuid',
        'changed',
        'created',
        'property',
        'user_id',
        'revision_id',
        'revision_user_id',
        'revision_created',
        'revision_log_message',
      ));
      $fields = array_diff_key($fields, $skip);

      foreach ($fields as $field_name => $field) {
        /** @var \Drupal\Core\Field\FieldItemListInterface $field */
        $fields[$field_name] = $field->getValue();
      }
      // Bundle value needs to be the direct scalar, not a nested field value.
      $fields['property'] = $this->bundle();

      // Swap domain and range.
      $domain = $fields['domain'];
      $fields['domain'] = $fields['range'];
      $fields['range'] = $domain;

      $reverse_entity = NULL;
      if ($update) {
        // Load the original reverse reference and update it.
        $query = \Drupal::entityQuery($this->getEntityTypeId());
        // Don't query on some fields that are okay to be different.
        // Querying for the original unchanged entity's field values.
        foreach ($fields as $field_name => $values) {
          if (is_array($values)) {
            foreach ($values as $delta => $value) {
              if (is_array($value)) {
                foreach ($value as $column => $col_value) {
                  $query->condition($field_name . '.' . $delta . '.' . $column, $col_value);
                }
              }
              else {
                $query->condition($field_name . '.' . $delta, $value);
              }
            }
          }
          else {
            $query->condition($field_name, $values);
          }
        }

        // Skip fields that are correct to be different between the references.
        // We do not want to change any entity's language, so skip that too.
        $updated_fields = array_diff_key($this->getFields(FALSE), $skip, array('langcode' => 'langcode'));

        foreach ($query->execute() as $match) {
          if ($reverse_entity = static::load($match)) {
            // Update reverse entity field values.
            foreach ($updated_fields as $field_name => $field) {
              $reverse_entity->set($field_name, $field->getValue());
            }

            // Swap domain and range.
            $domain = $reverse_entity->get('domain')->getValue();
            $reverse_entity->set('domain', $reverse_entity->get('range')->getValue());
            $reverse_entity->set('range', $domain);
            break;
          }
        }
      }

      if (!$reverse_entity) {
        // Create a reverse reference.
        $reverse_entity = static::create($fields);
      }

      $reverse_entity->setReverseable(FALSE);
      $reverse_entity->save();
    }
  }

  public function getReverseable() {
    if (!isset($this->reverseable)) {
      $this->reverseable = $this->property->entity->bidirectional;
    }

    return $this->reverseable;
  }

  public function setReverseable($reverseable) {
    $this->reverseable = $reverseable;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperty() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyLabel() {
    return $this->property->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseLabel() {
    return $this->property->entity->reverse_label;
  }

  /**
   * {@inheritdoc}
   */
  public function getFriendlyLabel() {
    return $this->property->entity->getFriendlyLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseFriendlyLabel() {
    return $this->property->entity->getReverseFriendlyLabel();
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
      ->setDescription(t('The ID of the CIDOC reference.'))
      ->setReadOnly(TRUE);
    $fields['property'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Property'))
      ->setDescription(t('The CIDOC entity property.'))
      ->setSetting('target_type', 'cidoc_property')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -4,
        'settings' => array(
          'link' => FALSE,
        ),
      ))
      ->setDisplayConfigurable('view', TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the CIDOC reference.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the CIDOC reference.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode']
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code for the CIDOC reference entity.'))
      ->setDisplayOptions('form', array(
        'type' => 'language_select',
        'weight' => 10,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setRevisionable(TRUE)
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Get the first entity in the domain of this reference.
   *
   * @deprecated
   * @return mixed
   */
  public function getDomain() {
    // @TODO: fairly certain this is wrong/could be done better.
    return $this->get('domain')->getValue()[0]['target_id'];
  }

  /**
   * Get the first entity in the range of this reference.
   *
   * @deprecated
   * @return mixed
   */
  public function getRange() {
    // @TODO: fairly certain this is wrong/could be done better.
    return $this->get('range')->getValue()[0]['target_id'];
  }

}

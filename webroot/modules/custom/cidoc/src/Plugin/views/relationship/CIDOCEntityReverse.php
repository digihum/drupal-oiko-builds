<?php

namespace Drupal\cidoc\Plugin\views\relationship;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A relationship handlers which reverse entity references.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("cidoc_related_entity_reverse")
 */
class CIDOCEntityReverse extends RelationshipPluginBase {

  /**
   * Constructs an EntityReverse object.
   *
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_manager
   *   The views plugin join manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager, EntityTypeBundleInfoInterface $entityBundleInfo, EntityStorageInterface $cidocReferenceStorage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
    $this->entityBundleInfo = $entityBundleInfo;
    $this->cidocReferenceStorage = $cidocReferenceStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')->getStorage('cidoc_property')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['limit_bundles'] = array('default' => array());
    $options['limit_types'] = array('default' => array());

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $bundle_options = [];
    foreach ($this->entityBundleInfo->getBundleInfo('cidoc_reference') as $bundle_key => $bundle_info) {
      $bundle_entity = $this->cidocReferenceStorage->load($bundle_key);
      $bundle_options[$bundle_key] = $bundle_entity->get('reverse_label');
    }
    natsort($bundle_options);

    $form['limit_bundles'] = array(
      '#type' => 'checkboxes',
      '#options' => $bundle_options,
      '#title' => $this->t('Restrict to specific properties'),
      '#description' => $this->t('Optionally restrict the references added in by the properties that reference the entities.'),
      '#default_value' => $this->options['limit_bundles'],
    );

    $type_options = [];
    foreach ($this->entityBundleInfo->getBundleInfo('cidoc_entity') as $bundle_key => $bundle_info) {
      $type_options[$bundle_key] = $bundle_info['label'];
    }

    natsort($type_options);

    $form['limit_types'] = array(
      '#type' => 'checkboxes',
      '#options' => $type_options,
      '#title' => $this->t('Restrict to specific entity types'),
      '#description' => $this->t('Optionally restrict the entities added in by the types of entites referenced.'),
      '#default_value' => $this->options['limit_types'],
    );

  }

  /**
   * Called to implement a relationship in a query.
   */
  public function query() {
    $this->ensureMyTable();

    // Here's what we're trying to do:
    // CIDOC entities -> Reference Domain -> Reference Range -> CIDOC entities

    $first = array(
      'left_table' => $this->tableAlias,
      'left_field' => 'id',
      'table' => 'cidoc_reference__range',
      'field' => 'range_target_id',
      'adjusted' => TRUE
    );
    if (!empty($this->options['required'])) {
      $first['type'] = 'INNER';
    }

    if (!empty($this->definition['join_extra'])) {
      $first['extra'] = $this->definition['join_extra'];
    }

    if (!empty(array_filter($this->options['limit_bundles']))) {
      // Add a limit on the bundles that can be referenced.
      $first['extra'][] = array(
        'field' => 'bundle',
        'value' => array_filter($this->options['limit_bundles']),
      );

    }

    if (!empty($def['join_id'])) {
      $id = $def['join_id'];
    }
    else {
      $id = 'standard';
    }
    $first_join = $this->joinManager->createInstance($id, $first);


    $this->first_alias = $this->query->addTable('cidoc_reference__range', $this->relationship, $first_join);

    // Second, relate the field table to the entity specified using
    // the entity id on the field table and the entity's id field.
    $second = array(
      'left_table' => $this->first_alias,
      'left_field' => 'entity_id',
      'table' => 'cidoc_reference__domain',
      'field' => 'entity_id',
      'adjusted' => TRUE
    );

    if (!empty($this->options['required'])) {
      $second['type'] = 'INNER';
    }

    if (!empty($def['join_id'])) {
      $id = $def['join_id'];
    }
    else {
      $id = 'standard';
    }
    $second_join = $this->joinManager->createInstance($id, $second);
    $second_join->adjusted = TRUE;

    // use a short alias for this:
    $alias = $this->first_alias . '__domain';

    $this->second_alias = $this->query->addRelationship($alias, $second_join, 'cidoc_reference__domain', $this->relationship);

    // Finally add our target table.

    $third = array(
      'left_table' => $this->second_alias,
      'left_field' => 'domain_target_id',
      'table' => $this->definition['base'],
      'field' => $this->definition['base field'],
      'adjusted' => TRUE
    );

    if (!empty($this->options['required'])) {
      $third['type'] = 'INNER';
    }

    if (!empty(array_filter($this->options['limit_types']))) {
      // Add a limit on the bundles that can be referenced.
      $third['extra'][] = array(
        'field' => 'bundle',
        'value' => array_filter($this->options['limit_types']),
      );

    }

    if (!empty($def['join_id'])) {
      $id = $def['join_id'];
    }
    else {
      $id = 'standard';
    }
    $third_join = $this->joinManager->createInstance($id, $third);
    $third_join->adjusted = TRUE;

    // use a short alias for this:
    $alias = 'referenced_cidoc_entity';

    $this->third_alias = $this->query->addRelationship($alias, $third_join, $this->definition['base'], $this->relationship);

    $this->alias = $this->third_alias;
  }

}

<?php

namespace Drupal\medmus_share\Plugin\EntityShareClient\Processor;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import entities as unpublished by default.
 *
 * @ImportProcessor(
 *   id = "medmus_share_unpublished_by_default",
 *   label = @Translation("Unpublished by default"),
 *   description = @Translation("Will import new entities as unpublished and not sync publication status of those entities."),
 *   stages = {
 *     "process_entity" = 0,
 *     "prepare_importable_entity_data" = 0,
 *   },
 * )
 */
class UnpublishedByDefault extends ImportProcessorPluginBase implements PluginFormInterface {
  /**
   * The Entity import state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stateInformation = $container->get('entity_share_client.state_information');
    $instance->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    $entity_type_manager = $container->get('entity_type.manager');
    $instance->entityDefinitions = array_filter($entity_type_manager->getDefinitions(), function ($entity_type_definition) {
      return $entity_type_definition->entityClassImplements('Drupal\Core\Entity\ContentEntityInterface');
    });
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'entity_type_ids' => [],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['entity_type_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Apply to selected entity types'),
      '#description' => $this->t(''),
      '#default_value' => $this->getConfiguredApplicableEntityTypes(),
      '#options' => array_map(function ($entity_type_definition) {
        return $entity_type_definition->getLabel();
      }, $this->entityDefinitions),
    ];

    return $form;
  }

  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    $import_status_entity = $this->stateInformation->getImportStatusOfEntity($processed_entity);
    if (empty($import_status_entity) && ($entity_type_id = $this->parseEntityTypeId($entity_json_data['type'])) && $this->isEntityTypeIdConfiguredAndApplicable($entity_type_id)) {
      // Totally new entity, lets mark it as unpublished.
      $processed_entity->set($this->getEntityStatusKey($entity_type_id), FALSE);
    }
  }

  /**
   * Get the entity types we are configured for.
   */
  protected function getConfiguredApplicableEntityTypes() {
    return array_filter($this->configuration['entity_type_ids']);
  }

  /**
   * {@inheritdoc}
   */
  protected function isEntityTypeIdConfiguredAndApplicable($entity_type_id) {
    return in_array($entity_type_id, $this->getConfiguredApplicableEntityTypes());
  }

  /**
   * {@inheritdoc}
   */
  protected function parseEntityTypeId($entity_json_data_type) {
    $parsed_type = explode('--', $entity_json_data_type);
    return $parsed_type[0];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityStatusKey($entity_type_id) {
    return $this->entityDefinitions[$entity_type_id]->getKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    if (($entity_type_id = $this->parseEntityTypeId($entity_json_data['type'])) && $this->isEntityTypeIdConfiguredAndApplicable($entity_type_id)) {
      // We simply never want to set the status.
      unset($entity_json_data['attributes'][$this->getEntityStatusKey($entity_type_id)]);
    }
  }

}

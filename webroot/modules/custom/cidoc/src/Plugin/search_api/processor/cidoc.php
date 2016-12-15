<?php

namespace Drupal\cidoc\Plugin\search_api\processor;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;


/**
 * Adds CIDOC elements to items.
 *
 * @SearchApiProcessor(
 *   id = "cidoc",
 *   label = @Translation("CIDOC CRM Entity"),
 *   description = @Translation("Adds functionality for Cidoc Entities."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class Cidoc extends ProcessorPluginBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|null
   */
  protected $database;

  /**
   * The logger to use for logging messages.
   *
   * @var \Psr\Log\LoggerInterface|null
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setLogger($container->get('logger.channel.search_api'));

    return $processor;
  }

  /**
   * Retrieves the logger to use.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger to use.
   */
  public function getLogger() {
    return $this->logger ?: \Drupal::service('logger.channel.search_api');
  }

  /**
   * Sets the logger to use.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to use.
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), array('cidoc_entity'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = array();

    if ($datasource) {
      if (in_array($datasource->getEntityTypeId(), array('cidoc_entity'))) {
        $definition = array(
          'label' => $this->t('Child event count'),
          'description' => $this->t('The number of events considered a child.'),
          'type' => 'integer',
          'processor_id' => $this->getPluginId(),
          'hidden' => FALSE,
        );
        $properties['cidoc_child_events'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {

    // Only run for node and comment items.
    $entity_type_id = $item->getDatasource()->getEntityTypeId();
    if (!in_array($entity_type_id, array('cidoc_entity'))) {
      return;
    }

    // Get the node object.
    $entity_wrapper = $item->getOriginalObject();
    if (!$entity_wrapper) {
      // Apparently we were active for a wrong item.
      return;
    }

    /** @var CidocEntity $entity */
    $entity = $entity_wrapper->getValue();

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'cidoc_child_events');
    foreach ($fields as $field) {
      $field->addValue(count($entity->getChildEventEntities()));
    }
  }

}

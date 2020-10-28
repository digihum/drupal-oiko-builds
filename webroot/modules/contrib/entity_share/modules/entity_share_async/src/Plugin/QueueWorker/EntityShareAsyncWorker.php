<?php

declare(strict_types = 1);

namespace Drupal\entity_share_async\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\State\StateInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_async\Service\QueueHelperInterface;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Asynchronous import queue worker.
 *
 * @QueueWorker(
 *   id = "entity_share_async_import",
 *   title = @Translation("Entity Share asynchronous import"),
 *   cron = {"time" = 30}
 * )
 */
class EntityShareAsyncWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  private $importService;

  /**
   * The state storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $stateStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    ImportServiceInterface $import_service,
    StateInterface $state_storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->importService = $import_service;
    $this->stateStorage = $state_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.entity_share_async'),
      $container->get('entity_share_client.import_service'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $async_states = $this->stateStorage->get(QueueHelperInterface::STATE_ID, []);

    $import_context = new ImportContext($item['remote_id'], $item['channel_id'], $item['import_config_id']);
    $this->importService->prepareImport($import_context);

    $url = $this->importService->getRuntimeImportContext()->getChannelUrl();
    $parsed_url = UrlHelper::parse($url);
    $query = $parsed_url['query'];
    $query['filter']['uuid-filter'] = [
      'condition' => [
        'path' => 'id',
        'operator' => 'IN',
        'value' => [$item['uuid']],
      ],
    ];
    $query = UrlHelper::buildQuery($query);
    $prepared_url = $parsed_url['path'] . '?' . $query;

    // Get the entity json data.
    $response = $this->importService->jsonApiRequest('GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());

    // Import the entity.
    // Can not use the importService::importEntities() method directly because
    // batch causes problem when used inside CRON.
    $ids = $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));

    if (empty($ids)) {
      $this->logger->warning(
        "Cannot synchronise item @uuid from channel @channel_id of remote @remote_id with the import config @import_config_id",
        [
          '@uuid' => $item['uuid'],
          '@channel_id' => $item['channel_id'],
          '@remote_id' => $item['remote_id'],
          '@import_config_id' => $item['import_config_id'],
        ]
      );
    }

    if (isset($async_states[$item['remote_id']][$item['channel_id']][$item['uuid']])) {
      unset($async_states[$item['remote_id']][$item['channel_id']][$item['uuid']]);
    }

    // Update states.
    $this->stateStorage->set(QueueHelperInterface::STATE_ID, $async_states);
  }

}

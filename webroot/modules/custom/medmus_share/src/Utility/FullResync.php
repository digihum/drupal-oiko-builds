<?php

namespace Drupal\medmus_share\Utility;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_async\Service\QueueHelperInterface;
use Drupal\entity_share_client\Service\FormHelperInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\entity_share_client\Service\StateInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FullResync implements ContainerInjectionInterface  {

  /**
   * The remote websites known from the website.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface[]
   */
  protected $remoteWebsites;

  /**
   * Field mappings as returned by entity_share_server entry point.
   *
   * @var array
   */
  protected $fieldMappings;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The form helper.
   *
   * @var \Drupal\entity_share_client\Service\FormHelperInterface
   */
  protected $formHelper;

  /**
   * Query string parameters ($_GET).
   *
   * @var \Symfony\Component\HttpFoundation\ParameterBag
   */
  protected $query;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The queue helper.
   *
   * @var \Drupal\entity_share_async\Service\QueueHelperInterface
   */
  protected $queueHelper;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Drupal\entity_share_client\Service\FormHelperInterface $form_helper
   *   The form helper service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $import_service
   *   The import service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   * @param \Drupal\entity_share_client\Service\StateInformationInterface $state_information
   *   The state information service.
   * @param QueueHelperInterface $queue_helper
   *   The queue helper service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    FormHelperInterface $form_helper,
    RequestStack $request_stack,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    ImportServiceInterface $import_service,
    PagerManagerInterface $pager_manager,
    StateInformationInterface $state_information,
    QueueHelperInterface $queue_helper
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteWebsites = $entity_type_manager
      ->getStorage('remote')
      ->loadMultiple();
    $this->remoteManager = $remote_manager;
    $this->formHelper = $form_helper;
    $this->query = $request_stack->getCurrentRequest()->query;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->importService = $import_service;
    $this->pagerManager = $pager_manager;
    $this->stateInformation = $state_information;
    $this->queueHelper = $queue_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('entity_share_client.form_helper'),
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('entity_share_client.import_service'),
      $container->get('pager.manager'),
      $container->get('entity_share_client.state_information'),
      $container->get('entity_share_async.queue_helper')
    );
  }

  public function batchCallback(string $importer_id, string $remote_id, string $channel_id, string $channel_name, &$context) {
    $this->fetchAndProcessPage($context, $importer_id, $remote_id, $channel_id);

    if (!isset($context['sandbox']['pass'])) {
      $context['sandbox']['pass'] = 0;
    }

    $context['message'] = t('Processed @channel_name.', [
      '@channel_name' => $channel_name,
    ]);

    // If there's more content go back around.
    if ($context['sandbox']['more_content'] !== FALSE) {
      $context['finished'] = 0.5;
      $context['sandbox']['pass']++;
      $context['message'] = t('Processed @channel_name, pass @pass.', [
        '@channel_name' => $channel_name,
        '@pass' => $context['sandbox']['pass'],
      ]);
    }

  }

  protected function enqueueForSync(&$context, string $importer_id, string $remote_id, string $channel_id, $json_entity) {
    $this->queueHelper->enqueue($remote_id, $channel_id, $importer_id, $json_entity);
  }

  protected function processFetchEntities(&$context, string $importer_id, string $remote_id, string $channel_id, $json) {
    // Find the entities that need some kind of update.
    $ids = [];
    foreach (EntityShareUtility::prepareData($json['data']) as $data) {
      $status_info = $this->stateInformation->getStatusInfo($data);
      if ($status_info['info_id'] !== StateInformationInterface::INFO_ID_SYNCHRONIZED) {
        $ids[] = $data['id'];
      }
    }
    if (!empty($ids)) {
      $this->enqueueForSync($context, $importer_id, $remote_id, $channel_id, $ids);
    }
  }

  protected function fetchAndProcessPage(&$context, string $importer_id, string $remote_id, string $channel_id) {

    $selected_remote = $this->remoteWebsites[$remote_id];

    $response = $this->remoteManager->jsonApiRequest($selected_remote, 'GET', $this->getUrlToFetch($context, $remote_id, $channel_id));
    $json = Json::decode((string) $response->getBody());

    // Process the entities.
    $this->processFetchEntities($context, $importer_id, $remote_id, $channel_id, $json);

    // Set the next URL to fetch.
    $context['sandbox']['more_content'] = FALSE;
    if (isset($json['links']['next']['href'])) {
      $context['sandbox']['more_content'] = $json['links']['next']['href'];
    }
  }

  protected function getUrlToFetch(&$context, $remote_id, $channel_id) {
    if (!empty($context['sandbox']['more_content'])) {
      return $context['sandbox']['more_content'];
    }
    else {
      $channels_info = $this->getChannelsInfos($remote_id);
      $channel_entity_type = $channels_info[$channel_id]['channel_entity_type'];
      $channel_bundle = $channels_info[$channel_id]['channel_bundle'];

      $selected_remote = $this->remoteWebsites[$remote_id];
      $this->fieldMappings = $this->remoteManager->getfieldMappings($selected_remote);
      $parsed_url = UrlHelper::parse($channels_info[$channel_id]['url']);


      $search_filter_and_group['changed_filter'] = [
        'condition' => [
          'path' => 'changed',
          'operator' => '>',
          // Limit to the last 90 days.
          'value' => Drupal::time()->getRequestTime() - 86400 * 90,
        ],
      ];
      $parsed_url['query']['filter'] = isset($parsed_url['query']['filter']) ? array_merge_recursive($parsed_url['query']['filter'], $search_filter_and_group) : $search_filter_and_group;

      // Change the sort if a sort had been selected.
      $sort_field = 'changed';
      $sort_direction = 'desc';

      if (!empty($sort_field) && !empty($sort_direction) && isset($this->fieldMappings[$channel_entity_type][$channel_bundle][$sort_field])) {
        $parsed_url['query']['sort'] = [
          $sort_field => [
            'path' => $this->fieldMappings[$channel_entity_type][$channel_bundle][$sort_field],
            'direction' => strtoupper($sort_direction),
          ],
        ];
      }
      $query = UrlHelper::buildQuery($parsed_url['query']);
      $prepared_url = $parsed_url['path'] . '?' . $query;
      return $prepared_url;
    }
  }

  protected $channelsInfoCache = [];
  protected function getChannelsInfos(string $remote_id) {
    if (!isset($this->channelsInfoCache[$remote_id])) {
      $selected_remote = $this->remoteWebsites[$remote_id];
      $this->channelsInfoCache[$remote_id] = $this->remoteManager->getChannelsInfos($selected_remote);
    }
    return $this->channelsInfoCache[$remote_id];
  }

}

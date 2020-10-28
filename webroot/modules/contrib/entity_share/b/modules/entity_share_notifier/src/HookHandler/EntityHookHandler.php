<?php

declare(strict_types = 1);

namespace Drupal\entity_share_notifier\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_share_server\Service\ChannelManipulatorInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook handler for the entity_update() and entity_insert hooks.
 *
 * @package Drupal\entity_share_notifier\HookHandler
 */
class EntityHookHandler implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The channel manipulator.
   *
   * @var \Drupal\entity_share_server\Service\ChannelManipulatorInterface
   */
  protected $channelManipulator;

  /**
   * The client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $clientFactory;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The bundle infos from the website.
   *
   * @var array
   */
  protected $bundleInfos;

  /**
   * EntityHookHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\entity_share_server\Service\ChannelManipulatorInterface $channel_manipulator
   *   The channel manipulator.
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   *   The client factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    ChannelManipulatorInterface $channel_manipulator,
    ClientFactory $client_factory,
    LoggerInterface $logger,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->channelManipulator = $channel_manipulator;
    $this->clientFactory = $client_factory;
    $this->logger = $logger;
    $this->bundleInfos = $entity_type_bundle_info->getAllBundleInfo();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity_share_server.channel_manipulator'),
      $container->get('http_client_factory'),
      $container->get('logger.channel.entity_share_notifier'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Notify, if available, clients.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function process(EntityInterface $entity) {
    $channels_to_notify = [];
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager
      ->getStorage('channel')
      ->loadMultiple();
    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);

    $entity_type_id = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_langcode = $entity->language()->getId();
    $entity_uuid = $entity->uuid();

    // Loop on channels to find the ones (for translations) in which the
    // entity is present.
    foreach ($channels as $channel) {
      // After this check, we know that we are manipulating a content entity.
      $channel_entity_type = $channel->get('channel_entity_type');
      if ($channel_entity_type != $entity_type_id) {
        continue;
      }

      // Check bundle.
      $channel_bundle = $channel->get('channel_bundle');
      if ($channel_bundle != $entity_bundle) {
        continue;
      }

      // Check langcode, if applicable.
      if (isset($this->bundleInfos[$channel_entity_type][$channel_bundle]['translatable']) && $this->bundleInfos[$channel_entity_type][$channel_bundle]['translatable']) {
        $channel_langcode = $channel->get('channel_langcode');
        if ($channel_langcode != $entity_langcode) {
          continue;
        }
      }

      // TODO. Will require to add a new field on channel to store the user to
      // test with.
      // Test that the entity appears on the channel.
      //      $route_name = sprintf('jsonapi.%s--%s.collection', $channel_entity_type, $channel_bundle);
      //      $query = $this->channelManipulator->getQuery($channel);
      //      $query['filter']['uuid-filter'] = [
      //        'condition' => [
      //          'path' => 'id',
      //          'operator' => 'IN',
      //          'value' => [$entity_uuid],
      //        ],
      //      ];
      //      $query = UrlHelper::buildQuery($query);
      //
      //      $url = Url::fromRoute($route_name)
      //        ->setOption('language', $languages[$channel_langcode])
      //        ->setOption('absolute', TRUE)
      //        ->setOption('query', $query);.
      $channels_to_notify[] = $channel->id();
    }

    /** @var \Drupal\entity_share_notifier\Entity\EntityShareSubscriberInterface[] $subscribers */
    $subscribers = $this->entityTypeManager
      ->getStorage('entity_share_subscriber')
      ->loadMultiple();

    // Loop on subscribers to notify its.
    foreach ($subscribers as $subscriber) {
      $channels_to_notify_for_subscriber = array_intersect($channels_to_notify, $subscriber->get('channel_ids'));

      if (empty($channels_to_notify_for_subscriber)) {
        continue;
      }

      $remote_id = $subscriber->get('remote_id');
      $remote_config_id = $subscriber->get('remote_config_id');
      $http_client = $this->clientFactory->fromOptions([
        'base_uri' => $subscriber->get('subscriber_url') . '/',
        'auth' => [
          $subscriber->get('basic_auth_username'),
          $subscriber->get('basic_auth_password'),
        ],
        'headers' => [
          'Content-type' => 'application/json',
        ],
      ]);

      foreach ($channels_to_notify_for_subscriber as $channel_id) {
        try {
          $http_client->request('POST', 'entity_share/async', [
            'json' => [
              'remote_config_id' => $remote_config_id,
              'remote_id' => $remote_id,
              'channel_id' => $channel_id,
              'uuid' => $entity_uuid,
            ],
          ]);
        }
        catch (ClientException $e) {
          $this->logger->error('Error when requesting client: @exception_message', ['@exception_message' => $e->getMessage()]);
        }
        catch (ServerException $e) {
          $this->logger->error('Error when requesting client: @exception_message', ['@exception_message' => $e->getMessage()]);
        }
        catch (\Exception $e) {
          $this->logger->error('Error when requesting client: @exception_message', ['@exception_message' => $e->getMessage()]);
        }
      }
    }
  }

}

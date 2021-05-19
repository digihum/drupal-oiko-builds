<?php

namespace Drupal\medmus_share\Utility;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\State;
use Drupal\Core\State\StateInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\entity_share_client\Service\FormHelperInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\entity_share_client\Service\StateInformationInterface;
use Drupal\entity_share_notifier\Entity\EntityShareSubscriberInterface;
use Drupal\medmus_share\Entity\DeletedRemoteEntity;
use Drupal\medmus_share\Entity\DeletedRemoteEntityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MedmusShareEntityDeleteHandler implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
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
   * The Entity Share state service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $entityShareStateInformation;

  /**
   * Constructs a ContentEntityForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    StateInformationInterface $entity_share_state_information
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->bundleInfos = $entity_type_bundle_info->getAllBundleInfo();
    $this->entityShareStateInformation = $entity_share_state_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.channel.medmus_share'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_share_client.state_information')
    );
  }

  /**
   * Main entry point.
   *
   * We need to record that this entity has been deleted, so that if we attempt
   * to re-sync it, it does not come back.
   */
  public function handleEntityDelete(EntityInterface $entity) {
    if ($entity instanceof ContentEntityInterface && $entity->uuid()) {
      // Also update the entity state information if it's available.
      $state_information_service = \Drupal::service('entity_share_client.state_information');
      if ($entity_import_status = $this->entityShareStateInformation->getImportStatusOfEntity($entity)) {
        $entity_import_status
          ->setPolicy(EntityImportStatusInterface::IMPORT_POLICY_SKIP)
          ->save();
      }
      else {
        // It's possible that an entity belongs to one of our entity share
        // channels, but never got any state information recorded, in that case,
        // add the state information so we can record the delete.
        if ($channels = $this->getChannelsWithEntity($entity)) {
          foreach ($this->getRemoteSiteIds($channels) as $remote) {
            foreach ($channels as $channel) {
              $this->entityShareStateInformation->createImportStatusOfEntity($entity, [
                'remote_website' => $remote->id(),
                'channel_id' => $channel->id(),
                'policy' => EntityImportStatusInterface::IMPORT_POLICY_SKIP,
              ]);
            }
          }
        }
      }
    }
  }

  /**
   * Get the channels in which an entity might have appeared.
   */
  protected function getChannelsWithEntity(EntityInterface $entity) {
    $channels_to_notify = [];
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager
      ->getStorage('channel')
      ->loadMultiple();

    $entity_type_id = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_langcode = $entity->language()->getId();

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

      $channels_to_notify[$channel->id()] = $channel;
    }

    return $channels_to_notify;
  }

  /**
   * Get the loaded channel entities.
   */
  protected function getRemoteSiteIds($channels) {
    return $this->entityTypeManager
      ->getStorage('remote')
      ->loadMultiple();
  }

}

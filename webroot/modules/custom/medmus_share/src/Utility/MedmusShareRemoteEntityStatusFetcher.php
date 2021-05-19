<?php

namespace Drupal\medmus_share\Utility;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
use Drupal\entity_share_client\Service\FormHelperInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\medmus_share\Entity\DeletedRemoteEntity;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MedmusShareRemoteEntityStatusFetcher implements ContainerInjectionInterface {

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The medmusDeletedRemoteEntityStorage entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $medmusDeletedRemoteEntityStorage;

  /**
   * The remote websites known from the website.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface[]
   */
  protected $remoteWebsites;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a ContentEntityForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    StateInterface $state,
    TimeInterface $time,
    LoggerInterface $logger
  ) {
    $this->medmusDeletedRemoteEntityStorage = $entity_type_manager
      ->getStorage('medmus_deleted_remote_entity');
    $this->remoteWebsites = $entity_type_manager
      ->getStorage('remote')
      ->loadMultiple();
    $this->remoteManager = $remote_manager;
    $this->state = $state;
    $this->time = $time;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('state'),
      $container->get('datetime.time'),
      $container->get('logger.channel.medmus_share')
    );
  }

  /**
   * Fetch deleted entity records from the remote sites.
   */
  public function fetchEntities() {
    // Get the remote websites from entity share client.
    foreach ($this->remoteWebsites as $remote) {
      $prepared_url = '/rest-export/v1/medmus/sync-skips';
      $query = [];
      $state_key = 'medmus_share.deleted_entity_sync_last.' . $remote->id();
      if ($last_fetched = $this->state->get($state_key)) {
        $date_time = new DateTimePlus($last_fetched);
        $date_time->modify('-30 minutes');
        // Need to format the date for views :(
        $query['changed'] = $date_time->format(DateTimePlus::FORMAT);
      }
      $query = UrlHelper::buildQuery($query);
      $prepared_url .= '?' . $query;
      $response = $this->remoteManager->request($remote, 'GET', $prepared_url);
      if (empty($response)) {
        $this->logger->error('Could not fetch skipped entities from remote website: @remote, trying to fetch: @url.', [
          '@remote' => $remote->label(),
          '@url' => $prepared_url,
        ]);
      }
      else {
        $json = Json::decode((string) $response->getBody());
        // If this is a bunch of JSON objects, then pull those in.
        if (is_array($json)) {
          foreach ($json as $item) {
            if (count(array_intersect_key($item, array_fill_keys(['tracking_id', 'changed', 'type', 'uuid'], TRUE))) === 4) {
              $this->ensureDeletedRemoteEntityExists($remote->id(), $item['tracking_id'], $item['type'], $item['uuid'], EntityShareUtility::convertChangedTime($item['changed']));
            }
          }
          // Update the last fetched time.
          $this->state->set($state_key, '@' . $this->time->getRequestTime());
        }
      }
    }
  }

  /**
   * Ensure that the record of the remote deleted entity exists.
   */
  protected function ensureDeletedRemoteEntityExists($remote_id, $tracking_id, $entity_type, $entity_uuid, int $changed_time) {
    $existing_entities = $this->medmusDeletedRemoteEntityStorage
      ->getQuery()
      ->condition('remote_website', $remote_id)
      ->condition('entity_uuid', $entity_uuid)
      ->execute();
    if (empty($existing_entities)) {
      $deleted_entity = DeletedRemoteEntity::create([
        'tracking_id' => $tracking_id,
        'entity_uuid' => $entity_uuid,
        'entity_type_id' => $entity_type,
        'remote_website' => $remote_id,
      ]);
    }
    else {
      foreach ($existing_entities as $id) {
        $deleted_entity = DeletedRemoteEntity::load(($id));
      }
      // Change the decision back to undecided as this just changed upstream.
      $deleted_entity->set('decision', DeletedRemoteEntity::DECISION_UNDECIDED);
    }

    $deleted_entity->save();
  }
}

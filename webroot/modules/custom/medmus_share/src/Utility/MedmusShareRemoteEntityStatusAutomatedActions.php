<?php

namespace Drupal\medmus_share\Utility;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\medmus_share\Entity\DeletedRemoteEntityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class MedmusShareRemoteEntityStatusAutomatedActions implements ContainerInjectionInterface {

  /**
   * The medmusDeletedRemoteEntityStorage entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $medmusDeletedRemoteEntityStorage;


  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a ContentEntityForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    Connection $connection
  ) {
    $this->medmusDeletedRemoteEntityStorage = $entity_type_manager
      ->getStorage('medmus_deleted_remote_entity');
    $this->logger = $logger;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.channel.medmus_share'),
      $container->get('database')
    );
  }

  /**
   * Main entry point, runs on cron.
   *
   * Determine if the any local entities can be deleted.
   *
   * You need to enable this in your settings.php file like so:
   * <code>
   * $settings['medmus_share_auto_delete_enabled'] = TRUE;
   * <endcode>
   */
  public function autoDeleteLocalEntities() {
    // Query for remote entities that have been deleted, where our local entity
    // is unpublished. We'll remove it locally too.
    if (Settings::get('medmus_share_auto_delete_enabled', FALSE)) {
      foreach (['cidoc_entity', 'cidoc_reference'] as $entity_type) {
        $query = $this->connection->select('medmus_deleted_remote_entity', 'd')
          ->fields('d', ['id'])
          ->condition('d.decision', DeletedRemoteEntityInterface::DECISION_UNDECIDED)
          ->range(0, 200);
        $query->innerJoin($entity_type, 'e', 'e.uuid = d.entity_uuid AND d.entity_type_id = :entity_type', [':entity_type' => $entity_type]);
        $query->condition('e.status', 0);

        $results = $query->execute()->fetchCol();
        if (!empty($results)) {
          /* @var \Drupal\medmus_share\Entity\DeletedRemoteEntityInterface $entity */
          foreach ($this->medmusDeletedRemoteEntityStorage->loadMultiple($results) as $entity) {
            $transaction = \Drupal::database()->startTransaction();
            $entity->label();
            $entity->setDecision(DeletedRemoteEntityInterface::DECISION_DELETE_AUTOMATICALLY);
            if ($local_entity = $entity->getLocalEntity()) {
              $this->logger->info('Automatically removed unpublished entity: %label', ['%label' => $local_entity->label()]);
              $local_entity->delete();
            }
            $entity->save();
            unset($transaction);
          }
        }
      }
    }
  }
}

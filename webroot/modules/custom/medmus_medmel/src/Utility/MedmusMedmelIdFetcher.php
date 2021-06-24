<?php

namespace Drupal\medmus_medmel\Utility;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MedmusMedmelIdFetcher implements ContainerInjectionInterface {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a ContentEntityForm object.
   */
  public function __construct(
    ClientInterface $client,
    LoggerInterface $logger,
    Connection $database
  ) {
    $this->httpClient = $client;
    $this->logger = $logger;
    $this->database = $database;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('logger.channel.medmus_medmel'),
      $container->get('database')
    );
  }

  function syncIds() {
    // Fetch the IDs, and make our DB table look like the JSON.
    $request = $this->httpClient->request('GET', 'http://medmel.polisemie.it/php/getStaffId.php');

    if ($request->getStatusCode() != 200) {
      $this->logger->error('Got error code: @code', [
        '@code' => $request->getStatusCode(),
      ]);
    }
    else {
      // Process the data.
      $contents = json_decode($request->getBody()->getContents(), TRUE);
      if (!empty($contents) && is_array($contents)) {
        // This will ensure that our delete all and then re-insert everything works atomically.
        $transaction = $this->database->startTransaction();
        $this->database
          ->delete('medmus_medmel')
          ->execute();
        $insert = $this->database
          ->insert('medmus_medmel')
          ->fields(['unique_id', 'work_id', 'ms']);
        foreach ($contents as $content) {
          $insert->values(['unique_id' => $content['unique_id'], 'work_id' => $content['work_id'], 'ms' => $content['ms']]);
        }
        $insert->execute();
        // Commit our transaction.
        unset($transaction);
      }
    }

  }

}

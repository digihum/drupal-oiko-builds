<?php

namespace Drupal\medmus_medmel\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
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
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ContentEntityForm object.
   */
  public function __construct(
    ClientInterface $client,
    LoggerInterface $logger,
    Connection $database,
    ConfigFactoryInterface $configFactory
  ) {
    $this->httpClient = $client;
    $this->logger = $logger;
    $this->database = $database;
    $this->configFactory = $configFactory;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('logger.channel.medmus_medmel'),
      $container->get('database'),
      $container->get('config.factory')
    );
  }

  function syncIds() {
    $config = $this->configFactory->get('medmus_medmel.settings');

    try {
      // Fetch the IDs, and make our DB table look like the JSON.
      $request = $this->httpClient->request('GET', $config->get('fetchUrl'), ['verify' => '/webroot/modules/custom/medmus_medmel/certificate/medmel-seai-uniroma1-it-chain.pem']);

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

          // Now we should try to find entities that match these items and invalidate their cache tags.
        }
      }
    }
    catch (RequestException $e) {
      if ($e->hasResponse()) {
        $this->logger->error('Got error code: @code and message: @message', [
          '@code' => $e->getCode(),
          '@message' => (string) $e->getResponse()->getBody(),
        ]);
      }
      else {
        $this->logger->error('Got exception: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

}

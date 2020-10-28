<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client_remote_manager_test\Service;

use Drupal\entity_share_client\Service\RemoteManager;
use GuzzleHttp\ClientInterface;

/**
 * Class TestRemoteManager.
 *
 * @package Drupal\entity_share_client_remote_manager_test\Service
 */
class TestRemoteManager extends RemoteManager {

  /**
   * A mapping, URL => response, from the GET requests made.
   *
   * @var \Psr\Http\Message\ResponseInterface[]
   */
  protected $responseMapping = [];

  /**
   * {@inheritdoc}
   */
  protected function doRequest(ClientInterface $client, $method, $url) {
    // It it is a GET request store the result to be able to re-obtain the
    // result to simulate another website.
    if ($method == 'GET') {
      if (!isset($this->responseMapping[$url])) {
        $this->responseMapping[$url] = parent::doRequest($client, $method, $url);
      }

      return $this->responseMapping[$url];
    }

    return parent::doRequest($client, $method, $url);
  }

}

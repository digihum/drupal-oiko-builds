<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\entity_share_client\ImportContext;

/**
 * Import service interface methods.
 */
interface ImportServiceInterface {

  /**
   * Import a list of entities.
   *
   * 50 Max.
   *
   * @param \Drupal\entity_share_client\ImportContext $context
   *   The import context.
   * @param array $uuids
   *   The list of UUID's to import.
   */
  public function importEntities(ImportContext $context, array $uuids);

  /**
   * Import all the entities on a channel.
   *
   * @param \Drupal\entity_share_client\ImportContext $context
   *   The import context.
   */
  public function importChannel(ImportContext $context);

  /**
   * Import the entities on a specific channel's page.
   *
   * @param \Drupal\entity_share_client\ImportContext $context
   *   The import context.
   */
  public function importChannelPage(ImportContext $context);

  /**
   * Use data from the JSON:API to import content.
   *
   * @param array $entity_list_data
   *   An array of data from a JSON:API endpoint.
   *
   * @return int[]
   *   The list of entity IDs imported keyed by UUIDs.
   */
  public function importEntityListData(array $entity_list_data);

  /**
   * Prepare runtime import context and import processors.
   *
   * Originally this method is meant to be protected. But as Batch API can't
   * pass complex objects in batch's context or as batch operation's argument,
   * and instead of creating a dedicated method for that, it has been put as a
   * public method.
   *
   * @param \Drupal\entity_share_client\ImportContext $context
   *   The import context.
   *
   * @return bool
   *   TRUE if the import information can be gathered.
   */
  public function prepareImport(ImportContext $context);

  /**
   * Getter.
   *
   * @return \Drupal\entity_share_client\RuntimeImportContext
   *   The import service's runtime import context.
   */
  public function getRuntimeImportContext();

  /**
   * Performs a HTTP request.
   *
   * Pass the request to the injected remote manager using RuntimeImportContext
   * data.
   *
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  public function request($method, $url);

  /**
   * Performs a HTTP request on a JSON:API endpoint.
   *
   * Pass the request to the injected remote manager using RuntimeImportContext
   * data.
   *
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  public function jsonApiRequest($method, $url);

}

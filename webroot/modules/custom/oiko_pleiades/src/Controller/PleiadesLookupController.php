<?php

namespace Drupal\oiko_pleiades\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class PleiadesLookupController.
 *
 * @package Drupal\oiko_pleiades\Controller
 */
class PleiadesLookupController extends ControllerBase {

  /**
   * GuzzleHttp\Client definition.
   *
   * @var Client
   */
  protected $http_client;
  /**
   * {@inheritdoc}
   */
  public function __construct(Client $http_client) {
    $this->http_client = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Pleiadeslookup.
   *
   * @return string
   *   Return Hello string.
   */
  public function pleiadesLookup(Request $request) {

    $url = $request->query->get('pleiades');
    // Validate this this is a Pleiades URL.
    if (!preg_match('/^http:\/\/pleiades\.stoa\.org\/places\/(\d+)$/i', $url)) {
      throw new AccessDeniedHttpException();
    }

    // Do the lookup:
    // @TODO: Catch some exceptions.
    $responseJson = json_decode($this->http_client->get($url . '/json')->getBody(), true);

    if (isset($responseJson['reprPoint'])) {
      return new JsonResponse(array('lat' => $responseJson['reprPoint'][1], 'lon' => $responseJson['reprPoint'][0]));
    }
  }

}
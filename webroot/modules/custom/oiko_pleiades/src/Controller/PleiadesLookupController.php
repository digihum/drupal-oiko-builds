<?php

namespace Drupal\oiko_pleiades\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\Geocoder;
use Drupal\geocoder\ProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
  public function __construct(
    Client $http_client,
    Geocoder $geocoder,
    ProviderPluginManager $provider_plugin_manager,
    DumperPluginManager $dumper_plugin_manager
  ) {
    $this->http_client = $http_client;
    $this->geocoder = $geocoder;
    $this->providerPluginManager = $provider_plugin_manager;
    $this->dumperPluginManager = $dumper_plugin_manager;
  }

  /**
   * The provider plugin manager service.
   *
   * @var \Drupal\geocoder\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The dumper plugin manager service.
   *
   * @var \Drupal\geocoder\DumperPluginManager
   */
  protected $dumperPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('geocoder'),
      $container->get('plugin.manager.geocoder.provider'),
      $container->get('plugin.manager.geocoder.dumper')
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
    if (!preg_match('/^https?:\/\/pleiades\.stoa\.org\/places\/(\d+)$/i', $url)) {
      throw new AccessDeniedHttpException();
    }

    // Do the lookup:
    // @TODO: Catch some exceptions.
    $responseJson = json_decode($this->http_client->get($url . '/json')->getBody(), true);

    if (isset($responseJson['reprPoint'])) {
      return new JsonResponse(array('lat' => $responseJson['reprPoint'][1], 'lon' => $responseJson['reprPoint'][0]));
    }
  }

  public function geocoderLookup(Request $request) {
    $location = $request->query->get('location');

    $plugins = $this->getEnabledProviderPlugins();
    $dumper = $this->dumperPluginManager->createInstance('wkt');

    if ($addressCollection = $this->geocoder->geocode($location, array_keys($plugins))) {
      return new JsonResponse(['wkt' => $dumper->dump($addressCollection->first())]);
    }

    throw new NotFoundHttpException();
  }

  /**
   * Get the list of enabled Provider plugins.
   *
   * @return array
   *   Provider plugin IDs and their properties (id, name, arguments...).
   */
  protected function getEnabledProviderPlugins() {
    $geocoder_plugins = $this->providerPluginManager->getPlugins();
    $provider_plugin_ids = array(
      'googlemaps',
      'openstreetmap',
    );

    $provider_plugin_ids = array_combine($provider_plugin_ids, $provider_plugin_ids);

    foreach ($geocoder_plugins as $plugin) {
      if (isset($provider_plugin_ids[$plugin['id']])) {
        $provider_plugin_ids[$plugin['id']] = $plugin;
      }
    }

    return $provider_plugin_ids;
  }

}

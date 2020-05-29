<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MapPageController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class MapPageController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entity_type_manager;

  /**
   * The lock system.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager, LockBackendInterface $lock) {
    $this->entity_type_manager = $entity_type_manager;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('lock')
    );
  }

  /**
   * Basemap.
   *
   * @return string
   *   Return Hello string.
   */
  public function allEntitiesForMap(Request $request) {
    $data = [
      'features' => [],
    ];
    $page = $request->query->getInt('page');
    $entities_per_page = 100;

    // Add some lovely locking.
    $lock_name = 'MapPageController::allEntitiesForMap::' . $page;
    if ($this->lock->acquire($lock_name, 180)) {
      $storage = $this->entity_type_manager->getStorage('cidoc_entity');

      $query = $storage->getQuery()
        // We're essentially doing our own access checking so skip the built in stuff.
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->range($page * $entities_per_page, $entities_per_page)
        ->sort('id');
      \Drupal::moduleHandler()->invokeAll('oiko_app_all_entities_query_alter', [$query]);
      $results = $query->execute();

      // Get the entities.
      $entities = $storage->loadMultiple($results);
      foreach ($entities as $entity) {
        /** @var \Drupal\cidoc\Entity\CidocEntity $entity */
        $data['features'] = array_merge($data['features'], $entity->getGeospatialData());
      }

      $response = new CacheableJsonResponse($data);
      foreach ($entities as $entity) {
        $response->addCacheableDependency($entity);
      }

      // Add the paging cache information.
      $response->getCacheableMetadata()->addCacheContexts(['url.query_args:page']);
      if (isset($more_url)) {
        $response->addCacheableDependency($more_url);
      }
      else {
        // Add the list tag so that new items will get tagged onto the end.
        $definition = $this->entity_type_manager->getDefinition('cidoc_entity');
        $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());
      }

      $this->lock->release($lock_name);

      return $response;
    }
    else {
      // Get the browser to retry in a bit.
      $this->lock->wait($lock_name, 10);
      return new RedirectResponse(Url::fromRoute('oiko_leaflet.map_page_controller_allEntities', [], ['query' => ['page' => $page]])->toString(), 307);
    }
  }

  /**
   *
   */
  public function ownEntitiesForMap(Request $request) {
    $data = [
      'features' => [],
    ];

    $currentUser = $this->currentUser();
    // Add some lovely locking.
    $lock_name = 'MapPageController::ownEntitiesForMap::' . $currentUser->id();
    if ($this->lock->acquire($lock_name, 180)) {
      $storage = $this->entity_type_manager->getStorage('cidoc_entity');
      $query = $storage->getQuery()
        // Query for unpublished entities. Access checking takes care of limiting this to the correct entities.
        ->condition('status', 0)
        ->sort('id');

      \Drupal::moduleHandler()->invokeAll('oiko_app_own_entities_query_alter', [$query]);

      // User can view all entities.
      if ($currentUser->hasPermission('view unpublished cidoc entities')) {
        $query->condition('status', 0);
      }
      else if ($currentUser->hasPermission('view own unpublished cidoc entities')) {
        $query->condition('status', 0);
        $query->condition('user_id', $currentUser->id());
      }

      $results = $query->execute();

      // Get the entities.
      $entities = $storage->loadMultiple($results);
      foreach ($entities as $entity) {
        /** @var \Drupal\cidoc\Entity\CidocEntity $entity */
        $data['features'] = array_merge($data['features'], $entity->getGeospatialData());
      }

      $response = new CacheableJsonResponse($data);
      $response->getCacheableMetadata()->addCacheContexts(['user']);
      foreach ($entities as $entity) {
        $response->addCacheableDependency($entity);
      }

      $definition = $this->entity_type_manager->getDefinition('cidoc_entity');
      $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());

      $this->lock->release($lock_name);

      return $response;
    }
    else {
      // Get the browser to retry in a bit.
      $this->lock->wait($lock_name, 10);
      return new RedirectResponse(Url::fromRoute('oiko_leaflet.map_page_controller_unpublishedEntities')->toString(), 307);
    }
  }


  /**
   * Basemap.
   *
   * @return string
   *   Return Hello string.
   */
  public function baseMap() {

    $data = [];

    $map = leaflet_map_get_info('Ancient Terrain');
    $map['sidebar'] = TRUE;
    $map['pagestate'] = TRUE;
    $map['timeline'] = TRUE;
    $map['search'] = TRUE;
    $map['empires'] = TRUE;
    $map['clustering'] = TRUE;
    $map['locate'] = TRUE;
    $map['layerControl'] = TRUE;
    $map['data-url'] = Url::fromRoute('oiko_leaflet.map_page_controller_allEntities')->toString();
    $height = 'full';

    $map = leaflet_render_map($map, $data, $height);

    $map['#theme_wrappers']['container'] = [
      '#attributes' => [
        'class' => ['l-map-wrap'],
      ],
    ];

    return $map;
  }

}

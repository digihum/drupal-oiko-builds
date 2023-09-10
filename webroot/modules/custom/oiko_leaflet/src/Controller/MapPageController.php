<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\JsonResponse;
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface;
   */
  protected $cacheBin;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LockBackendInterface $lock, ModuleHandlerInterface $moduleHandler, CacheBackendInterface $cacheBin) {
    $this->entity_type_manager = $entity_type_manager;
    $this->lock = $lock;
    $this->moduleHandler = $moduleHandler;
    $this->cacheBin = $cacheBin;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('lock'),
      $container->get('module_handler'),
      $container->get('cache.default')
    );
  }

  protected $countOfEntityPages;

  /**
   * Get the number of pages of entities we need to query/cache.
   *
   * @param int $page_size
   *
   * @return false|float
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCountOfEntityPages($page_size = 100) {
    if (!isset($this->countOfEntityPages)) {
      $storage = $this->entity_type_manager->getStorage('cidoc_entity');
      $query = $storage->getQuery()
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->count();
      $this->moduleHandler->invokeAll('oiko_app_all_entities_query_alter', [$query]);
      $count = $query->execute();
      $this->countOfEntityPages = (int) ceil($count / $page_size);
    }
    return $this->countOfEntityPages;
  }

  protected function getEntityDataForSinglePage($page, $page_size = 100) {
    $data = [
      'features' => [],
    ];
    $cacheabilityMetadata = new CacheableMetadata();
    $storage = $this->entity_type_manager->getStorage('cidoc_entity');

    $query = $storage->getQuery()
      // We're essentially doing our own access checking so skip the built in stuff.
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->range($page * $page_size, $page_size)
      ->sort('id');
    \Drupal::moduleHandler()->invokeAll('oiko_app_all_entities_query_alter', [$query]);
    $results = $query->execute();

    // Get the entities.
    $entities = $storage->loadMultiple($results);
    foreach ($entities as $entity) {
      /** @var \Drupal\cidoc\Entity\CidocEntity $entity */
      $data['features'] = array_merge($data['features'], $entity->getGeospatialData());
    }

    foreach ($entities as $entity) {
      $cacheabilityMetadata->addCacheableDependency($entity);
    }

    if ($page == $this->getCountOfEntityPages() - 1) {
      $definition = $this->entity_type_manager->getDefinition('cidoc_entity');
      $cacheabilityMetadata->addCacheTags($definition->getListCacheTags());
    }

    return [
      'data' => $data,
      'cacheabilityMetadata' => $cacheabilityMetadata,
    ];
  }

  /**
   * Basemap.
   *
   * @return string
   *   Return Hello string.
   */
  public function allEntitiesForMap() {
    $entities_per_page = $this->entitiesPerInternalRestPage();
    $count_of_pages = $this->getCountOfEntityPages($entities_per_page);

    // Query for all these pages from cache.
    $pages = range(0, $count_of_pages - 1);
    $cids = array_combine(array_map(function ($num) use ($entities_per_page) {
      return 'oiko_leaflet:MapPageController:allEntitiesForMap:' . $entities_per_page . ':' . $num;
    }, $pages), $pages);
    $uncached_cids = array_keys($cids);
    // Sometimes we can run out of RAM getting things from cache!
    // So chunk it down.
    $cached_pages = [];
    $remaining_pages = [];
    foreach (array_chunk($uncached_cids, 10) as $chunk_to_fetch) {
      $cached_pages += $this->cacheBin->getMultiple($chunk_to_fetch);
      $remaining_pages = array_merge($remaining_pages, $chunk_to_fetch);
    }

    $data = [
      'features' => [],
    ];

    Timer::start('entityPageGeneration');

    // If we have multiple threads regenerating these pages we don't mind them doing it in parallel, but we might as well get them to rebuild the pages in a random order so that hopefully they can complete some different pages each before redirecting and then spotting that the other thread has done some work too.
    shuffle($remaining_pages);

    // If there are uncached pages: compute those now.
    while (!empty($remaining_pages)) {
      $cid = array_shift($remaining_pages);
      $page = $cids[$cid];

      $this_page = $this->getEntityDataForSinglePage($page, $entities_per_page);
      // Store in the cache.
      $this->cacheBin->set($cid, $this_page['data'], Cache::PERMANENT, $this_page['cacheabilityMetadata']->getCacheTags());

      // Merge in the data for rendering.
      $data['features'] = array_merge($data['features'], $this_page['data']['features']);

      // If we have been executing for more than 10 seconds, redirect.
      if (Timer::read('entityPageGeneration') > 10000) {
        return new RedirectResponse(Url::fromRoute('oiko_leaflet.map_page_controller_allEntities')->toString(), 307, ['X-Oiko-Cache-Pages-Left-To-Generate' => count($remaining_pages)]);
      }
    }

    // Process the cached pages of data into a big array.
    foreach ($cached_pages as $cache_page) {
      $data['features'] = array_merge($data['features'], $cache_page->data['features']);
    }

    $response = new JsonResponse($data);
    return $response;
  }

  protected function entitiesPerInternalRestPage() {
    return 200;
  }

  /**
   * Sometimes the pages aren't generated, so give things a little helping hand.
   */
  public function computePagesOnCron() {
    $entities_per_page = $this->entitiesPerInternalRestPage();
    $count_of_pages = $this->getCountOfEntityPages($entities_per_page);

    // Query for all these pages from cache.
    $pages = range(0, $count_of_pages - 1);
    $cids = array_combine(array_map(function ($num) use ($entities_per_page) {
      return 'oiko_leaflet:MapPageController:allEntitiesForMap:' . $entities_per_page . ':' . $num;
    }, $pages), $pages);
    $uncached_cids = array_keys($cids);
    $this->cacheBin->getMultiple($uncached_cids);

    // If we have multiple threads regenerating these pages we don't mind them doing it in parallel, but we might as well get them to rebuild the pages in a random order so that hopefully they can complete some different pages each before redirecting and then spotting that the other thread has done some work too.
    shuffle($uncached_cids);

    // If there are uncached pages: compute those now.
    while (!empty($uncached_cids)) {
      $cid = array_shift($uncached_cids);
      $page = $cids[$cid];

      $this_page = $this->getEntityDataForSinglePage($page, $entities_per_page);
      // Store in the cache.
      $this->cacheBin->set($cid, $this_page['data'], Cache::PERMANENT, $this_page['cacheabilityMetadata']->getCacheTags());
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
      if ($currentUser->isAuthenticated()) {
        $storage = $this->entity_type_manager->getStorage('cidoc_entity');
        $query = $storage->getQuery()
          // Query for unpublished entities. Access checking takes care of limiting this to the correct entities.
          ->accessCheck(TRUE)
          ->condition('status', 0)
          ->sort('id');

        \Drupal::moduleHandler()
          ->invokeAll('oiko_app_own_entities_query_alter', [$query]);
        $user_entity = User::load($currentUser->id());

        $view_all = $user_entity->field_show_all_unpublished_entit->value;
        $view_mine = $user_entity->field_show_my_unpublished_entiti->value;
        // User can view all entities.
        if ($view_all && $currentUser->hasPermission('view unpublished cidoc entities')) {
          $query->condition('status', 0);
        }
        elseif ($view_mine && ($currentUser->hasPermission('view own unpublished cidoc entities') || $currentUser->hasPermission('view unpublished cidoc entities'))) {
          $query->condition('status', 0);
          $query->condition('user_id', $currentUser->id());
        }
        else {
          // Add a condition that will never be true, since we do not want to
          // return any entities.
          $query->condition('id', -1);
        }

        $results = $query->execute();

        // Get the entities.
        $entities = $storage->loadMultiple($results);
        foreach ($entities as $entity) {
          /** @var \Drupal\cidoc\Entity\CidocEntity $entity */
          $data['features'] = array_merge($data['features'], $entity->getGeospatialData());
        }
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

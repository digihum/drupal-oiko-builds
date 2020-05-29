<?php

namespace Drupal\oiko_empire\Controller;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class EmpireController.
 *
 * @package Drupal\oiko_leaflet\Controller
 */
class EmpireController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
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
   * Listall.
   *
   * @return string
   *   Return Hello string.
   */
  public function listAll() {
    // Add some lovely locking.
    $lock_name = 'EmpireController::listAll';
    if ($this->lock->acquire($lock_name, 180)) {

      $data = array();
      $storage = $this->entity_type_manager->getStorage('cidoc_entity');
      $query = $storage->getQuery();
      $results = $query
        ->condition('bundle', 'e4_period')
        ->exists('field_empire_outline')
        ->execute();

      $loaded = [];

      if (!empty($results)) {
        $loaded = $storage->loadMultiple($results);
        /** @var CidocEntity $cidoc_entity */
        foreach ($loaded as $cidoc_entity) {
          $geodata = $cidoc_entity->getGeospatialData();
          // Mix in the field values of the actual empire.
          $empire = $cidoc_entity->field_empire_outline->entity;
          foreach ($geodata as &$item) {
            if (isset($item['temporal'])) {
              $item['empire_data'] = array(
                'label' => $empire->label(),
                'id' => $empire->id(),
                'color' => $empire->field_single_color->color,
                'opacity' => $empire->field_single_color->opacity,
              );
              $data[] = $item;
            }
          }
        }
      }

      $response = new CacheableJsonResponse($data);
      foreach ($loaded as $entity) {
        $response->addCacheableDependency($entity);
      }
      $definition = $this->entity_type_manager->getDefinition('cidoc_entity');
      $response->getCacheableMetadata()
        ->addCacheTags($definition->getListCacheTags());

      $this->lock->release($lock_name);
      return $response;
    }
    else {
      // Get the browser to retry in a bit.
      $this->lock->wait($lock_name, 10);
      return new RedirectResponse(Url::fromRoute('oiko_empire.empire_controller_listall')->toString(), 307);
    }
  }

}

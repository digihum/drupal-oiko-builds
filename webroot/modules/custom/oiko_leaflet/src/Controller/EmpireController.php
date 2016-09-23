<?php

namespace Drupal\oiko_leaflet\Controller;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

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
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entity_type_manager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Listall.
   *
   * @return string
   *   Return Hello string.
   */
  public function listAll() {
    $data = array();
    $storage = $this->entity_type_manager->getStorage('cidoc_entity');
    $query = $storage->getQuery();
    $results = $query
      ->condition('bundle', 'e4_period')
      ->exists('field_empire_outline')
      ->execute();

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

    return new CacheableJsonResponse($data);
  }

}

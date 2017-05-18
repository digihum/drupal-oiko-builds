<?php

namespace Drupal\oiko_cidoc\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Config\Entity\Query\QueryFactory;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class GraphingReferencesController.
 *
 * @package Drupal\oiko_cidoc\Controller
 */
class GraphingReferencesController extends ControllerBase {

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
   * Allreferences.
   *
   * @return string
   *   Return Hello string.
   */
  public function allReferences() {
    $references = [];

    // Try to get all references.
    $entity_storage = $this->entity_type_manager->getStorage('cidoc_reference');
    $entities = $entity_storage->loadMultiple();

    /** @var \Drupal\cidoc\Entity\CidocReference $reference */
    foreach ($entities as $reference) {
      $references[] = [
        'domain' => $reference->domain->getValue()[0]['target_id'],
        'property' => $reference->getPropertyLabel(),
        'range' => $reference->range->getValue()[0]['target_id'],
       ];
    }

    $response = new CacheableJsonResponse($references);
    foreach ($entities as $entity) {
      $response->addCacheableDependency($entity);
    }
    $definition = $this->entity_type_manager->getDefinition('cidoc_reference');
    $response->getCacheableMetadata()->addCacheTags($definition->getListCacheTags());
    return $response;
  }

}

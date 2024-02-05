<?php

namespace Drupal\cidoc\Controller;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class CidocEntityFormsController.
 *
 * @package Drupal\cidoc\Controller
 */
class CidocEntityFormsController extends ControllerBase {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The entity bundle storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $bundleStorage;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new CidocEntityFormsController.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $bundle_storage
   *   The entity bundle storage.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityStorageInterface $storage, EntityStorageInterface $bundle_storage, EntityRepositoryInterface $entity_repository) {
    $this->storage = $storage;
    $this->bundleStorage = $bundle_storage;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('cidoc_entity'),
      $entity_type_manager->getStorage('cidoc_entity_bundle'),
      $container->get('entity.repository')
    );
  }

  /**
   * Displays add links for available bundles/types for entity cidoc_entity .
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   A render array for a list of the cidoc_entity bundles/types that can be added or
   *   if there is only one type/bundle defined for the site, the function returns the add page for that bundle/type.
   */
  public function add(Request $request) {
    $bundles = $this->bundleStorage->loadMultiple();
    if ($bundles && count($bundles) == 1) {
      $bundle = reset($bundles);
      return $this->addForm($bundle, $request);
    }
    if (count($bundles) === 0) {
      $t_params = [
        '@link' => Link::fromTextAndUrl($this->t('Go to the class creation page'), Url::fromRoute('entity.cidoc_entity_bundle.add_form'))->toString(),
      ];
      return array(
        '#markup' => $this->t('You have not created any CIDOC entity classes yet. @link to add a new one.', $t_params),
      );
    }

    return \Drupal::formBuilder()->getForm('Drupal\cidoc\Form\CidocEntityAddListBundlesForm');
  }

  /**
   * Presents the creation form for CIDOC entities of a given bundle.
   *
   * @param EntityInterface $cidoc_entity_bundle
   *   The custom bundle to add.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   A form array as expected by drupal_render().
   */
  public function addForm(EntityInterface $cidoc_entity_bundle, Request $request) {
    $entity = $this->storage->create(array(
      'bundle' => $cidoc_entity_bundle->id()
    ));
    return $this->entityFormBuilder()->getForm($entity);
  }

  /**
   * Provides the page title for this controller.
   *
   * @param EntityInterface $cidoc_entity_bundle
   *   The custom bundle/type being added.
   *
   * @return string
   *   The page title.
   */
  public function getAddFormTitle(EntityInterface $cidoc_entity_bundle) {
    return t('Create %label CIDOC entity', array('%label' => $cidoc_entity_bundle->label()));
  }

  /**
   * Provides an edit title callback.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the entity edit page, if an entity was found.
   */
  public function editTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    /** @var CidocEntityInterface $entity */
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t('Edit %bundle: %label', ['%bundle' => $entity->bundleLabel(), '%label' => $entity->label()]);
    }
  }

  /**
   * Determines the entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) The entity, set in
   *   \Drupal\Core\Entity\Enhancer\EntityRouteEnhancer.
   *
   * @return \Drupal\Core\Entity\EntityInterface|NULL
   *   The entity, if it is passed in directly or if the first parameter of the
   *   active route is an entity; otherwise, NULL.
   */
  protected function doGetEntity(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($_entity) {
      $entity = $_entity;
    }
    else {
      // Let's look up in the route object for the name of upcasted values.
      foreach ($route_match->getParameters() as $parameter) {
        if ($parameter instanceof EntityInterface) {
          $entity = $parameter;
          break;
        }
      }
    }
    if (isset($entity)) {
      return $this->entityRepository->getTranslationFromContext($entity);
    }
  }

}

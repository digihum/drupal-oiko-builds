<?php

namespace Drupal\cidoc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class CidocEntityAddController.
 *
 * @package Drupal\cidoc\Controller
 */
class CidocEntityAddController extends ControllerBase {

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

  public function __construct(EntityStorageInterface $storage, EntityStorageInterface $bundle_storage) {
    $this->storage = $storage;
    $this->bundleStorage = $bundle_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('cidoc_entity'),
      $entity_type_manager->getStorage('cidoc_entity_bundle')
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
        '@link' => $this->l($this->t('Go to the class creation page'), Url::fromRoute('entity.cidoc_entity_bundle.add_form')),
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

}

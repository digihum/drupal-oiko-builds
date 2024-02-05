<?php

namespace Drupal\cidoc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class CidocReferenceAddController.
 *
 * @package Drupal\cidoc\Controller
 */
class CidocReferenceAddController extends ControllerBase {

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
  protected $propertyStorage;

  public function __construct(EntityStorageInterface $storage, EntityStorageInterface $property_storage) {
      $this->storage = $storage;
      $this->propertyStorage = $property_storage;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
      /** @var EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = $container->get('entity_type.manager');
      return new static(
        $entity_type_manager->getStorage('cidoc_reference'),
        $entity_type_manager->getStorage('cidoc_property')
      );
    }
    /**
     * Displays add links for available cidoc_properties.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request object.
     *
     * @return array
     *   A render array for a list of cidoc_properties that can be added or if
     *   there is only one defined for the site, the function returns the add
     *   page for that property.
     */
    public function add(Request $request) {
      $properties = $this->propertyStorage->loadMultiple();
      if ($properties && count($properties) == 1) {
        $property = reset($properties);
        return $this->addForm($property, $request);
      }
      if (count($properties) === 0) {
        $t_params = [
          '@link' => Link::fromTextAndUrl($this->t('Go to the property creation page'), Url::fromRoute('entity.cidoc_property.add_form'))->toString(),
        ];
        return array(
          '#markup' => $this->t('You have not created any CIDOC properties yet. @link to add a new one.', $t_params),
        );
      }
      return array(
        '#theme' => 'cidoc_reference_content_add_list',
        '#content' => $properties
      );
    }

    /**
     * Presents the creation form for a cidoc_reference entity for a property.
     *
     * @param EntityInterface $cidoc_property
     *   The custom bundle to add.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request object.
     *
     * @return array
     *   A form array as expected by drupal_render().
     */
    public function addForm(EntityInterface $cidoc_property, Request $request) {
      $entity = $this->storage->create(array(
        'property' => $cidoc_property->id()
      ));
      return $this->entityFormBuilder()->getForm($entity);
    }

    /**
     * Provides the page title for this controller.
     *
     * @param EntityInterface $cidoc_property
     *   The custom bundle/type being added.
     *
     * @return string
     *   The page title.
     */
    public function getAddFormTitle(EntityInterface $cidoc_property) {
      return t('Create %label CIDOC property reference', array('%label' => $cidoc_property->label()));
    }

}

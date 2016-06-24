<?php

namespace Drupal\cidoc\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CidocEntityAddListBundlesForm.
 *
 * @package Drupal\cidoc\Form
 */
class CidocEntityAddListBundlesForm extends FormBase {


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

  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('cidoc_entity'),
      $entity_type_manager->getStorage('cidoc_entity_bundle')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cidoc_entity_add_list_bundles_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['types'] = array(
      '#type' => 'horizontal_tabs',
    );

    $form['types']['main_types'] = array(
      '#type' => 'details',
      '#title' => $this->t('Main types'),
      '#weight' => -1,
      '#group' => 'types',
    );

    $form['types']['main_types']['main'] = array(
      '#type' => 'vertical_tabs',
      '#weight' => -1,
      '#group' => 'main_types',
    );

    $form['types']['auxiliary_types'] = array(
      '#type' => 'details',
      '#title' => $this->t('Auxiliary types'),
      '#group' => 'types',
    );

    $form['types']['auxiliary_types']['auxiliary'] = array(
      '#type' => 'vertical_tabs',
      '#group' => 'auxiliary_types',
    );

    $bundles = $this->bundleStorage->loadMultiple();
    $query = \Drupal::request()->query->all();

    foreach ($bundles as $bundle) {

      $url = new Url(
        'entity.cidoc_entity.add_form',
        array(
          'cidoc_entity_bundle' => $bundle->id(),
        ),
        array(
          'query' => $query,
        )
      );

      if ($bundle->getGroup() != 'main' && !isset($form['types']['auxiliary_types'][$bundle->getGroup()])) {
        $form['types']['auxiliary_types'][$bundle->getGroup()] = array(
          '#type' => 'vertical_tabs',
          '#group' => 'auxiliary_types',
        );
      }

      $form['entities'][$bundle->id()] = array(
        '#type' => 'details',
        '#group' => $bundle->getGroup(),
        '#title' => $bundle->label(),
        '#weight' => $bundle->getWeight(),
      );

      $form['entities'][$bundle->id()]['link'] = Link::fromTextAndUrl($this->t('Create @label', array('@label' => $bundle->label())), $url)->toRenderable();
      $form['entities'][$bundle->id()]['link']['#attributes']['class'][] = 'button';

      if ($description = trim($bundle->getDescription())) {
        $form['entities'][$bundle->id()]['description'] = array(
          '#group' => $bundle->id(),
          '#type' => 'item',
          '#title' => $this->t('Description'),
          '#markup' => $description,
        );
      }

      if ($examples = trim($bundle->getExamples())) {
        $form['entities'][$bundle->id()]['examples'] = array(
          '#group' => $bundle->id(),
          '#type' => 'item',
          '#title' => $this->t('Examples'),
          '#markup' => $examples,
        );
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}

<?php

namespace Drupal\cidoc_sync\Form;

use Drupal\cidoc_spec\DrupalCidocManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use ComputerMinds\CIDOC_CRM\EntityFactory;
use ComputerMinds\CIDOC_CRM\PropertyFactory;

/**
 * Class CidocSyncForm.
 *
 * @package Drupal\cidoc_sync\Form
 */
class CidocSyncForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $bundle_info;
  protected $entity_type_manager;
  protected $crm_entity_factory;
  protected $crm_property_factory;
  protected $crm_drupal_manager;

  public function __construct(
    EntityTypeBundleInfo $bundle_info,
    EntityTypeManager $entity_type_manager,
    EntityFactory $crm_entity_factory,
    PropertyFactory $crm_property_factory,
    DrupalCidocManager $crm_drupal_manager
  ) {
    $this->bundle_info = $bundle_info;
    $this->entity_type_manager = $entity_type_manager;
    $this->crm_entity_factory = $crm_entity_factory;
    $this->crm_property_factory = $crm_property_factory;
    $this->crm_drupal_manager = $crm_drupal_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('cidoc_spec.entity_factory'),
      $container->get('cidoc_spec.property_factory'),
      $container->get('cidoc_spec.drupal_manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cidoc_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['help'] = array(
      '#type' => 'markup',
      '#markup' => t('This will sync all of the entities defined by CIDOC-CRM.'),
    );

    $form['sync_cidoc_crm'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Sync CIDOC-CRM'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = array(
      'title' => $this->t('Syncing the CIDOC-CRM with Drupal'),
      'operations' => array(
      ),
      'file' => drupal_get_path('module', 'cidoc_sync') . '/cidoc_sync.batch_functions.inc',
    );

    // Sync all of the entities that are configured to be.
    $enabled_crm_entities = $this->crm_drupal_manager->getEnabledCRMEntityNames();
    $drupal_bundles = array_keys($this->bundle_info->getBundleInfo('cidoc_entity'));
    foreach ($enabled_crm_entities as $crm_entity_name) {
      if (!in_array($this->crm_drupal_manager->convertCRMNameToDrupalIdentifier($crm_entity_name), $drupal_bundles, TRUE)) {
        // We need to create this CRM entity in Drupal.
        $batch['operations'][] = array(
          'cidoc_sync_create_entity_bundle_callback',
          array(
            $crm_entity_name
          ),
        );
      }
      else {
        // The CIDOC bundle already exists in Drupal, lets sync it.
        $batch['operations'][] = array(
          'cidoc_sync_sync_entity_bundle_callback',
          array(
            $crm_entity_name,
          ),
        );
      }
    }

    // If there are extra bundles, throw a warning.
    foreach ($drupal_bundles as $drupal_bundle_name) {
      if (!in_array($this->crm_drupal_manager->convertDrupalIdentifierToCRMName($drupal_bundle_name), $enabled_crm_entities, TRUE)) {
        // @TODO: Lookup the bundle label.
        drupal_set_message($this->t('Drupal CIDOC Entity Type @drupal_name exists in Drupal but is not enabled', array('@drupal_name' => $drupal_bundle_name)), 'warning');
      }
    }

    if (!empty($batch['operations'])) {
      batch_set($batch);
    }
    else {
      drupal_set_message($this->t('Sync complete with nothing to do.'));
    }

    // Check properties, which we sync to relations.



  }
}

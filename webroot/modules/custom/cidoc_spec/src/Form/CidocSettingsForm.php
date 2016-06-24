<?php

namespace Drupal\cidoc_spec\Form;

use Drupal\cidoc_spec\DrupalCidocManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use ComputerMinds\CIDOC_CRM\EntityFactory;
use ComputerMinds\CIDOC_CRM\PropertyFactory;

/**
 * Class CidocSettingsForm.
 *
 * @package Drupal\cidoc_spec\Form
 */
class CidocSettingsForm extends ConfigFormBase {

  /**
   * ComputerMinds\CIDOC_CRM\EntityFactory definition.
   *
   * @var \ComputerMinds\CIDOC_CRM\EntityFactory
   */
  protected $cidoc_spec_entity_factory;

  /**
   * \ComputerMinds\CIDOC_CRM\PropertyFactory definition.
   *
   * @var \ComputerMinds\CIDOC_CRM\PropertyFactory
   */
  protected $cidoc_spec_property_factory;

  /**
   *
   *
   * @var \Drupal\cidoc_spec\DrupalCidocManager
   */
  protected $cidoc_drupal_manager;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityFactory $cidoc_spec_entity_factory,
    PropertyFactory $cidoc_spec_property_factory,
    DrupalCidocManager $cidoc_drupal_manager
    ) {
    parent::__construct($config_factory);
    $this->cidoc_spec_entity_factory = $cidoc_spec_entity_factory;
    $this->cidoc_spec_property_factory = $cidoc_spec_property_factory;
    $this->cidoc_drupal_manager = $cidoc_drupal_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cidoc_spec.entity_factory'),
      $container->get('cidoc_spec.property_factory'),
      $container->get('cidoc_spec.drupal_manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cidoc_spec.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cidoc_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cidoc_spec.settings');

    $form['enabled_entities'] = array(
      '#title' => $this->t('Enabled entities'),
      '#type' => 'checkboxes',
      '#default_value' => $config->get('enabled_entities'),
      '#options' => $this->cidoc_drupal_manager->getCRMEntityNamesAndLabels(),
      '#description' => $this->t('Select which CRM entities you want to use in this Drupal site, if none are selected, all will be enabled.'),
    );

    $form['enabled_properties'] = array(
      '#title' => $this->t('Enabled properties'),
      '#type' => 'checkboxes',
      '#default_value' => $config->get('enabled_properties'),
      '#options' => $this->cidoc_drupal_manager->getCRMPropertyNamesAndLabels(),
      '#description' => $this->t('Select which CRM properties you want to use in this Drupal site, if none are selected, all will be enabled.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('cidoc_spec.settings')
      ->set('enabled_entities', array_values(array_filter($form_state->getValue('enabled_entities'))))
      ->set('enabled_properties', array_values(array_filter($form_state->getValue('enabled_properties'))))
      ->save();
  }

}

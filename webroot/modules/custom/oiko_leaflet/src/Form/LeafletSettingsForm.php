<?php

namespace Drupal\oiko_leaflet\Form;

use Drupal\cidoc\Entity\CidocEntityBundle;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\oiko_leaflet\ItemColorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CidocSettingsForm.
 *
 * @package Drupal\cidoc_spec\Form
 */
class LeafletSettingsForm extends ConfigFormBase {

  /**
   * The cidoc entity type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $cidocEntityTypeStorage;

  /**
   * LeafletSettingsForm constructor.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityStorageInterface $cidocEntityTypeStorage
    ) {
    parent::__construct($config_factory);
    $this->cidocEntityTypeStorage = $cidocEntityTypeStorage;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')->getStorage('cidoc_entity_bundle')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'oiko_leaflet.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'leaflet_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('oiko_leaflet.settings');

    $form['url_template'] = array(
      '#title' => $this->t('URL Template'),
      '#type' => 'textfield',
      '#size' => 120,
      '#maxlength' => 500,
      '#default_value' => $config->get('url_template'),
      '#required' => TRUE,
      '#description' => $this->t('e.g. //api.tiles.mapbox.com/v4/isawnyu.map-knmctlkh/{z}/{x}/{y}.png?access_toke=pk.eyJ1IjoiZGFydGhzdGV2ZW4iLCJhIjoiY2lweTFzOWJxMDA4ZWh0bTJlb28xc3R6NyJ9.94HMG5U3tZiei13s7Rqaog'),
    );

    $form['attribution'] = array(
      '#title' => $this->t('Attribution'),
      '#type' => 'textfield',
      '#size' => 120,
      '#maxlength' => 300,
      '#default_value' => $config->get('attribution'),
      '#required' => TRUE,
      '#description' => $this->t('Powered by <a href="http://leafletjs.com/">Leaflet</a> and <a href="https://www.mapbox.com/">Mapbox</a>. Map base by <a title="Ancient World Mapping Center (UNC-CH)" href="http://awmc.unc.edu">AWMC</a>, 2014 (cc-by-nc).'),
    );

    $form['color_system'] = array(
      '#title' => $this->t('Color system'),
      '#type' => 'radios',
      '#default_value' => $config->get('color_system'),
      '#required' => TRUE,
      '#options' => [
        ItemColorInterface::COLOR_SYSTEM_PRIMARY_HISTORICAL_SIGNIFICANCE => $this->t('Primary Historical Significance'),
        ItemColorInterface::COLOR_SYSTEM_CIDOC_ENTITY_CLASS => $this->t('CIDOC Entity Class'),
      ],
      '#description' => $this->t('Various interface elements are colored automatically by the system. You may select what property of the entity data they are coloured by.')
    );

    // Allow specifying the colors of the CIDOC entites.
    $form['cidoc_classes'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('CIDOC Entity class colors'),
      '#tree' => TRUE,
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="color_system"]' => ['value' => ItemColorInterface::COLOR_SYSTEM_CIDOC_ENTITY_CLASS],
        ],
      ],
    );

    $cidoc_classes = $this->cidocEntityTypeStorage->loadMultiple(NULL);
    uasort($cidoc_classes, ['\Drupal\cidoc\Entity\CidocEntityBundle', 'sort']);
    foreach ($cidoc_classes as $id => $cidoc_class) {
      $form['cidoc_classes'][$id] = array(
        '#type' => 'select',
        '#title' => $cidoc_class->label(),
        '#default_value' => $cidoc_class->getThirdPartySetting('oiko_leaflet', 'item_color', ''),
        '#options' => \Drupal\oiko_leaflet\ItemColorInterface::ITEM_COLORS,
        '#empty_option' => t('Default (blue)'),
        '#description' => t('Optionally specify the color markers and interface elements should be if they are @type', ['@type' => $cidoc_class->label()]),
      );
    }

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

    $this->config('oiko_leaflet.settings')
      ->set('url_template', $form_state->getValue('url_template'))
      ->set('attribution', $form_state->getValue('attribution'))
      ->set('color_system', $form_state->getValue('color_system'))
      ->save();

    // Now save all the colors.
    $all_classes = $cidoc_classes = $this->cidocEntityTypeStorage->loadMultiple(NULL);
    foreach ($form_state->getValue('cidoc_classes') as $class => $color) {
      if (isset($all_classes[$class])) {
        if (!empty($color)) {
          $all_classes[$class]->setThirdPartySetting('oiko_leaflet', 'item_color', $color);
        }
        else {
          $all_classes[$class]->unsetThirdPartySetting('oiko_leaflet', 'item_color');
        }
        $all_classes[$class]->save();
      }
    }
  }

}

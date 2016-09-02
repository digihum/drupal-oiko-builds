<?php

namespace Drupal\cidoc\Form;

use Drupal\cidoc\Entity\CidocEntityBundle;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CidocEntityBundleForm.
 *
 * @package Drupal\cidoc\Form
 */
class CidocEntityBundleForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var CidocEntityBundle $cidoc_entity_bundle */
    $cidoc_entity_bundle = $this->entity;
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $cidoc_entity_bundle->label(),
      '#description' => $this->t('Label for the CIDOC entity class.'),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $cidoc_entity_bundle->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\cidoc\Entity\CidocEntityBundle::load',
      ),
      '#disabled' => !$cidoc_entity_bundle->isNew(),
    );

    $form['crm_entity'] = array(
      '#title' => $this->t('CIDOC-CRM Entity'),
      '#type' => 'textfield',
      '#default_value' => $cidoc_entity_bundle->getCRMEntityName(),
      '#disabled' => !$cidoc_entity_bundle->isNew(),
    );

    $form['group'] = array(
      '#title' => t('Group'),
      '#type' => 'select',
      '#default_value' => $cidoc_entity_bundle->getGroup(),
      '#options' => array(
        'main' => t('Main group'),
        'auxiliary' => t('Auxiliary group'),
      ),
    );

    $form['weight'] = array(
      '#title' => t('Weight'),
      '#type' => 'weight',
      '#delta' => 25,
      '#default_value' => $cidoc_entity_bundle->getWeight(),
      '#description' => t('This will determine the order of bundles on the add page.'),
    );

    $form['description'] = array(
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#default_value' => $cidoc_entity_bundle->getDescription(),
      '#description' => t('This text will be displayed on the <em>Add CIDOC entity</em> page.'),
    );

    $form['examples'] = array(
      '#title' => t('Examples'),
      '#type' => 'textarea',
      '#default_value' => $cidoc_entity_bundle->getExamples(),
      '#description' => t('This text will be displayed on the <em>Add CIDOC entity</em> page.'),
    );

    $form['geoserializers'] = array(
      '#title' => t('Geoserializers'),
      '#type' => 'checkboxes',
      '#options' => $cidoc_entity_bundle->getGeoserializerOptions(),
      '#default_value' => $cidoc_entity_bundle->getGeoserializers(),
      '#description' => t('Select the plugins that will transform an entity of this type into geospatial data for rendering on maps.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $cidoc_entity_bundle = $this->entity;
    $status = $cidoc_entity_bundle->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label CIDOC entity class.', [
          '%label' => $cidoc_entity_bundle->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label CIDOC entity class.', [
          '%label' => $cidoc_entity_bundle->label(),
        ]));
    }
    $form_state->setRedirectUrl($cidoc_entity_bundle->urlInfo('collection'));
  }

}

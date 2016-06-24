<?php

namespace Drupal\cidoc\Form;

use Drupal\cidoc\Entity\CidocProperty;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CidocPropertyForm.
 *
 * @package Drupal\cidoc\Form
 */
class CidocPropertyForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#attached'] = array(
      'library' => array('cidoc/drupal.cidoc_properties'),
    );

    /** @var \Drupal\cidoc\Entity\CidocProperty $cidoc_property */
    $cidoc_property = $this->entity;
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $cidoc_property->label(),
      '#description' => $this->t("Label for the CIDOC property."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $cidoc_property->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\cidoc\Entity\CidocProperty::load',
      ),
      '#disabled' => !$cidoc_property->isNew(),
    );

    $form['labels']['reverse_label'] = array(
      '#type' => 'textfield',
      '#size' => 40,
      '#title' => t('Reverse label'),
      '#description'   => $this->t('Reverse label of the property. This is used when you need to display the reverse direction (ie. from the range entity to the domain entity) of a property reference. If this is not supplied, the normal label is used.'),
      '#default_value' => $cidoc_property->reverse_label,
    );

    // See if data already exists for this property. If so, prevent changes to
    // the endpoint settings.
    $has_data = (bool) \Drupal::entityQuery('cidoc_reference')
      ->condition('property', $cidoc_property->id())
      ->count()
      ->execute();

    $options_bundles = [];
    foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo('cidoc_entity') as $bundle_id => $bundle) {
      $options_bundles[$bundle_id] = $bundle['label'];
    }

    $form['endpoints'] = array(
      '#type' => 'fieldset',
      '#attributes' => array(
        'class' => array('cidoc-property-form-table'),
      ),
      '#suffix' => '<div class="clearfix"></div>',
      '#title' => $this->t('Endpoint restrictions'),
      '#field_prefix' => '<div class="description">' . $this->t('Restrict which CIDOC entity classes are allowed for the source & target endpoints on this property. Not selecting any will permit all classes, including any created in the future, on the endpoint(s).') . '</div>',
    );
    $domain_bundles = $cidoc_property->domain_bundles;
    if (in_array('*', $domain_bundles, TRUE)) {
      $domain_bundles = array();
    }
    $form['endpoints']['domain_bundles'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Domain classes'),
      '#options' => $options_bundles,
      '#default_value' => $domain_bundles,
      '#disabled' => $has_data,
      '#attributes' => array(
        'class' => array('cidoc-property-form-composite-column'),
      ),
    );
    if ($has_data) {
      $form['endpoints']['domain_bundles']['#prefix'] = '<div class="messages messages--error">' . $this->t('There is data for this property in the database. The endpoint class restriction settings can no longer be changed.') . '</div>';
    }
    $range_bundles = $cidoc_property->range_bundles;
    if (in_array('*', $range_bundles, TRUE)) {
      $range_bundles = array();
    }
    $form['endpoints']['range_bundles'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Range classes'),
      '#options' => $options_bundles,
      '#default_value' => $range_bundles,
      '#disabled' => $has_data,
      '#attributes' => array(
        'class' => array('cidoc-property-form-composite-column'),
      ),
    );

    $form['endpoints']['editability'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Editable on endpoint entities'),
      '#options' => array(
        'domain' => $this->t('Domain'),
        'range' => $this->t('Range'),
      ),
      '#description' => $this->t('Select whether this property can be edited directly on the entities that it references from and/or to.'),
      '#default_value' => array(),
      '#attributes' => array(
        'class' => array('cidoc-property-form-items-columns'),
      ),
    );
    foreach ($form['endpoints']['editability']['#options'] as $endpoint => $endpoint_label) {
      if ($cidoc_property->isEditable($endpoint)) {
        $form['endpoints']['editability']['#default_value'][] = $endpoint;
      }
    }

    $form['#entity_builders']['update_status'] = [$this, 'cleanEndpoints'];

    return $form;
  }

  /**
   * Entity builder cleaning up the endpoint values.
   *
   * @param string $entity_type_id
   *   The entity type identifier.
   * @param \Drupal\cidoc\Entity\CidocProperty $entity
   *   The entity updated with the submitted values.
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\node\NodeForm::updateStatus()
   */
  function cleanEndpoints($entity_type_id, CidocProperty $entity, array $form, FormStateInterface $form_state) {
    $ranges = array_filter($form_state->getValue('domain_bundles'));
    if (empty($ranges)) {
      $ranges = ['*'];
    }

    $entity->domain_bundles = $ranges;

    $ranges = array_filter($form_state->getValue('range_bundles'));
    if (empty($ranges)) {
      $ranges = ['*'];
    }

    $entity->range_bundles = $ranges;

    // Get to an array of allowed endpoint strings mapped to boolean values.
    $entity->editability = array_map('boolval', $form_state->getValue('editability'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $cidoc_property = $this->entity;
    $status = $cidoc_property->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label CIDOC property.', [
          '%label' => $cidoc_property->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Updated the %label CIDOC property.', [
          '%label' => $cidoc_property->label(),
        ]));
    }
    $form_state->setRedirectUrl($cidoc_property->toUrl('collection'));
  }

}

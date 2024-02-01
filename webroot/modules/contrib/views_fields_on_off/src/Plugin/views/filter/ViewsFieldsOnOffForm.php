<?php

namespace Drupal\views_fields_on_off\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Provides a handler that adds the form for Fields On/Off.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_fields_on_off_form")
 */
class ViewsFieldsOnOffForm extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isExposed() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    $all_fields = [];
    foreach ($this->displayHandler->getHandlers('field') as $id => $handler) {
      if ($label = $handler->label()) {
        $all_fields[$id] = $label;
      }
      else {
        $all_fields[$id] = $handler->adminLabel();
      }
    }
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $this->valueOptions = $all_fields;

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['bypass_hook_views_pre_view'] = ['default' => FALSE];
    $options['exposed_select_type'] = ['default' => 'select'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['bypass_hook_views_pre_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass hook_views_pre_view()'),
      '#description' => $this->t('Bypassing hook_views_pre_view() means the module will instead reply on hook_preprocess_views_view(),in which case the filter plugin has been fully initialized and one can call a function on the plugin class instead of handling everything in the hook. Because modules like Charts rely on Views Fields On/Off to work earlier in the rendering process, this option is left unchecked by default.'),
      '#default_value' => $this->options['bypass_hook_views_pre_view'],
    ];

    $form['exposed_select_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Exposed selection field type'),
      '#description' => t('Pick what HTML element you want used in your exposed form.'),
      '#options' => [
        'checkboxes' => $this->t('Checkboxes'),
        'radios' => $this->t('Radios'),
        'select' => $this->t('Single select'),
        'multi_select' => $this->t('Multiple select'),
      ],
      '#default_value' => $this->options['exposed_select_type'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    $filter_id = $this->options['expose']['identifier'] ?? NULL;
    if (empty($filter_id)) {
      return;
    }
    $label = $this->options['label'] ?? '';
    $options = array_intersect_key($this->getValueOptions(), $this->options['value']);
    $form[$filter_id] = [
      '#type' => $this->options['exposed_select_type'],
      '#title' => $this->t('@value', [
        '@value' => $label,
      ]),
      '#options' => $options,
      '#required' => !empty($this->options['expose']['required']),
    ];
    if ($this->options['exposed_select_type'] == 'multi_select') {
      $form[$filter_id]['#type'] = 'select';
      $form[$filter_id]['#multiple'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This is not a real field, and it only affects the query by excluding
    // fields from the display. But Views won't render if the query()
    // method is not present. This doesn't do anything, but it has to be here.
    // This function is a void, so it doesn't return anything.
  }

  /**
   * Theme preprocess function.
   *
   * @param array $variables
   *   Theme variables to be rendered.
   *
   * @see views_fields_on_off_preprocess_views_view()
   */
  public function preprocess(array &$variables) {
    $field_options = $this->view->display_handler->getOption('fields');
    foreach ($field_options as $key => &$field) {
      if (isset($this->value[$key]) || in_array($key, $this->value)) {
        continue;
      }
      unset($this->view->field[$key]);
    }
  }
}

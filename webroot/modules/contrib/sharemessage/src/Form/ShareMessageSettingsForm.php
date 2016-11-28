<?php

namespace Drupal\sharemessage\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a form that configures Share Message global settings.
 */
class ShareMessageSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'sharemessage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'sharemessage_settings_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {

    $config = $this->config('sharemessage.settings');

    // Global setting.
    $form['message_enforcement'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow to enforce Share Messages'),
      '#description' => t('This will enforce loading of a Share Message if the ?smid argument is present in an URL. If something else on your site is using this argument, disable this option.'),
      '#default_value' => $config->get('message_enforcement'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('sharemessage.settings')
      ->set('message_enforcement', $form_state->getValue('message_enforcement'))
      ->save();
  }

}

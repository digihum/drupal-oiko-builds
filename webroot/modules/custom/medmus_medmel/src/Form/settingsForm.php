<?php

namespace Drupal\medmus_medmel\Form;

use Drupal\Core\Form\FormStateInterface;

class settingsForm extends \Drupal\Core\Form\ConfigFormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'medmus_medmel.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'medmus_medmel_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $directory = \Drupal::service('file_system')->realpath("private://");

    $form['fetchUrl'] = [
      '#type' => 'url',
      '#title' => $this->t('Remote fetch URL'),
      '#default_value' => $config->get('fetchUrl'),
      '#description' => $this->t('Location to fetch Medmel IDs from.'),
    ];

    $form['iframeHeight'] = [
      '#type' => 'number',
      '#title' => $this->t('Iframe Height'),
      '#default_value' => $config->get('iframeHeight'),
      '#step' => 1,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('The height of the iframe embed URL, in pixels.'),
    ];

    $form['embedUrl'] = [
      '#type' => 'url',
      '#title' => $this->t('Single embed URL'),
      '#default_value' => $config->get('embedUrl'),
      '#description' => $this->t('This will be used for the iframe embed URL when there is a single related piece of music.'),
    ];

    $form['multipleEmbedUrl'] = [
      '#type' => 'url',
      '#title' => $this->t('Multiple embed URL'),
      '#default_value' => $config->get('multipleEmbedUrl'),
      '#description' => $this->t('This will be used for the iframe embed URL when there are multiple related pieces of music.'),
    ];

    $form['certName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Certificate name'),
      '#default_value' => $config->get('certName'),
      '#description' => $this->t('Name of the uploaded .pem file.'),
    ];

    $form['cert'] = [
      '#type' => 'managed_file',
      '#name' => 'custom_certificates',
      '#title' => $this->t('Certificate'),
      '#size' => 100,
      '#default_value' => $config->get('defaultCertificate'),
      '#description' => $this->t('Here you can upload a certificate in case the curl request throws an Error 60.'),
      '#upload_location' => 'private://certificates',
      '#upload_validators' => array(
        'file_validate_extensions' => array('pem'),
        'file_validate_size' => array(1024*1024)
      )
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('fetchUrl', $form_state->getValue('fetchUrl'))
      ->set('iframeHeight', $form_state->getValue('iframeHeight'))
      ->set('embedUrl', $form_state->getValue('embedUrl'))
      ->set('multipleEmbedUrl', $form_state->getValue('multipleEmbedUrl'))
      ->set('certName', $form_state->getValue('certName'))
      ->set('cert', $form_state->getValue('cert'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}

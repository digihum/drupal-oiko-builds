<?php

namespace Drupal\sharemessage\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sharemessage\Plugin\sharemessage\Sharrre;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a form that configures Share Message settings.
 */
class SharrreSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'sharemessage.sharrre',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'sharemessage_sharrre_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $form = parent::buildForm($form, $form_state);

    $sharrre_config = $this->config('sharemessage.sharrre');

    // Check if the library configuration is valid.
    if ($check_form = Sharrre::checkConfiguration(TRUE)) {
      $form['check'] = $check_form;
    }

    $url = 'http://jster.net/library/sharrre';
    $form['library_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Remote URL for Sharrre Library (minimized version)'),
      '#description' => $this->t('Set the URL for a Web-Hosted minimized version of Sharrre library (jquery.sharrre.min.js with the leading slashes), or leave empty if using the library locally. You can retrieve the library from <a href=":url">Sharrre CDN</a>.', array(
        ':url' => $url,
      )),
      '#default_value' => $sharrre_config->get('library_url'),
    );

    $form['default_services'] = array(
      '#title' => t('Default visible services'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => array(
        'googlePlus' => t('Google+'),
        'facebook' => t('Facebook'),
        'twitter' => t('Twitter'),
        'digg' => t('Digg'),
        'delicious' => t('Delicious'),
        'stumbleupon' => t('StumpleUpon'),
        'linkedin' => t('Linkedin'),
        'pinterest' => t('Pinterest'),
      ),
      '#default_value' => $sharrre_config->get('services'),
      '#size' => 10,
    );

    $form['shorter_total'] = array(
      '#type' => 'checkbox',
      '#title' => t('Format number like 1.2k or 5M'),
      '#default_value' => $sharrre_config->get('shorter_total'),
    );

    $form['enable_hover'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow the sharing buttons'),
      '#default_value' => $sharrre_config->get('enable_hover'),
    );

    $form['enable_counter'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable the total counter'),
      '#default_value' => $sharrre_config->get('enable_counter'),
    );

    $form['enable_tracking'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow tracking social interaction with Google Analytics'),
      '#default_value' => $sharrre_config->get('enable_tracking'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Check if the name of the library in the remote URL is as expected.
    $url = $form_state->getValue('library_url');
    $sharrre_cdn_url = 'http://jster.net/library/sharrre';
    if (!empty($url) && parse_url($url, PHP_URL_HOST) && parse_url($url, PHP_URL_PATH)) {
      $js = pathinfo($url, PATHINFO_BASENAME);
      if (!preg_match('/^jquery\.sharrre-[\d.]*\.min\.js$/', $js)) {
        drupal_set_message(t('The naming of the library is unexpected. Double check that this is the real Sharrre library. The URL for the minimized version of the library can be found on <a href=":url">Sharrre CDN</a>.', [':url' => $sharrre_cdn_url]), 'warning');
      }
    }
    elseif (!empty($url)) {
      drupal_set_message(t('The remote URL is unexpected. Please, provide the correct URL to the minimized version of the library found on <a href=":url">Sharrre CDN</a>.', [':url' => $sharrre_cdn_url]), 'error');
    }

    // If the profile id changes then we need to rebuild the library cache.
    Cache::invalidateTags(['library_info']);

    $this->config('sharemessage.sharrre')
      ->set('services', $form_state->getValue('default_services'))
      ->set('library_url', $form_state->getValue('library_url'))
      ->set('shorter_total', $form_state->getValue('shorter_total'))
      ->set('enable_hover', $form_state->getValue('enable_hover'))
      ->set('enable_counter', $form_state->getValue('enable_counter'))
      ->set('enable_tracking', $form_state->getValue('enable_tracking'))
      ->save();
  }

}

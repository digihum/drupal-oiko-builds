<?php

declare(strict_types = 1);

namespace Drupal\medmus_cidoc\Plugin\ClientAuthorization;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginBase;

/**
 * Provides Header based client authorization.
 *
 * @ClientAuthorization(
 *   id = "basic_auth_with_header",
 *   label = @Translation("Basic Auth With Header"),
 * )
 */
class BasicAuthWithHeader extends ClientAuthorizationPluginBase {

  /**
   * {@inheritdoc}
   */
  public function checkIfAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient($url) {
    $credentials = $this->keyService->getCredentials($this);
    return $this->httpClientFactory->fromOptions([
      'base_uri' => $url . '/',
      'cookies' => TRUE,
      'allow_redirects' => TRUE,
      'auth' => [
        $credentials['username'],
        $credentials['password'],
      ],
      'headers' => [
        $credentials['header_name'] => $credentials['header_value'],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiClient($url) {
    $credentials = $this->keyService->getCredentials($this);
    return $this->httpClientFactory->fromOptions([
      'base_uri' => $url . '/',
      'auth' => [
        $credentials['username'],
        $credentials['password'],
      ],
      'headers' => [
        'Content-type' => 'application/vnd.api+json',
        $credentials['header_name'] => $credentials['header_value'],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $credentials = $this->keyService->getCredentials($this);

    $form['entity_share']['username'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Username'),
      '#default_value' => $credentials['username'] ?? '',
    ];

    $form['entity_share']['password'] = [
      '#type' => 'password',
      '#required' => TRUE,
      '#title' => $this->t('Password'),
      '#default_value' => $credentials['password'] ?? '',
    ];

    $form['entity_share']['header_name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Header Name'),
      '#default_value' => $credentials['header_name'] ?? '',
    ];

    $form['entity_share']['header_value'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Header Value'),
      '#default_value' => $credentials['header_value'] ?? '',
    ];
    if ($this->keyService->additionalProviders()) {
      $this->expandedProviderOptions($form);
      $form['key']['id']['#key_filters'] = ['type' => 'entity_share_basic_auth_with_header'];
      $form['key']['id']['#description'] = $this->t('Select the key you have configured to hold the Header data.');
    }

    return $form;
  }

}

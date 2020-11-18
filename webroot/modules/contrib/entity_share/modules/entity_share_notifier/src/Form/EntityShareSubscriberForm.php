<?php

declare(strict_types = 1);

namespace Drupal\entity_share_notifier\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityShareSubscriberForm.
 */
class EntityShareSubscriberForm extends EntityForm {

  /**
   * Injected plugin service.
   *
   * @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginManager
   */
  protected $authPluginManager;

  /**
   * The currently configured auth plugin.
   *
   * @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface
   */
  protected $authPlugin;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->authPluginManager = $container->get('plugin.manager.entity_share_client_authorization');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity_share_subscriber = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity_share_subscriber->label(),
      '#description' => $this->t('Label for the Entity share subscriber.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_share_subscriber->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_share_notifier\Entity\EntityShareSubscriber::load',
      ],
      '#disabled' => !$entity_share_subscriber->isNew(),
    ];

    $form['subscriber_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#maxlength' => 255,
      '#description' => $this->t('The subscriber URL. Example: http://example.com'),
      '#default_value' => $entity_share_subscriber->get('subscriber_url'),
      '#required' => TRUE,
    ];

    $this->addAuthOptions($form, $form_state);

    $form['remote_config_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remote Config ID'),
      '#default_value' => $entity_share_subscriber->get('remote_config_id'),
      '#description' => $this->t('The remote config ID used on the subscriber website to pull this server.'),
      '#required' => TRUE,
    ];

    $form['remote_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remote ID'),
      '#default_value' => $entity_share_subscriber->get('remote_id'),
      '#description' => $this->t('The remote ID used on the subscriber website to pull this server.'),
      '#required' => TRUE,
    ];

    $channel_ids = $entity_share_subscriber->get('channel_ids');
    $form['channel_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Channel IDs'),
      '#description' => $this->t('The list of channel this subscriber will be notified on.'),
      '#options' => $this->getChannelsOptions(),
      '#default_value' => !is_null($channel_ids) ? $channel_ids : [],
    ];

    return $form;
  }

  /**
   * Helper function to build the authorization options in the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function addAuthOptions(array &$form, FormStateInterface $form_state) {
    $options = [];
    $plugins = [];
    $commonUuid = '';
    if ($this->getAuthPlugin()) {
      $options[$this->authPlugin->getPluginId()] = $this->authPlugin->getLabel();
      $plugins[$this->authPlugin->getPluginId()] = $this->authPlugin;
      // Ensure all plugins will have the same uuid in the configuration to
      // avoid duplication of entries in the key value store.
      $existing_plugin_configuration = $this->authPlugin->getConfiguration();
      $commonUuid = $existing_plugin_configuration['uuid'];
    }
    $availablePlugins = $this->authPluginManager->getAvailablePlugins($commonUuid);
    foreach ($availablePlugins as $id => $plugin) {
      if (empty($options[$id])) {
        // This plugin type was not previously set as an option.
        $options[$id] = $plugin->getLabel();
        $plugins[$id] = $plugin;
      }
    }
    // Do we have a value?
    $selected = $form_state->getValue('pid');
    if (!empty($selected)) {
      $selectedPlugin = $plugins[$selected];
    }
    elseif (!empty($this->authPlugin)) {
      // Is a plugin previously stored?
      $selectedPlugin = $this->authPlugin;
    }
    else {
      // Fallback: take the first option.
      $selectedPlugin = reset($plugins);
    }
    $form['auth'] = [
      '#type' => 'container',
      '#plugins' => $plugins,
      'pid' => [
        '#type' => 'radios',
        '#title' => $this->t('Authorization methods'),
        '#options' => $options,
        '#default_value' => $selectedPlugin->getPluginId(),
        '#ajax' => [
          'wrapper' => 'plugin-form-ajax-container',
          'callback' => [get_class($this), 'ajaxPluginForm'],
        ],
      ],
      'data' => [],
    ];
    $subformState = SubformState::createForSubform($form['auth']['data'], $form, $form_state);
    $form['auth']['data'] = $selectedPlugin->buildConfigurationForm($form['auth']['data'], $subformState);
    $form['auth']['data']['#tree'] = TRUE;
    $form['auth']['data']['#prefix'] = '<div id="plugin-form-ajax-container">';
    $form['auth']['data']['#suffix'] = '</div>';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $selectedPlugin = $this->getSelectedPlugin($form, $form_state);
    $subformState = SubformState::createForSubform($form['auth']['data'], $form, $form_state);
    // Store the remote entity in case the plugin submission needs its data.
    $subformState->set('remote', $this->entity);
    $selectedPlugin->submitConfigurationForm($form['auth']['data'], $subformState);
  }

  /**
   * Callback function to return the credentials portion of the form.
   *
   * @param array $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   *
   * @return array
   *   A portion of the render array.
   */
  public static function ajaxPluginForm(array $form, FormStateInterface $formState) {
    return $form['auth']['data'];
  }

  /**
   * Helper method to instantiate plugin from this entity.
   *
   * @return bool
   *   The Remote entity has a plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getAuthPlugin() {
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
    $remote = $this->entity;
    $plugin = $remote->getAuthPlugin();
    if ($plugin instanceof ClientAuthorizationInterface) {
      $this->authPlugin = $plugin;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper method to get selected plugin from the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface
   *   The selected plugin.
   */
  protected function getSelectedPlugin(
    array &$form,
    FormStateInterface $form_state) {
    $authPluginId = $form_state->getValue('pid');
    $plugins = $form['auth']['#plugins'];
    /** @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface $selectedPlugin */
    $selectedPlugin = $plugins[$authPluginId];
    return $selectedPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate URL.
    if (!UrlHelper::isValid($form_state->getValue('subscriber_url'), TRUE)) {
      $form_state->setError($form['subscriber_url'], $this->t('Invalid URL.'));
    }
    $selectedPlugin = $this->getSelectedPlugin($form, $form_state);
    if ($selectedPlugin instanceof PluginFormInterface) {
      $subformState = SubformState::createForSubform($form['auth']['data'], $form, $form_state);
      $selectedPlugin->validateConfigurationForm($form['auth']['data'], $subformState);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_notifier\Entity\EntityShareSubscriberInterface $entity_share_subscriber */
    $entity_share_subscriber = $this->entity;

    $channel_ids = array_filter($form_state->getValue('channel_ids'));
    $entity_share_subscriber->set('channel_ids', $channel_ids);

    if (!empty($form['auth']['#plugins'])) {
      $selectedPlugin = $this->getSelectedPlugin($form, $form_state);
      $entity_share_subscriber->mergePluginConfig($selectedPlugin);
    }

    $status = $entity_share_subscriber->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Entity share subscriber.', [
          '%label' => $entity_share_subscriber->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Entity share subscriber.', [
          '%label' => $entity_share_subscriber->label(),
        ]));
    }
    $form_state->setRedirectUrl($entity_share_subscriber->toUrl('collection'));
  }

  /**
   * Get channels.
   *
   * @return array
   *   An array of options.
   */
  protected function getChannelsOptions() {
    $channel_options = [];

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager
      ->getStorage('channel')
      ->loadMultiple();
    foreach ($channels as $channel) {
      $channel_options[$channel->id()] = $channel->label();
    }

    return $channel_options;
  }

}

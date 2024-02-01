<?php

declare(strict_types = 1);

namespace Drupal\entity_share_notifier\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class EntityShareSubscriberForm.
 */
class EntityShareSubscriberForm extends EntityForm {

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

    $form['basic_auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic Auth'),
    ];

    $form['basic_auth']['basic_auth_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $entity_share_subscriber->get('basic_auth_username'),
      '#required' => TRUE,
    ];

    $form['basic_auth']['basic_auth_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate URL.
    if (!UrlHelper::isValid($form_state->getValue('subscriber_url'), TRUE)) {
      $form_state->setError($form['subscriber_url'], $this->t('Invalid URL.'));
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

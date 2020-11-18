<?php

declare(strict_types = 1);

namespace Drupal\entity_share_notifier\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;

/**
 * Defines the Entity share subscriber entity.
 *
 * @ConfigEntityType(
 *   id = "entity_share_subscriber",
 *   label = @Translation("Entity share subscriber"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\entity_share_notifier\EntityShareSubscriberListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_share_notifier\Form\EntityShareSubscriberForm",
 *       "edit" = "Drupal\entity_share_notifier\Form\EntityShareSubscriberForm",
 *       "delete" = "Drupal\entity_share_notifier\Form\EntityShareSubscriberDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\entity_share_notifier\EntityShareSubscriberHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "entity_share_subscriber",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "subscriber_url",
 *     "auth",
 *     "remote_id",
 *     "remote_config_id",
 *     "channel_ids",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity_share/entity_share_subscriber/{entity_share_subscriber}",
 *     "add-form" = "/admin/config/services/entity_share/entity_share_subscriber/add",
 *     "edit-form" = "/admin/config/services/entity_share/entity_share_subscriber/{entity_share_subscriber}/edit",
 *     "delete-form" = "/admin/config/services/entity_share/entity_share_subscriber/{entity_share_subscriber}/delete",
 *     "collection" = "/admin/config/services/entity_share/entity_share_subscriber"
 *   }
 * )
 */
class EntityShareSubscriber extends ConfigEntityBase implements EntityShareSubscriberInterface {

  /**
   * The Entity share subscriber ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Entity share subscriber label.
   *
   * @var string
   */
  protected $label;

  /**
   * The subscriber URL.
   *
   * @var string
   */
  protected $subscriber_url;

  /**
   * An associative array of the authorization plugin data.
   *
   * @var array
   */
  protected $auth;

  /**
   * The remote ID used on the subscriber website to pull this server.
   *
   * @var string
   */
  protected $remote_id;

  /**
   * The remote  config ID used on the subscriber website to pull this server.
   *
   * @var string
   */
  protected $remote_config_id;

  /**
   * The list of channel IDs this subscriber will be notified on.
   *
   * @var string[]
   */
  protected $channel_ids;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure no trailing slash at the end of the remote URL.
    $subscriber_url = $this->get('subscriber_url');
    if (!empty($subscriber_url) && preg_match('/(.*)\/$/', $subscriber_url, $matches)) {
      $this->set('subscriber_url', $matches[1]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthPlugin() {
    $pluginData = $this->auth;
    if (!empty($pluginData['pid'])) {
      // DI not available in entities:
      // https://www.drupal.org/project/drupal/issues/2142515.
      /** @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginManager $manager */
      $manager = \Drupal::service('plugin.manager.entity_share_client_authorization');
      $pluginId = $pluginData['pid'];
      unset($pluginData['pid']);
      return $manager->createInstance($pluginId, $pluginData);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mergePluginConfig(ClientAuthorizationInterface $plugin) {
    $auth = ['pid' => $plugin->getPluginId()] +
      $plugin->getConfiguration();
    $this->auth = $auth;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClient(bool $json) {
    $plugin = $this->getAuthPlugin();
    if ($json) {
      return $plugin->getJsonApiClient($this->subscriber_url);
    }
    return $plugin->getClient($this->subscriber_url);
  }

}

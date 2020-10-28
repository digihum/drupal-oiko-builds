<?php

declare(strict_types = 1);

namespace Drupal\entity_share_notifier\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

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
 *     "basic_auth_username",
 *     "basic_auth_password",
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
   * The subscriber basic auth username.
   *
   * @var string
   */
  protected $basic_auth_username;

  /**
   * The subscriber basic auth password.
   *
   * @var string
   */
  protected $basic_auth_password;

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

}

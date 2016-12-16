<?php

namespace Drupal\sharemessage;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Define interface for ShareMessage entity.
 */
interface ShareMessageInterface extends ConfigEntityInterface {

  /**
   * Sets the internal Share Message label.
   *
   * @param string $label
   *   A human-readable label representing the internal Share Message label.
   *
   * @return $this
   */
  public function setLabel($label);

  /**
   * Sets the Share Message title used when sharing.
   *
   * @param string $title
   *   The title of the Share Message to be set.
   *
   * @return $this
   */
  public function setTitle($title);

  /**
   * Returns the plugin instance.
   *
   * @return \Drupal\sharemessage\SharePluginInterface
   *   The plugin instance for this Share Message.
   */
  public function getPlugin();

  /**
   * Returns the Share Message plugin ID.
   *
   * @return string
   *   The Share Message plugin ID used by this Share Message.
   */
  public function getPluginId();

  /**
   * Sets the plugin ID.
   *
   * @param string $plugin_id
   *   The Share Message plugin ID to be set.
   *
   * @return $this
   */
  public function setPluginID($plugin_id);

  /**
   * Checks whether the Share Message plugin of this Share Message exists.
   *
   * @return bool
   *   TRUE if the Share Message plugin exists, FALSE otherwise.
   */
  public function hasPlugin();

  /**
   * Gets the definition of the plugin implementation.
   *
   * @return array
   *   The plugin definition, as returned by the discovery object used by the
   *   plugin manager.
   */
  public function getPluginDefinition();

  /**
   * Gets the default settings.
   *
   * @param string $key
   *   The settings key.
   *
   * @return mixed
   *   The default settings.
   */
  public function getSetting($key);

  /**
   * Gets a context for tokenizing.
   *
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the item.
   *
   * @return array
   *   An array containing the following elements:
   *     - sharemessage: This entity.
   *     - node: The node target for the current request, if any.
   *   The array is altered by modules implementing
   *   hook_sharemessage_token_context().
   */
  public function getContext($view_mode = 'full');

  /**
   * Returns Open Graph meta tags for <head>.
   *
   * @param array $context
   *   The context for the token replacements.
   *
   * @return array
   *   An array containing the Open Graph meta tags:
   *     - title
   *     - image: If at least one image Url is given.
   *     - video, video:width, video:height, video:type:
   *       If at least one video Url is given.
   *     - url
   *     - description
   *     - type
   */
  public function buildOGTags($context);

  /**
   * Tokenizes a field, if it is set.
   *
   * @param string $property_value
   *   A field value.
   * @param array $context
   *   A context array for Token::replace().
   * @param string $default
   *   (optional) Default value if field value is not set.
   *
   * @return string
   *   If existent, the field value with tokens replace, the default otherwise.
   */
  public function getTokenizedField($property_value, $context, $default = '');

  /**
   * Gets the Share Message URL.
   *
   * @param array $context
   *   The context for the token replacements.
   *
   * @return string
   *   The URL for this Share Message.
   */
  public function getUrl($context);

}

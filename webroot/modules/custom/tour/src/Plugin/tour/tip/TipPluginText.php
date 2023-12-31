<?php

namespace Drupal\tour\Plugin\tour\tip;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Drupal\tour\TipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays some text as a tip.
 *
 * @Tip(
 *   id = "text",
 *   title = @Translation("Text")
 * )
 */
class TipPluginText extends TipPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The body text which is used for render of this Text Tip.
   *
   * @var string
   */
  protected $body;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The forced position of where the tip will be located.
   *
   * @var string
   */
  protected $location;

  /**
   * The label used to reach this item when moving forward.
   *
   * @var string
   */
  protected $next_label;


  /**
   * The label used to reach this item when moving backward.
   *
   * @var string
   */
  protected $previous_label;

  /**
   * Determine if this step should show the information bar.
   *
   * @var bool
   */
  protected $information_bar;

  /**
   * Constructs a \Drupal\tour\Plugin\tour\tip\TipPluginText object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('token'));
  }

  /**
   * Returns a ID that is guaranteed uniqueness.
   *
   * @return string
   *   A unique id to be used to generate aria attributes.
   */
  public function getAriaId() {
    static $id;
    if (!isset($id)) {
      $id = Html::getUniqueId($this->get('id'));
    }
    return $id;
  }

  /**
   * Returns body of the text tip.
   *
   * @return string
   *   The tip body.
   */
  public function getBody() {
    return $this->get('body');
  }

  /**
   * Returns location of the text tip.
   *
   * @return string
   *   The tip location.
   */
  public function getLocation() {
    return $this->get('location');
  }

  /**
   * Get the label for moving forward to this tip.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   */
  public function getNextLabel() {
    return $this->get('next_label') ? $this->get('next_label') : $this->t('Next: @label', array('@label' => $this->get('label')));
  }

  /**
   * Get the label for moving backward to this tip.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   */
  public function getPreviousLabel() {
    return $this->get('previous_label') ? $this->get('previous_label') : $this->t('‹ @label', array('@label' => $this->get('label')));
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes() {
    $attributes = parent::getAttributes();
    $attributes['data-aria-describedby'] = 'tour-tip-' . $this->getAriaId() . '-contents';
    $attributes['data-aria-labelledby'] = 'tour-tip-' . $this->getAriaId() . '-label';
    if ($location = $this->get('location')) {
      $attributes['data-options'] = 'tipLocation:' . $location;
    }
    $attributes['data-next-text'] = $this->getNextLabel();
    $attributes['data-previous-text'] = $this->getPreviousLabel();
    if ($info_bar = $this->get('information_bar')) {
      $attributes['data-information-bar'] = 'visible';
    }
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput() {
    $output = '<h2 class="tour-tip-label" id="tour-tip-' . $this->getAriaId() . '-label">' . Html::escape($this->getLabel()) . '</h2>';
    $output .= '<p class="tour-tip-body" id="tour-tip-' . $this->getAriaId() . '-contents">' . $this->token->replace($this->getBody()) . '</p>';
    return array('#markup' => $output);
  }

}

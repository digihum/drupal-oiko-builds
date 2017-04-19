<?php

namespace Drupal\oiko_event_basket\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides the event basket block.
 *
 * @Block(
 *   id = "event_basket",
 *   admin_label = @Translation("Event basket block"),
 * )
 */
class EventBasketBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('2 lists will appear here'),
    );
  }

}

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
    return [
      [
        '#type' => 'container',
        '#attributes' => array(
          'class' => ['js-event-basket']
        ),
        '#attached' => array(
          'library' => array(
            'oiko_event_basket/event-basket',
          ),
        ),
      ],
      [
        '#type' => 'container',
        '#attributes' => array(
          'class' => ['js-event-history']
        ),
        '#attached' => array(
          'library' => array(
            'oiko_event_basket/event-history',
          ),
        ),
      ],
    ];
  }

}

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
        '#theme' => 'event_list',
        '#title' => t('My added events'),
        '#class' => 'js-event-basket',
        '#attached' => array(
          'library' => array(
            'oiko_event_basket/event-basket',
          ),
        ),
      ],
      [
        '#theme' => 'event_list',
        '#title' => t('My journey'),
        '#class' => 'js-event-history',
        '#attached' => array(
          'library' => array(
            'oiko_event_basket/event-history',
          ),
        ),
      ],
    ];
  }

}

<?php

namespace Drupal\oiko_forum\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\views\Views;

/**
 * Provides the discussion notifications block.
 *
 * @Block(
 *   id = "discussion_notifcations",
 *   admin_label = @Translation("Discussion notifications block"),
 * )
 */
class DiscussionNotifications extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $renderer = \Drupal::service('renderer');

    $block = [
      '#cache' => [
        'contexts' => [
          'user',
        ],
        'tags' => [],
      ],
    ];

    if (\Drupal::currentUser()->isAuthenticated()) {
      $view = Views::getView('my_discussions');
      $options = array(
        'id' => 'uid_touch',
        'table' => 'node_field_data',
        'field' => 'uid_touch',
        'value' => array(\Drupal::currentUser()->id()),
      );
      $view->addHandler('default', 'filter', 'node_field_data', 'uid_touch', $options);
      $view->setDisplay('discussion_notifcations');
      $block['discussion_notifcations'] = $view->render();
      $block['#cache']['tags'] = array_merge($block['#cache']['tags'], $view->getCacheTags());
    }
    else {
      $block['discussion_notifcations'] = [
        '#markup' => '<h3>My discussions</h3><p>Login or register to keep track of discussions.</p>',
      ];
    }

    $view = Views::getView('my_discussions');
    $view->setDisplay('latest_discussions');
    $block['latest_discussions'] = $view->render();
    $block['#cache']['tags'] = array_merge($block['#cache']['tags'], $view->getCacheTags());

    return $block;
  }
}

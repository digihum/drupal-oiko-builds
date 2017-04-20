<?php

namespace Drupal\oiko_app\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides the event basket block.
 *
 * @Block(
 *   id = "main_cidoc_search",
 *   admin_label = @Translation("Main cidoc search"),
 * )
 */
class MainSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#theme' => 'main_cidoc_search',
      '#attached' => [
        'library' =>  array(
          'oiko_app/mainsearch',
        ),
      ]
    );
  }

}

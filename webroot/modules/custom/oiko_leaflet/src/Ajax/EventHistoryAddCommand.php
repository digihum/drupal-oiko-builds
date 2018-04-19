<?php

namespace Drupal\oiko_leaflet\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Url;

/**
 * AJAX command for adding an item to the browser history state API.
 *
 * @ingroup ajax
 */
class EventHistoryAddCommand implements CommandInterface {

  protected $id;

  protected $title;

  /**
   * Constructs an EventHistoryAddCommand object.
   */
  public function __construct($id, $title) {
    $this->id = $id;
    $this->title = $title;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return array(
      'command' => 'oikoEventHistoryAdd',
      'id' => $this->id,
      'title' => $this->title,
    );
  }

}

<?php

namespace Drupal\oiko_leaflet\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Url;

/**
 * AJAX command for adding an item to the browser history state API.
 *
 * @ingroup ajax
 */
class GAEventCommand implements CommandInterface {

  protected $event;

  protected $args;

  /**
   * Constructs an AlertCommand object.
   *
   * @param string $text
   *   The text to be displayed in the alert box.
   */
  public function __construct($event = 'pageview', $args = []) {
    $this->event = $event;
    $this->args = $args;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return array(
      'command' => 'oikoGAEvent',
      'event' => $this->event,
      'args' => $this->args,
    );
  }

}

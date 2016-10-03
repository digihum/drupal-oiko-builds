<?php

namespace Drupal\oiko_leaflet\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Url;

/**
 * AJAX command for adding an item to the browser history state API.
 *
 * @ingroup ajax
 */
class HistoryPushCommand implements CommandInterface {

  /**
   * The Url to send to the browser.
   *
   * @var Url
   */
  protected $url;

  protected $data;

  protected $title;

  /**
   * Constructs an AlertCommand object.
   *
   * @param string $text
   *   The text to be displayed in the alert box.
   */
  public function __construct($data = NULL, $title = NULL, Url $url) {
    $this->data = $data;
    $this->title = $title;
    $this->url = $url;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return array(
      'command' => 'historyPush',
      'data' => $this->data,
      'title' => $this->title,
      'url' => $this->url->toString(),
    );
  }

}

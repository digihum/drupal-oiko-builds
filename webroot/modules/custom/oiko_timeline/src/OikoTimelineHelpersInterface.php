<?php

namespace Drupal\oiko_timeline;

interface OikoTimelineHelpersInterface {

  /**
   * @param $events
   * @param $entities
   * @param $data
   */
  public function renderEventsForTimeline($events, &$entities, &$data);
}

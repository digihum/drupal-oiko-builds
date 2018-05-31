<?php

namespace Drupal\oiko_timeline;

class OikoTimelineHelpers implements OikoTimelineHelpersInterface {

  /**
   * @param $events
   * @param $entities
   * @param $data
   *
   * @TODO: Extract this out into a service, and generally clean it up.
   */
  public function renderEventsForTimeline($events, &$entities, &$data): void {
    foreach ($events as $event) {
      /** @var CidocEntity $event */
      $entities[] = $event;
      $temporal = $event->getTemporalInformation();
      if (isset($temporal['minmin']) || isset($temporal['maxmax'])) {
        if ($significance = $event->significance->entity) {
          $significance_id = $significance->id();
          if ($color = $significance->field_icon_color->getValue()[0]['value']) {
            $event_color = $color;
          }
          else {
            $event_color = 'blue';
          }
        }
        else {
          $significance_id = 0;
          $event_color = 'blue';
        }

        $events_uri = $event->toUrl()->toString(TRUE);
        $entities[] = $events_uri;
        $data['events'][] = [
          'type' => $event->bundle() == 'e4_period' ? 'period' : 'event',
          'crm_type' => $event->bundle(),
          'uri' => $events_uri->getGeneratedUrl(),
          'id' => $event->id(),
          'label' => $event->getFriendlyLabel() . ': ' . $event->label(),
          'date_title' => $temporal['human'],
          'minmin' => $temporal['minmin'],
          'maxmax' => $temporal['maxmax'],
          'color' => $event_color,
          'significance' => $significance_id,
        ];
      }
    }
  }
}
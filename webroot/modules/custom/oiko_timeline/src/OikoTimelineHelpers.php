<?php

namespace Drupal\oiko_timeline;

use Drupal\cidoc\Entity\CidocEntity;

class OikoTimelineHelpers implements OikoTimelineHelpersInterface {

  /**
   * @var \Drupal\oiko_leaflet\ItemColorInterface
   */
  protected $colorizer;

  /**
   * OikoTimelineHelpers constructor.
   *
   * @param \Drupal\oiko_leaflet\ItemColorInterface $colorizer
   */
  public function __construct(\Drupal\oiko_leaflet\ItemColorInterface $colorizer) {
    $this->colorizer = $colorizer;
  }

  /**
   * @param $events
   * @param $entities
   * @param $data
   *
   * @TODO: Extract this out into a service, and generally clean it up.
   */
  public function renderEventsForTimeline($events, &$entities, &$data)  {
    foreach ($events as $event) {
      /** @var CidocEntity $event */
      $entities[] = $event;
      $temporal = $event->getTemporalInformation();
      if (isset($temporal['minmin']) || isset($temporal['maxmax'])) {
        if ($significance = $event->significance->entity) {
          $significance_id = $significance->id();
        }
        else {
          $significance_id = 0;
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
          'color' => $this->colorizer->getColorForCidocEvent($event),
          'significance' => $significance_id,
        ];
      }
    }
  }
}

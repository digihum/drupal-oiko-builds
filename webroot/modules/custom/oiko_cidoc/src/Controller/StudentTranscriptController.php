<?php

namespace Drupal\oiko_cidoc\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;

/**
 * Class StudentTranscriptController.
 *
 * @package Drupal\oiko_cidoc\Controller
 */
class StudentTranscriptController extends ControllerBase {


  /**
   * Render array for a transcript.
   */
  public function transcript() {
    $response = [];

    $response['cidoc_entities']['view'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_entities',
      '#display_id' => 'embed',
      '#arguments' => [
        $this->currentUser()->id(),
      ],
    ];

    $response['nodes']['view'] = [
      '#type' => 'view',
      '#name' => 'student_transcript_nodes',
      '#display_id' => 'embed',
      '#arguments' => [
        $this->currentUser()->id(),
      ],
    ];

    return $response;
  }

}

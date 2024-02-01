<?php

namespace Drupal\avatars_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avatar Kit test controller.
 */
class AvatarKitTestController extends ControllerBase {

  /**
   * Return an image for testing avatar generators.
   */
  public function image(): Response {
    $headers = ['Content-Type' => 'image/png'];
    $file = \Drupal::service('extension.path.resolver')->getPath('core', '') . '/misc/druplicon.png';
    return new BinaryFileResponse($file, 200, $headers);
  }

}

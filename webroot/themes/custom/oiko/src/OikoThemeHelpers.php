<?php

namespace Drupal\oiko;

use Drupal\Core\Security\TrustedCallbackInterface;

class OikoThemeHelpers implements TrustedCallbackInterface {

  public static function trustedCallbacks() {
    return ['oiko_remove_theme_wrappers'];
  }

  /**
   * pre_render callback that removes #theme_wrappers
   */
  public static function oiko_remove_theme_wrappers($element) {
    $element['#theme_wrappers'] = array();
    return $element;
  }

}

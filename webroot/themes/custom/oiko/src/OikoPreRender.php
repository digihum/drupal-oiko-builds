<?php 

namespace Druapl\oiko;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements trusted prerender callbacks for the Oiko theme.
 *
 */

 class ClaroPreRender implements TrustedCallbackInterface {

  /**
   * Sets Oiko Element Info - #pre_render callback.
   */
  public static function preRender($elements) {
    if (isset($elements['view']['#pre_render'])) {
        $elements['view']['#pre_render'][] = '\Drupal\oiko\OikoElementController::oiko_remove_theme_wrappers';
      }
      return $elements;
  }

  
    /**
     * pre_render callback that removes #theme_wrappers
     */
    public function oiko_remove_theme_wrappers($element) {
        $element['#theme_wrappers'] = array();
        return $element;
    }

      /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'oiko_remove_theme_wrappers',
    ];
  }

}
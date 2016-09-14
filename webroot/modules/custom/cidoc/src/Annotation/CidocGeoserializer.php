<?php

namespace Drupal\cidoc\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Class CidocGeoserializer
 *
 * @Annotation
 */
class CidocGeoserializer extends Plugin {
  /**
   * @var The ID of the plugin, and the bundle that it handles.
   */
  public $id;

  public $name;

  public $hidden = FALSE;
}

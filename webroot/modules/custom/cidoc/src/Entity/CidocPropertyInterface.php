<?php

namespace Drupal\cidoc\Entity;

interface CidocPropertyInterface {

  const SubWidgetTypeNormal = 'no';

  const SubWidgetTypeTime = 'time';

  const SubWidgetTypeGeneric = 'generic';

  /**
   * Is this property bidirectional.
   *
   * @return bool
   */
  public function isBidirectional();

}

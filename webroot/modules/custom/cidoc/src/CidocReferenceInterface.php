<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining CIDOC reference entities.
 *
 * @ingroup cidoc
 */
interface CidocReferenceInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {
  /**
   * Gets the CIDOC property.
   *
   * @return string
   *   The CIDOC property.
   */
  public function getProperty();

  /**
   * Gets the label of the CIDOC property.
   *
   * @return string
   *   The CIDOC property label.
   */
  public function getPropertyLabel();

  /**
   * Gets the reverse label of the CIDOC property.
   *
   * @return string
   *   The CIDOC property label.
   */
  public function getReverseLabel();

  /**
   * Gets the CIDOC reference creation timestamp.
   *
   * @return int
   *   Creation timestamp of the CIDOC reference.
   */
  public function getCreatedTime();

  /**
   * Sets the CIDOC reference creation timestamp.
   *
   * @param int $timestamp
   *   The CIDOC reference creation timestamp.
   *
   * @return \Drupal\cidoc\CidocReferenceInterface
   *   The called CIDOC reference entity.
   */
  public function setCreatedTime($timestamp);

}

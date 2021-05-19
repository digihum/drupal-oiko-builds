<?php

namespace Drupal\cidoc;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining CIDOC entities.
 *
 * @ingroup cidoc
 */
interface CidocEntityInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Gets the CIDOC entity's name, using the internal name where available.
   *
   * @param bool $fallback
   *   Whether to return the entity label if it has no internal name. Defaults
   *   to TRUE.
   *
   * @return string
   *   Name of the CIDOC entity.
   */
  public function getName($fallback = TRUE);

  /**
   * Gets the label of the CIDOC entity bundle (class).
   *
   * @return string
   *   The CIDOC entity class label.
   */
  public function bundleLabel();

  /**
   * Gets the friendly label of the CIDOC entity bundle (class).
   *
   * @return string
   *   The CIDOC entity class friendly label.
   */
  public function getFriendlyLabel();

  /**
   * Gets the CIDOC entity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the CIDOC entity.
   */
  public function getCreatedTime();

  /**
   * Sets the CIDOC entity creation timestamp.
   *
   * @param int $timestamp
   *   The CIDOC entity creation timestamp.
   *
   * @return \Drupal\cidoc\CidocEntityInterface
   *   The called CIDOC entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets property references from/to the CIDOC entity, indexed by property.
   *
   * @param null|array|string $property_name
   *   Specify property type(s) to get. Leave as NULL to get all properties.
   * @param bool $reverse
   *   Optionally, set to TRUE to get references to this CIDOC entity instead of
   *   references from this entity.
   * @param bool $load_entities
   *   Optionally, set to FALSE to avoid loading entities.
   * @return array
   *   Returns an associative array of CIDOC property names, each mapped to
   *   arrays, which are associative arrays of CIDOC reference entity ids,
   *   mapped to their entity objects unless $load_entities was falsey.
   */
  public function getReferences($property_name = NULL, $reverse = FALSE, $load_entities = TRUE);

  /**
   * Queries for entities referencing to/from this entity that need populating.
   *
   * @return array
   *   Returns an array of ids. The values of the array are strings containing
   *   the endpoint entity ids separated by a '>' to indicate the reference
   *   direction. The keys will be strings containing the reference entity ID
   *   and property machine name, separated by a colon.
   */
  public function getReferencesNeedingPopulating();

  /**
   * @deprecated
   */
  public function getReverseReferences($properties = [], $loaded = TRUE);

  /**
   * @deprecated
   */
  public function getForwardReferences($properties = [], $loaded = TRUE);

  public function getReverseReferencedEntities($properties = [], $loaded = TRUE);
  public function getForwardReferencedEntities($properties = [], $loaded = TRUE);


  public function getTemporalInformation();
  public function getGeospatialData();

  public function hasGeospatialData();
}

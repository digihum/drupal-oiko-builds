<?php

declare(strict_types = 1);

namespace Drupal\medmus_share\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Entity import status entities.
 */
interface DeletedRemoteEntityInterface extends ContentEntityInterface {

  /**
   * Denotes the default entity import policy.
   */
  const DECISION_UNDECIDED = 0;

  /**
   * Denotes the skip entity import policy.
   */
  const DECISION_KEEP = 1;

  /**
   * Denotes the skip entity import policy.
   */
  const DECISION_DELETE = 2;

  /**
   * Denotes the deleted automatically entity import policy.
   */
  const DECISION_DELETE_AUTOMATICALLY = 3;

  /**
   * Gets the timestamp of the last entity change.
   *
   * @return int
   *   The timestamp of the last entity save operation.
   */
  public function getChangedTime();

  /**
   * Sets the timestamp of the last entity change.
   *
   * @param int $timestamp
   *   The timestamp of the last entity save operation.
   *
   * @return $this
   */
  public function setChangedTime($timestamp);

  /**
   * Returns the import policy of entity.
   *
   * @return int
   *   The import policy.
   */
  public function getDecision();

  /**
   * Sets the import policy of entity.
   *
   * @param int $policy
   *   The import policy.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setDecision($policy);

  /**
   * @return ContentEntityInterface
   */
  public function getLocalEntity();

    /**
   * Gets all implemented import policies.
   *
   * @return array
   *   Keys are raw policy values, values are human-readable labels.
   */
  public static function getAvailableDecisions(): array;

}

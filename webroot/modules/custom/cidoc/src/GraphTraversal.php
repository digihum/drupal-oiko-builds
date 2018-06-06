<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Provides a mechanism for traversing the CIDOC Entity graph.
 */
class GraphTraversal {

  public function findConnectedVertices(CidocEntity $entity, callable $predicate = NULL, $properties = [], $loaded = TRUE) {
    // @TODO: There's maybe some value in these values, maybe this should be object that represents them.
    // These are the vertices that we've visited already.
    $visited = [];
    // These are the vertices we still need to visit.
    $to_visit = [$entity];
    // These are the vertices that match the $predicate and we're going to return.
    $vertices = [$entity->id() => $entity];

    while (!empty($to_visit)) {
      $visiting_entity = array_pop($to_visit);
      $visited[] = $visiting_entity->id();

      /** @var \Drupal\cidoc\Entity\CidocEntity $visiting_entity */
      $all_referenced_entities = array_merge(
        $visiting_entity->getForwardReferencedEntities($properties),
        $visiting_entity->getReverseReferencedEntities($properties)
      );
      foreach ($all_referenced_entities as $referenced_entity) {
        // If we've not visited this entity and the predicate is not satisfied, this is one of our vertices.
        if (!in_array($referenced_entity->id(), $visited, TRUE) && $this->predicateSatisfied($referenced_entity, $predicate)) {
          $vertices[$referenced_entity->id()] = $referenced_entity;
          $to_visit[] = $referenced_entity;
        }
      }
    }

    return $loaded ? $vertices : array_keys($vertices);
  }

  protected function predicateSatisfied(CidocEntity $entity, callable $predicate = NULL) {
    if (!isset($predicate)) {
      return TRUE;
    }
    else {
      return call_user_func($predicate, $entity);
    }
  }
}
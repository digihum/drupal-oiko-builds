<?php

namespace Drupal\cidoc;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\cidoc\Entity\CidocReference;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Provides a mechanism for traversing the CIDOC Entity graph.
 */
class GraphTraversal {

  // @TODO: Return the edges in addition to the vertices.
  public function findConnectedVertices(CidocEntity $entity, callable $predicate = NULL, $max_edge_length = NULL, $properties = [], $loaded = TRUE) {
    // @TODO: There's maybe some value in these values, maybe this should be object that represents them.
    // These are the vertices that we've visited already.
    $visited = [];
    // These are the vertices we still need to visit.
    $to_visit = [['entity' => $entity, 'edge_length' => 0,]];
    // These are the vertices that match the $predicate and we're going to return.
    $vertices = [$entity->id() => $entity];

    while (!empty($to_visit)) {
      $visiting_data = array_pop($to_visit);
      $visiting_entity = $visiting_data['entity'];
      $visiting_edge_length = $visiting_data['edge_length'];

      // Skip this entity if the edge length is too long.
      if (!is_null($max_edge_length) && $visiting_edge_length >= $max_edge_length) {
        continue;
      }

      // @TOOD: Is it possible that we'd never complete for some nodes with weird ways that they could be visited with different edge lengths?
      $visited[] = $visiting_entity->id();

      /** @var \Drupal\cidoc\Entity\CidocEntity $visiting_entity */
      $all_referenced_entities = array_merge(
        $visiting_entity->getForwardReferencedEntities($properties),
        $visiting_entity->getReverseReferencedEntities($properties)
      );
      foreach ($all_referenced_entities as $referenced_entity) {
        // If we've not visited this entity and the predicate is satisfied, this is one of our vertices.
        if (!in_array($referenced_entity->id(), $visited, TRUE) && $this->entityPredicateSatisfied($referenced_entity, $predicate)) {
          $vertices[$referenced_entity->id()] = $referenced_entity;
          $to_visit[] = [
            'entity' => $referenced_entity,
            'edge_length' => $visiting_edge_length + 1,
            ];
        }
      }
    }

    return $loaded ? $vertices : array_keys($vertices);
  }

  protected function entityPredicateSatisfied(CidocEntity $entity, callable $predicate = NULL) {
    if (!isset($predicate)) {
      return TRUE;
    }
    else {
      return call_user_func($predicate, $entity);
    }
  }

  protected function referencePredicateSatisfied(CidocReference $reference, callable $predicate = NULL) {
    if (!isset($predicate)) {
      return TRUE;
    }
    else {
      return call_user_func($predicate, $reference);
    }
  }

  public function findConnectedVerticesAndEdges(CidocEntity $entity, $max_edge_length = NULL, callable $vertice_predicate = NULL, $edge_predicate = NULL, $loaded = TRUE) {
    // @TODO: There's maybe some value in these values, maybe this should be object that represents them.
    // These are the vertices that we've visited already.
    $visited = [];
    // These are the vertices we still need to visit.
    $to_visit = [['entity' => $entity, 'edge_length' => 0,]];
    // These are the vertices that match the $predicate and we're going to return.
    $vertices = [$entity->id() => $entity];
    $edges = [];

    while (!empty($to_visit)) {
      $visiting_data = array_pop($to_visit);
      $visiting_entity = $visiting_data['entity'];
      $visiting_edge_length = $visiting_data['edge_length'];

      // Skip this entity if the edge length is too long.
      if (!is_null($max_edge_length) && $visiting_edge_length >= $max_edge_length) {
        continue;
      }

      // @TOOD: Is it possible that we'd never complete for some nodes with weird ways that they could be visited with different edge lengths?
      $visited[] = $visiting_entity->id();

      /** @var \Drupal\cidoc\Entity\CidocEntity $visiting_entity */

      // Loop over all forward and reverse properties
      foreach (array(CidocProperty::DOMAIN_ENDPOINT => FALSE, CidocProperty::RANGE_ENDPOINT => TRUE) as $endpoint => $reverse) {
        foreach ($references = $visiting_entity->getReferences(NULL, $reverse) as $property_bundle => $these_references) {
          foreach ($these_references as $reference) {
            /** @var CidocReference $reference */
            // Check that our edge predicate is satisfied.
            if ($this->referencePredicateSatisfied($reference, $edge_predicate)) {
              // Evaluate the entity.
              if ($endpoint == CidocProperty::DOMAIN_ENDPOINT) {
                $referenced_entity = $reference->getRangeEntity();
              }
              else {
                $referenced_entity = $reference->getDomainEntity();
              }
              if (!in_array($referenced_entity->id(), $visited, TRUE) && $this->entityPredicateSatisfied($referenced_entity, $vertice_predicate)) {
                $vertices[$referenced_entity->id()] = $referenced_entity;
                $edges[$reference->id()] = $reference;
                $to_visit[] = [
                  'entity' => $referenced_entity,
                  'edge_length' => $visiting_edge_length + 1,
                ];
              }
            }
          }
        }
      }
    }

    return [
      'vertices' => $loaded ? $vertices : array_keys($vertices),
      'edges' => $loaded ? $edges : array_keys($edges),
    ];
  }
}

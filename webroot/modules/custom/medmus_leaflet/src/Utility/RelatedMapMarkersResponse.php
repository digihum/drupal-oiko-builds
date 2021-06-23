<?php

namespace Drupal\medmus_leaflet\Utility;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseTrait;

/**
 * Related Map Markers Response.
 */
class RelatedMapMarkersResponse implements CacheableDependencyInterface {

  /**
   * The cacheability metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheabilityMetadata;

  /**
   * {@inheritdoc}
   */
  public function addCacheableDependency($dependency) {
    // A trait doesn't have a constructor, so initialize the cacheability
    // metadata if that hasn't happened yet.
    if (!isset($this->cacheabilityMetadata)) {
      $this->cacheabilityMetadata = new CacheableMetadata();
    }

    $this->cacheabilityMetadata = $this->cacheabilityMetadata->merge(CacheableMetadata::createFromObject($dependency));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    // A trait doesn't have a constructor, so initialize the cacheability
    // metadata if that hasn't happened yet.
    if (!isset($this->cacheabilityMetadata)) {
      $this->cacheabilityMetadata = new CacheableMetadata();
    }

    return $this->cacheabilityMetadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getCacheableMetadata()->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getCacheableMetadata()->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->getCacheableMetadata()->getCacheMaxAge();
  }

  protected $realSourcePoints = [];

  protected $realTargetPoints = [];

  protected $fakeTargetPoints = [];

  protected $realEdges = [];

  protected $fakeEdges = [];

  /**
   * RelatedMapMarkersResponse constructor.
   */
  public function __construct(CidocEntityInterface $rootEntity) {
    $this->addCacheableDependency($rootEntity);
  }

  public function addSourcePoint($sourcePoint, CidocEntityInterface $locationEntity, CidocEntityInterface $workEntity) {
    $this->addCacheableDependency($locationEntity);
    $this->addCacheableDependency($workEntity);
    $sourcePoint['id'] = $workEntity->id();
    return $this->doAddSourcePoint($sourcePoint);
  }

  protected function doAddSourcePoint($sourcePoint) {
    // If we have already added this real point, no need to add again.
    if ($prior_keys = array_keys($this->realSourcePoints, $sourcePoint)) {
      $key = reset($prior_keys);
    }
    else {
      $this->realSourcePoints[] = $sourcePoint;
      $key = array_key_last($this->realSourcePoints);
    }
    return $key;
  }

  public function addRealTargetPoint($sourcePointId, $targetPoint, bool $forward, string $lineLabel, CidocEntityInterface $workEntity, CidocEntityInterface $targetEntity) {
    $this->addCacheableDependency($workEntity);
    $this->addCacheableDependency($targetEntity);
    $targetPoint['id'] = $workEntity->id();
    $key = $this->doAddRealTargetPoint($targetPoint);
    // Add the line too.
    $this->realEdges[] = [
      'source' => $sourcePointId,
      'target' => $key,
      'forward' => $forward,
      'label' => $lineLabel,
      'popup' => $lineLabel,
    ];
    return $key;
  }

  protected function doAddRealTargetPoint($targetPoint) {
    // If we have already added this real point, no need to add again.
    if ($prior_keys = array_keys($this->realTargetPoints, $targetPoint)) {
      $key = reset($prior_keys);
    }
    else {
      $this->realTargetPoints[] = $targetPoint;
      $key = array_key_last($this->realTargetPoints);
    }
    return $key;
  }

  public function addFakeTargetPoint($sourcePointId, $targetPoint, bool $forward, string $lineLabel, CidocEntityInterface $workEntity) {
    $this->addCacheableDependency($workEntity);
    $targetPoint['id'] = $workEntity->id();
    $targetPoint['type'] = 'point';
    $key = $this->doAddFakeTargetPoint($targetPoint);
    // Add the line too.
    $this->fakeEdges[] = [
      'source' => $sourcePointId,
      'target' => $key,
      'forward' => $forward,
      'label' => $lineLabel,
      'popup' => $lineLabel,
    ];
    return $key;
  }

  protected function doAddFakeTargetPoint($targetPoint) {
    // If we have already added this fake point, no need to add again.
    if ($prior_keys = array_keys($this->fakeTargetPoints, $targetPoint)) {
      $key = reset($prior_keys);
    }
    else {
      $this->fakeTargetPoints[] = $targetPoint;
      $key = array_key_last($this->fakeTargetPoints);
    }
    return $key;
  }

  public function toJsonData() {
    return [
      'sourcePoints' => $this->cleanPoints($this->realSourcePoints, 'medmus-leaflet-marker-work-upside-down'),
      'realTargetPoints' => $this->cleanPoints($this->realTargetPoints, 'medmus-leaflet-marker-work-upside-down'),
      'realTargetLines' => $this->realEdges,
      'fakeTargetPoints' => $this->cleanPoints($this->fakeTargetPoints),
      'fakeTargetLines' => $this->fakeEdges,
    ];
  }

  protected function cleanPoints($points, $markerClass = 'medmus-leaflet-marker-work') {
    return array_map(function ($item) use ($markerClass) {
      unset($item['temporal'], $item['significance_id'], $item['significance'], $item['color']);
      $item['markerClass'] = $markerClass;
      return $item;
    }, $points);
  }

  public function mergeWith(RelatedMapMarkersResponse $b) {
    // First merge source points, taking note of the keys that change.
    $sourcePointMappings = [];
    foreach ($b->realSourcePoints as $old_key => $sourcePoint) {
      $sourcePointMappings[$old_key] = $this->doAddSourcePoint($sourcePoint);
    }

    $realTargetMappings = [];
    foreach ($b->realTargetPoints as $old_key => $targetPoint) {
      $realTargetMappings[$old_key] = $this->doAddRealTargetPoint($targetPoint);
    }

    $fakeTargetMappings = [];
    foreach ($b->fakeTargetPoints as $old_key => $targetPoint) {
      $fakeTargetMappings[$old_key] = $this->doAddFakeTargetPoint($targetPoint);
    }

    // Now merge the edges.
    foreach ($b->realEdges as $edge) {
      $this->realEdges[] = [
        'source' => $sourcePointMappings[$edge['source']],
        'target' => $realTargetMappings[$edge['target']],
        'forward' => $edge['forward'],
        'label' => $edge['label'],
        'popup' => $edge['popup'],
      ];
    }

    // Now merge the edges.
    foreach ($b->fakeEdges as $edge) {
      $this->fakeEdges[] = [
        'source' => $sourcePointMappings[$edge['source']],
        'target' => $fakeTargetMappings[$edge['target']],
        'forward' => $edge['forward'],
        'label' => $edge['label'],
        'popup' => $edge['popup'],
      ];
    }

    $this->addCacheableDependency($b);
    return $this;
  }
}

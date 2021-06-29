(function() {
  'use strict';
function extensions(parentClass) { return {
  options: {

  },

  initialize: function (targetLayer, options, priorValue) {
    L.Util.setOptions(this, options);
    this._targetLayer = targetLayer;
    this._visibleLayers = [];
    this._allLayersAndMetadata = [];
    this._filteringCallbacks = priorValue || [];
    this._additionQueue = [];
    this._addLayerTimer = null;
    this._filterLayersTimer = null;
  },

  addLayer: function addLayer(layer, metadata) {
    // This gets called over and over and over by our mapping library, so
    // we 'debounce' it.
    if (this._addLayerTimer) {
      clearTimeout(this._addLayerTimer);
    }
    this._additionQueue.push([layer, metadata]);
    var that = this;
    this._addLayerTimer = setTimeout(function() {
      that.addLayers(that._additionQueue);
      that._additionQueue = [];
    }, 0);
  },

  addLayers: function addLayers(layers) {
    var i, layer, layersToAddToTarget = [];
    for (i = 0; i < layers.length; i++) {
      layer = layers[i];
      this._allLayersAndMetadata.push(layer);
      if (this.isLayerVisible(layer)) {
        this._visibleLayers.push(layer[0]);
        layersToAddToTarget.push(layer[0]);
      }
    }

    this._addLayersToTarget(layersToAddToTarget);
  },

  push: function push(callback) {
    this.addFilteringCallback(callback);
  },
  addFilteringCallback: function addFilteringCallback(callback) {
    if (typeof callback !== 'function') {
      throw 'Passed callback is not a valid function.'
    }
    this._filteringCallbacks.push(callback);
    // Re-filter all the data based on this new callback.
    this.recomputeFilteredItems();
  },

  removeFilteringCallback: function removeilteringCallback(callback) {
    if (typeof callback !== 'function') {
      throw 'Passed callback is not a valid function.'
    }
    this._removeItemFromArray(this._filteringCallbacks, callback);
    this.recomputeFilteredItems();
  },

  isLayerVisible: function isLayerVisible(layerAndMetadata) {
    // Simply need to check to see if any filtering callbacks disallow showing
    // this layer.
    if (this._filteringCallbacks.length) {
      for (var i = 0;i < this._filteringCallbacks.length; i++) {
        if (!this._filteringCallbacks[i].apply(null, layerAndMetadata)) {
          return false;
        }
      }
    }
    return true;
  },

  /**
   * Remove a single layer from the filterable group.
   *
   * @param layer
   *   The leaflet layer to remove.
   * @param metadata
   *   The metadata for the layer.
   */
  removeLayer: function removeLayer(layer, metadata) {
    this.removeLayers([[layer, metadata]]);
  },

  /**
   * Remove layers from the filterable group.
   *
   * @param layers
   *   An array of layers and metadata to remove.
   */
  removeLayers: function removeLayers(layers) {
    var j, i, layer, layersToRemoveFromTarget = [];
    for (j = 0; j < layers.length; j++) {
      layer = layers[j];

      // Remove from the visible layers first.
      this._visibleLayers.removeLayer(layer[0]);
      layersToRemoveFromTarget.push(layer[0]);
      // Now remove the layer + metadata from our internal storage.
      this._removeItemFromArray(this._allLayersAndMetadata, layer);
    }

    // Recreate a brand new temporal tree, as we have no way to remove things
    // from it.
    this._temporalTree = new IntervalTree();
    for (i = 0; i < this._temporalLayers.length; i++) {
      this._temporalTree.insert(this._temporalLayers[i].temporal.start, this._temporalLayers[i].temporal.end, this._temporalLayers[i]);
    }

    // Now remove those layers from our target.
    this._removeLayersFromTarget(layersToRemoveFromTarget);
  },

  /**
   * Add the given layers to our target layer.
   */
  _addLayersToTarget: function _addLayersToTarget(layersToAddToTarget) {
    // Now add those layers to our target.
    if (typeof this._targetLayer.addLayers === 'function') {
      this._targetLayer.addLayers(layersToAddToTarget);
    }
    else {
      for (i = 0; i < layersToAddToTarget.length; i++) {
        this._targetLayer.addLayer(layersToAddToTarget[i]);
      }
    }
  },

  /**
   * Remove the given layers from our target layer.
   */
  _removeLayersFromTarget: function _removeLayersFromTarget(layersToRemoveFromTarget) {
    if (typeof this._targetLayer.removeLayers === 'function') {
      this._targetLayer.removeLayers(layersToRemoveFromTarget);
    }
    else {
      for (i = 0; i < layersToRemoveFromTarget.length; i++) {
        this._targetLayer.removeLayer(layersToRemoveFromTarget[i]);
      }
    }
  },

  /**
   * Change the layers on our target layer.
   *
   * This allows for efficient adding/removing all in one go.
   */
  _changeLayersOnTarget: function _changeLayersOnTarget(layersToAddToTarget, layersToRemoveFromTarget) {
    if (typeof this._targetLayer.changeLayers === 'function') {
      this._targetLayer.changeLayers(layersToAddToTarget, layersToRemoveFromTarget);
    }
    else {
      this._removeLayersFromTarget(layersToRemoveFromTarget);
      this._addLayersToTarget(layersToAddToTarget);
    }
  },

  _removeItemFromArray: function _removeItemFromArray(array, item) {
    var i = array.indexOf(item);
    if (i !== -1) {
      array.splice(i, 1);
    }
  },

  clearLayers: function clearLayers() {
    this.removeLayers(this._allLayersAndMetadata);
  },

  recomputeFilteredItems: function recomputeFilteredItems() {
    // This gets called over and over and over by our mapping library, so
    // we 'debounce' it.
    if (this._filterLayersTimer) {
      clearTimeout(this._filterLayersTimer);
    }
    var that = this;
    this._filterLayersTimer = setTimeout(function () {
      that.recomputeFilteredItemsImmediately();
    }, 0);
  },

  recomputeFilteredItemsImmediately: function recomputeFilteredItemsImmediately() {
    // We need to work out which items we should show/hide etc.
    var i, features = [];
    var featuresToRemove = [];

    // Compute features that should be visible.
    for (i = 0; i < this._allLayersAndMetadata.length; i++) {
      if (this.isLayerVisible(this._allLayersAndMetadata[i])) {
        features.push(this._allLayersAndMetadata[i][0]);
      }
    }

    var found, layer;

    // Loop through the existing features on our map.
    for (i = 0; i < this._visibleLayers.length; i++) {
      found = false;
      layer = this._visibleLayers[i];
      // Search for this layer in our set of features we do want.
      for (var j = 0; j < features.length; j++) {
        if (features[j] === layer) {
          found = true;
          features.splice(j, 1);
          break;
        }
      }
      if (!found) {
        // We didn't find this layer, so remove it and decrement i, so we process this i again.
        featuresToRemove.push(layer);
        this._visibleLayers.splice(i, 1);
        i--;
      }
    }

    // features now only contains features we do want, but are not visible yet,
    // add them to the record of visible layers and then call through to the
    // target layer.
    for (i = 0; i < features.length; i++) {
      this._visibleLayers.push(features[i]);
    }
    this._changeLayersOnTarget(features, featuresToRemove);
  }
}};

L.FilterableLayerHelper   = L.Class.extend(extensions( L.Class ));

L.filterableLayerHelper = function (targetLayer, options) {
  return new L.FilterableLayerHelper(targetLayer, options || {});
};

})();

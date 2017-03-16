(function() {
  'use strict';
function extensions(parentClass) { return {

  options: {
    visibleInTimelineBrowser: true,
    temporalRangeWindow: 0
  },

  initialize: function (targetLayer, options) {
    L.Util.setOptions(this, options);
    this._targetLayer = targetLayer;
    this._visibleLayers = [];
    this._staticLayers = [];
    this._temporalLayers = [];
    this._temporalTree = new IntervalTree();
  },

  addTo: function addTo(map) {
    this.remove();
    this._map = map;

    this.onAdd(map);
    return this;
  },

  remove: function remove() {
    if (!this._map) {
      return this;
    }

    if (this.onRemove) {
      this.onRemove(this._map);
    }

    this._map = null;

    return this;
  },

  onAdd: function onAdd(map) {
    this.map = map;
    this.map.on('temporal.shift', this._onTemporalChange, this);
    this.map.on('temporal.getBounds', this._onTemporalGetBounds, this);
    this.map.on('temporal.getCounts', this._onTemporalGetCounts, this);
    this.map.on('temporal.getStartAndEnds', this._onTemporalgetStartAndEnds, this);
  },

  onRemove: function onRemove() {
    this.map.off('temporal.shift', this._onTemporalChange, this);
    this.map.off('temporal.getBounds', this._onTemporalGetBounds, this);
    this.map.off('temporal.getCounts', this._onTemporalGetCounts, this);
    this.map.off('temporal.getStartAndEnds', this._onTemporalgetStartAndEnds, this);
  },

  addLayer: function addLayer(layer) {
    // This isn't a temporal layer, so just add it to our list of static layers.
    if (!('temporal' in layer) || !('start' in layer.temporal) || !('end' in layer.temporal)) {
      this._staticLayers.push(layer);
      this._targetLayer.addLayer(layer);
      return;
    }

    this._temporalLayers.push(layer);
    this._temporalTree.insert(layer.temporal.start, layer.temporal.end, layer);

    // @TODO: Need to debounce this.
    this.map.fire('temporal.rebase');
  },

  removeLayer: function removeLayer(layer) {
    this._targetLayer.removeLayer(layer);
    var i;

    i = this._visibleLayers.indexOf(layer);
    if (i !== -1) {
      this._visibleLayers.splice(i, 1);
    }

    i = this._staticLayers.indexOf(layer);
    if (i !== -1) {
      this._staticLayers.splice(i, 1);
    }

    i = this._temporalLayers.indexOf(layer);
    if (i !== -1) {
      this._temporalLayers.splice(i, 1);
    }

    this._temporalTree = new IntervalTree();
    for (i = 0; i < this._temporalLayers.length; i++) {
      this._temporalTree.insert(this._temporalLayers[i].temporal.start, this._temporalLayers[i].temporal.end, this._temporalLayers[i]);
    }

    // @TODO: Need to debounce this.
    this.map.fire('temporal.rebase');
  },

  clearLayers: function () {
    this._visibleLayers = [];
    this.s = [];
    this._targetLayer.clearLayers();
  },

  _onTemporalChange: function(e) {

    var features = [];
    if (this._temporalTree.size) {
      // Get the layers we should be showing.
      if (this.options.temporalRangeWindow == 0) {
        features = this._temporalTree.lookup(Math.ceil(e.time));
      }
      else {
        features = this._temporalTree.overlap(Math.floor(e.time - this.options.temporalRangeWindow), Math.ceil(e.time + this.options.temporalRangeWindow));
      }
    }

    var found, layer;

    // Loop through the existing features on our map.
    for (var i = 0; i < this._visibleLayers.length; i++) {
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
        this._targetLayer.removeLayer(layer);
        this._visibleLayers.splice(i, 1);
        i--;
      }
    }

    for (var k = 0; k < features.length; k++) {
      layer = features[k];
      this._visibleLayers.push(layer);
      this._targetLayer.addLayer(layer);
    }
  },

  _onTemporalGetBounds: function(e) {
    if (!this.options.visibleInTimelineBrowser) {
      return;
    }
    var bounds = {
      min: Infinity,
      max: -Infinity
    };

    // Process all our temporal items.
    for (var i = 0; i < this._temporalLayers.length; i++) {
      bounds.min = Math.min(bounds.min, this._temporalLayers[i].temporal.start);
      bounds.max = Math.max(bounds.max, this._temporalLayers[i].temporal.end);
    }

    e.boundsCallback(bounds.min, bounds.max);
  },

  _onTemporalGetCounts: function(e) {
    if (!this.options.visibleInTimelineBrowser) {
      return;
    }
    e.countsCallback(this._temporalTree.size ? this._temporalTree.overlap(e.slice.start, e.slice.end).length : 0);
  },

  _onTemporalgetStartAndEnds: function(e) {
    if (!this.options.visibleInTimelineBrowser) {
      return;
    }
    if (this._temporalTree.size) {
      var items = this._temporalTree.overlap(e.slice.start, e.slice.end);
      for (var i = 0; i < items.length;i++) {
        e.startEndCallback(items[i].temporal.start, items[i].temporal.end);
      }
    }
  }
}};

L.TemporalLayerHelper   = L.Class.extend(extensions( L.Class ));

L.temporalLayerHelper = function (targetLayer, options) {
  return new L.TemporalLayerHelper(targetLayer, options || {});
};

})();
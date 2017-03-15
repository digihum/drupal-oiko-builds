(function() {

function extensions(parentClass) { return {

  initialize: function (targetLayer, options) {
    this._targetLayer = targetLayer;
    this._visibleLayers = [];
    this._staticLayers = [];
    this._temporalRangeWindow = options.temporalRangeWindow || 0;
    this._temporalTree = new IntervalTree();
  },

  addTo: function (map) {
    this.remove();
    this._map = map;

    this.onAdd(map);
    return this;
  },

  remove: function () {
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
    map.on('temporal.shift', this._onTemporalChange, this);
  },
  onRemove: function onRemove() {
    this.map.off('temporal.shift', this._onTemporalChange, this);
  },

  addLayer: function (layer) {
    // This isn't a temporal layer, so just add it to our list of static layers.
    if (!('temporal' in layer) || !('start' in layer.temporal) || !('end' in layer.temporal)) {
      this._staticLayers.push(layer);
      this._targetLayer.addLayer(layer);
      return;
    }
    this._temporalTree.insert(layer.temporal.start, layer.temporal.end, layer)
  },

  removeLayer: function (layer) {
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
      if (this._temporalRangeWindow == 0) {
        features = this._temporalTree.lookup(Math.ceil(e.time));
      }
      else {
        features = this._temporalTree.overlap(Math.floor(e.time - this._temporalRangeWindow), Math.ceil(e.time + this._temporalRangeWindow));
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
  }
}};

L.TemporalLayerHelper   = L.Class.extend(extensions( L.Class ));

L.temporalLayerHelper = function (targetLayer, options) {
  return new L.TemporalLayerHelper(targetLayer, options || {});
};

})();
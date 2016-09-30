(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    drupalLeaflet.clusterer = L.markerClusterGroup({
      // Make the radius of the clusters quite small.
      maxClusterRadius: 40
    });
    map.addLayer(drupalLeaflet.clusterer);

    // Set the clusterer be the main layer on the map for us.
    drupalLeaflet.mainLayer = drupalLeaflet.clusterer;

    // Temporal stuff, we want a layer group to keep track of Leaflet features
    // with temporal data.
    drupalLeaflet.temporalDisplayedLayerGroup = L.layerGroup();

    // Instantiate an IntervalTree to make searching for what to hide/show easier.
    drupalLeaflet.temporalTree = new IntervalTree();
    drupalLeaflet.temporalStart = Infinity;
    drupalLeaflet.temporalEnd = -Infinity;

    drupalLeaflet.drawOnSetTime = true;

    drupalLeaflet.updateTime = function(time) {
      this.time = typeof time === 'number' ? time : new Date(time).getTime();
      if (this.drawOnSetTime) {
        this.updateTemporalLayersTrigger();
      }
    };

    drupalLeaflet.timelineControl = new L.TimeLineControl({
      formatOutput: function(date){
        return new Date(date).toString();
      },
      drupalLeaflet: drupalLeaflet
    });

    // Add an extra div, and plonk the timeline widget in it.
    drupalLeaflet.timelineControl._map = map;
    drupalLeaflet.timelineContainerDiv = drupalLeaflet.timelineControl.onAdd(map);
    $(drupalLeaflet.container).after(drupalLeaflet.timelineContainerDiv);
    $(drupalLeaflet.timelineContainerDiv).hide();
    drupalLeaflet.timelineControl.addTimeline(drupalLeaflet.temporalTree, $.proxy(drupalLeaflet.updateTime, drupalLeaflet));


    drupalLeaflet.updateTemporalLayersTrigger = function() {
      // Fire an event so that anyone can respond.
      $(document).trigger('temporalShift', [this]);
    };

    drupalLeaflet.updateTemporalLayers = function() {
      var self = this;

      // Show half a year either side of our selection.
      var offset = 365.25 * 86400 / 2;
      // These are the features we want on our map.
      var features = self.temporalTree.overlap(Math.floor(self.time - offset), Math.ceil(self.time + offset));
      
      var found, layer;

      // Loop through the existing features on our map.
      for (var i = 0; i < self.temporalDisplayedLayerGroup.getLayers().length; i++) {
        found = false;
        layer = self.temporalDisplayedLayerGroup.getLayers()[i];
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
          i--;
          self.mainLayer.removeLayer(layer);
          self.temporalDisplayedLayerGroup.removeLayer(layer);
        }
      }

      features.forEach(function (feature) {
        self.mainLayer.addLayer(feature);
        self.temporalDisplayedLayerGroup.addLayer(feature);
      });
    };
    $(document).on('temporalShift', function(e, dl) {
      $.proxy(drupalLeaflet.updateTemporalLayers, dl)();
    });
  });

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    if (feature.hasOwnProperty('temporal')) {
      // We need to remove this feature from the map, and add it to our special LayerGroup.
      drupalLeaflet.lMap.removeLayer(lFeature);
      drupalLeaflet.mainLayer.addLayer(lFeature);
      drupalLeaflet.temporalDisplayedLayerGroup.addLayer(lFeature);

      // And also add this feature to our IntervalTree;
      var min = parseInt(feature.temporal.minmin, 10);
      var max = parseInt(feature.temporal.maxmax, 10);
      drupalLeaflet.temporalTree.insert(min, max, lFeature);
      drupalLeaflet.temporalStart = Math.min(drupalLeaflet.temporalStart, min);
      drupalLeaflet.temporalEnd = Math.max(drupalLeaflet.temporalEnd, max);
      drupalLeaflet.timelineControl.addItem(min, max);
      drupalLeaflet.timelineControl.recalculate();
    }
    else {
      // We don't have any temporal data, so just add the marker to the main layer.
      drupalLeaflet.lMap.removeLayer(lFeature);
      drupalLeaflet.mainLayer.addLayer(lFeature);
    }

    if (drupalLeaflet.temporalDisplayedLayerGroup.getLayers().length > 1) {
      $(drupalLeaflet.timelineContainerDiv).show();
    }
  });

})(jQuery);

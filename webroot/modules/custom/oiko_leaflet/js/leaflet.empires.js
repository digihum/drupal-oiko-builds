(function ($) {
  'use strict';

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    if (mapDefinition.hasOwnProperty('empires') && mapDefinition.empires) {
      // We need to enable the empires functionality.

      drupalLeaflet.empires = drupalLeaflet.empires || {};

      // Temporal stuff, we want a layer group to keep track of Leaflet features
      // with temporal data.
      drupalLeaflet.empires.empiresDisplayedLayerGroup = L.layerGroup();

      drupalLeaflet.empires.empiresDisplayedLayerGroup.addTo(map);

      // Instantiate an IntervalTree to make searching for what to hide/show possible.
      drupalLeaflet.empires.temporalTree = new IntervalTree();

      drupalLeaflet.empires.updateTemporalLayers = function() {
        var self = this;

        // These are the features we want on our map.
        var features = self.empires.temporalTree.lookup(Math.ceil(self.time));

        var found, layer;

        // Loop through the existing features on our map.
        for (var i = 0; i < self.empires.empiresDisplayedLayerGroup.getLayers().length; i++) {
          found = false;
          layer = self.empires.empiresDisplayedLayerGroup.getLayers()[i];
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
            self.empires.empiresDisplayedLayerGroup.removeLayer(layer);
          }
        }

        features.forEach(function (feature) {
          self.empires.empiresDisplayedLayerGroup.addLayer(feature);
        });
      };
      // Attach the above event handler to the temporalShift event.
      $(document).on('temporalShift', function(e, dl) {
        $.proxy(drupalLeaflet.empires.updateTemporalLayers, dl)();
      });


      // Go get the empire data.
      var get = $.get('/oiko_leaflet/empires/list.json');
      get.done(function(data) {
        data.forEach(function (empire) {
          var lFeature = drupalLeaflet.create_feature(empire);
          var min = parseInt(empire.temporal.minmin, 10);
          var max = parseInt(empire.temporal.maxmax, 10);
          var styleOptions = {
            stroke: false
          };
          if (empire.hasOwnProperty('empire_data')) {
            if (empire.empire_data.hasOwnProperty('color')) {
              styleOptions.color = empire.empire_data.color;
            }
            if (empire.empire_data.hasOwnProperty('opacity')) {
              styleOptions.fillOpacity = empire.empire_data.opacity;
            }
          }
          lFeature.setStyle(styleOptions);

          drupalLeaflet.empires.temporalTree.insert(min, max, lFeature);
          drupalLeaflet.timelineControl.recalculate();
        });
      });



    }

  });


})(jQuery);
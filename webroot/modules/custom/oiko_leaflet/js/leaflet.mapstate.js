(function ($) {
  'use strict';
  $(document).on('leaflet.map', function (e, mapDefinition, map, mapid) {
    var drupalLeaflet = Drupal.Leaflet[mapid];
    // If this is the first map we're processing on the page, assume that we want to capture it's page state.
    if (mapDefinition.hasOwnProperty('pagestate') && mapDefinition.pagestate) {

      var drupalLeafletInstance = $('#' + mapid).data('leaflet');
      // Replace the add_features method with one that doesn't actually add the features to the map.
      drupalLeafletInstance.add_features = function(mapid, features, initial) {
        let self = this;
        for (let i = 0; i < features.length; i++) {
          let feature = features[i];
          let lFeature;

          // dealing with a layer group
          if (feature.group) {
            let lGroup = self.create_feature_group(feature);
            for (let groupKey in feature.features) {
              let groupFeature = feature.features[groupKey];
              lFeature = self.create_feature(groupFeature);
              if (lFeature !== undefined) {
                if (lFeature.setStyle) {
                  feature.path = feature.path ? (feature.path instanceof Object ? feature.path : JSON.parse(feature.path)) : {};
                  lFeature.setStyle(feature.path);
                }
                if (groupFeature.popup) {
                  lFeature.bindPopup(groupFeature.popup);
                }
                lGroup.addLayer(lFeature);
              }
            }

            // Add the group to the layer switcher.
            self.add_overlay(feature.label, lGroup, false, mapid);
          }
          else {
            lFeature = self.create_feature(feature);
            if (lFeature !== undefined) {
              if (lFeature.setStyle) {
                feature.path = feature.path ? (feature.path instanceof Object ? feature.path : JSON.parse(feature.path)) : {};
                lFeature.setStyle(feature.path);
              }

              if (feature.popup) {
                lFeature.bindPopup(feature.popup);
              }
            }
          }

          // Allow others to do something with the feature that was just added to the map.
          $(document).trigger('leaflet.feature', [lFeature, feature, self]);
        }

        // Allow plugins to do things after features have been added.
        $(document).trigger('leaflet.features', [initial || false, self])
      };

      $(document).on('leaflet.features', function (e, initial, drupalLeaflet) {
        if (drupalLeaflet.map_definition.mapid === mapid) {
          // Fit bounds after adding features.
          setTimeout(function () {
            self.fitbounds();
          }, 250);
        }
      });

    }
  });
})(jQuery);

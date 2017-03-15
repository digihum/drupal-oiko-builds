(function ($) {
  'use strict';
  $(document).on('leaflet.map', function (e, mapDefinition, map, drupalLeaflet) {
    // If this is the first map we're processing on the page, assume that we want to capture it's page state.
    if (mapDefinition.hasOwnProperty('pagestate') && mapDefinition.pagestate) {
      // var hash = new L.Hash(map, drupalLeaflet);

      // Swap out the add_features function for one that only sets map bounds if the page hash is empty.
      drupalLeaflet.add_features = function (features, initial) {
        for (var i = 0; i < features.length; i++) {
          var feature = features[i];
          var lFeature;

          // dealing with a layer group
          if (feature.group) {
            var lGroup = this.create_feature_group(feature);
            for (var groupKey in feature.features) {
              var groupFeature = feature.features[groupKey];
              lFeature = this.create_feature(groupFeature);
              if (lFeature != undefined) {
                if (groupFeature.popup) {
                  lFeature.bindPopup(groupFeature.popup);
                }
                lGroup.addLayer(lFeature);
              }
            }

            // Add the group to the layer switcher.
            this.add_overlay(feature.label, lGroup);
          }
          else {
            lFeature = this.create_feature(feature);
            if (lFeature != undefined) {
              // if (!feature.hasOwnProperty('temporal')) {
              //   //this.lMap.addLayer(lFeature);
              // }

              // if (feature.popup) {
              //   lFeature.bindPopup(feature.popup);
              // }
            }
          }

          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this]);
        }

        // Fit bounds after adding features.
        // But only if the the location hash doesn't contain anything valid.
        // if (!hash.parseHash(location.hash)) {
        //   this.fitbounds();
        // }

        // Allow plugins to do things after features have been added.
        if (features.length) {
          $(document).trigger('leaflet.features', [initial || false, this])
        }
      };
    }
  });
})(jQuery);

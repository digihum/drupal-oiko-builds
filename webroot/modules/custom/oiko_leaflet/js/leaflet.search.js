(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {

    if (mapDefinition.hasOwnProperty('search') && mapDefinition.search) {

      var featureCache = {};

      // Build up a lovely map of Drupal feature id to lat/lon or bounds.
      $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeafletInstance) {
        var id;
        if (mapDefinition.hasOwnProperty('search') && mapDefinition.search) {
          if (feature.hasOwnProperty('id') && feature.id && typeof feature.exclude_from_temporal_layer == 'undefined') {
            id = parseInt(feature.id, 10);
            if (feature.hasOwnProperty('lat') && feature.hasOwnProperty('lon')) {
              featureCache[feature.id] = {
                lat: feature.lat,
                lon: feature.lon
              };
            }
            else if (typeof lFeature.getBounds === 'function') {
              featureCache[feature.id] = {
                bounds: lFeature.getBounds().pad(0.5)
              };
            }
            else {
              // We don't know how to handle anything else at the moment.
            }
          }
        }
      });

      // Listen for the search event on the map, used when someone selects an item for searching.
      $(window).bind('selected.map.searchitem', function (e, id) {
        var id = parseInt(id, 10);
        if (featureCache.hasOwnProperty(id)) {
          if (featureCache[id].hasOwnProperty('lat')) {
            map.panTo(L.latLng(featureCache[id].lat, featureCache[id].lon), {animate: true, duration: 0.5});
          }
          else if (featureCache[id].hasOwnProperty('bounds')) {
            map.fitBounds(featureCache[id].bounds, {animate: true, duration: 0.5});
          }
          else {
            // We don't know how to handle anything else at the moment.
          }
        }
      });

    }

  });

})(jQuery);

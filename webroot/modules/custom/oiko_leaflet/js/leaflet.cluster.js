(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    // If the map is using clustering add in the clusterer.
    if (drupalLeaflet.map_definition.hasOwnProperty('clustering') && drupalLeaflet.map_definition.clustering) {
      drupalLeaflet.clusterer = L.markerClusterGroup({
        // Make the radius of the clusters quite small.
        maxClusterRadius: 40
      });
      map.addLayer(drupalLeaflet.clusterer);

      // Set the clusterer be the main layer on the map for us.
      drupalLeaflet.mainLayer = drupalLeaflet.clusterer;
    }
    else {
      // Set the lMap to be the main layer.
      drupalLeaflet.mainLayer = drupalLeaflet.lMap;
    }
  });

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    drupalLeaflet.lMap.removeLayer(lFeature);
    drupalLeaflet.mainLayer.addLayer(lFeature);
  });

})(jQuery);

(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    // If the map is using clustering add in the clusterer.
    if (mapDefinition.hasOwnProperty('clustering') && mapDefinition.clustering) {
      drupalLeaflet.clusterer = L.markerClusterGroup({
        // Make the radius of the clusters quite small.
        maxClusterRadius: 10,

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

})(jQuery);

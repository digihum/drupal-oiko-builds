(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    // If the map is using clustering add in the clusterer.
    if (mapDefinition.hasOwnProperty('clustering') && mapDefinition.clustering) {
      drupalLeaflet.clusterFocusLayer = L.layerGroup();
      drupalLeaflet.clusterer = L.markerClusterGroup({
        // Make the radius of the clusters quite small.
        maxClusterRadius: 10,
      });

      // Add our layers to tha map.
      map.addLayer(drupalLeaflet.clusterer);
      map.addLayer(drupalLeaflet.clusterFocusLayer);

      var cloneOptions = function(options) {
        var ret = {};
        for (var i in options) {
          var item = options[i];
          if (item && item.clone) {
            ret[i] = item.clone();
          } else if (item instanceof L.Layer) {
            throw 'Unsupported option.';
          } else {
            ret[i] = item;
          }
        }
        return ret;
      }

      var cloneMarker = function (layer) {
        var options = cloneOptions(layer.options);
        // Undo the opacity that clustering adds.
        options.opacity = 1;
        // Marker layers
        if (layer instanceof L.Marker) {
          var marker = L.marker(layer.getLatLng(), options);
          // Copy over the popup.
          var p = layer.getPopup();
          if (p) {
            marker.bindPopup(p);
          }
          // Copy over the tooltip.
          var t = layer.getMedmusTooltip();
          if (t) {
            marker.bindMedmusTooltip(t);
          }
          return marker;
        }
      }

      // Move the given layer to our focus layer group.
      var moveLayerToFocusGroup = function(layer) {
        // Remove the layer from the mainlayer and add.
        var newLayer = cloneMarker(layer);
        if (newLayer) {
          drupalLeaflet.clusterFocusLayer.addLayer(newLayer);
        }
      };

      // Move all the layers in our focus group back into the main layer.
      var unFocusAllLayers = function() {
        drupalLeaflet.clusterFocusLayer.clearLayers();
      };

      if (L.Browser.touch) {
        drupalLeaflet.clusterer.on('preclick', function (e) {
          if (e.layer.isMedmusTooltipOpen()) {
            drupalLeaflet.clusterer.unspiderfy();
            moveLayerToFocusGroup(e.layer);
          }
        });
      }
      else {
        drupalLeaflet.clusterer.on('click', function (e) {
          drupalLeaflet.clusterer.unspiderfy();
          moveLayerToFocusGroup(e.layer);
        });
      }

      drupalLeaflet.clusterer.on('spiderfied', function (e) {
        unFocusAllLayers();
      });

      // Set the clusterer be the main layer on the map for us.
      drupalLeaflet.mainLayer = drupalLeaflet.clusterer;
    }
    else {
      // Set the lMap to be the main layer.
      drupalLeaflet.mainLayer = drupalLeaflet.lMap;
    }
  });

})(jQuery);

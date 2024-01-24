(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {
    var drupalLeaflet = Drupal.Leaflet[mapid];
    var drupalLeafletInstance = $('#'+ mapid).data('leaflet');

    // If the map is using clustering add in the clusterer.
    if (mapDefinition.hasOwnProperty('clustering') && mapDefinition.clustering) {
      drupalLeafletInstance.clusterFocusLayer = L.layerGroup();
      drupalLeafletInstance.clusterer = L.markerClusterGroup({
        // Make the radius of the clusters quite small.
        maxClusterRadius: 10,
      });

      // Add our layers to tha map.
      map.addLayer(drupalLeafletInstance.clusterer);
      map.addLayer(drupalLeafletInstance.clusterFocusLayer);

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

      // We have an additional layer for points that are 'in focus', i.e. have
      // been clicked on.
      // So when a marker in a spider gets clicked it will get cloned and placed
      // into this layer.
      // Further interaction with other spidered markers will clear this 'focus'
      // layer.
      // This is so that you can still 'interact' to some degree with the marker
      // even though it is within the now closed spider.

      // Move the given layer to our focus layer group.
      var moveLayerToFocusGroup = function(layer) {
        // Remove the layer from the mainlayer and add.
        var newLayer = cloneMarker(layer);
        if (newLayer) {
          drupalLeafletInstance.clusterFocusLayer.addLayer(newLayer);
        }
      };

      // Move all the layers in our focus group back into the main layer.
      var unFocusAllLayers = function() {
        drupalLeafletInstance.clusterFocusLayer.clearLayers();
      };

      if (L.Browser.touch) {
        drupalLeafletInstance.clusterer.on('preclick', function (e) {
          if (e.layer.isMedmusTooltipOpen()) {
            // Only move the layer if the target is actually in a spidered
            // collection.
            if (e.target._spiderfied) {
              drupalLeafletInstance.clusterer.unspiderfy();
              moveLayerToFocusGroup(e.layer);
            }
          }
        });
      }
      else {
        drupalLeafletInstance.clusterer.on('click', function (e) {
          // Only move the layer if the target is actually in a spidered
          // collection.
          if (e.target._spiderfied) {
            drupalLeafletInstance.clusterer.unspiderfy();
            moveLayerToFocusGroup(e.layer);
          }
        });
      }

      drupalLeafletInstance.clusterer.on('spiderfied', function (e) {
        unFocusAllLayers();
      });

      // Set the clusterer be the main layer on the map for us.
      drupalLeaflet.mainLayer = drupalLeafletInstance.clusterer;
    }
    else {
      // Set the lMap to be the main layer.
      drupalLeaflet.mainLayer = drupalLeafletInstance.lMap;
    }
  });

})(jQuery);

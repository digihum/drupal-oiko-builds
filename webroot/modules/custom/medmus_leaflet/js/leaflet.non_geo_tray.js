(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    // If the map is using non_geo_tray add in the non_geo_tray.
    if (mapDefinition.hasOwnProperty('non_geo_tray') && mapDefinition.non_geo_tray) {
      // Set up our strange medmus stuff.
      var previousSideBarRequest;
      var works = L.oikoMedmusWorksLayer(drupalLeaflet);
      works.addTo(map);

      $(window).bind('oikoSidebarOpening', function (e, id) {
        // Additionally fire off a request to get a nice set of related markers.
        if (previousSideBarRequest) {
          previousSideBarRequest.abort();
        }
        // Remove all previous data.
        works.clearLayers();
        var element_settings = {};
        element_settings.progress = {type: 'none'};
        // For anchor tags, these will go to the target of the anchor rather
        // than the usual location.
        element_settings.url = '/cidoc-entity/' + id + '/medmus-related-markers.json';
        element_settings.dataType = 'json';
        previousSideBarRequest = $.ajax(element_settings)
          .done(function (data) {
            previousSideBarRequest = null;
            // Remove all previous data.
            works.clearLayers();

            works.setData({
              sourcePoints: [
                {
                  "type": "point",
                  "label": "Source point",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Source point 1",
                  "lat": 41.2698205224768,
                  "lon": 5.499226927657332,
                  "markerClass": "oiko-leaflet-marker-work"
                }
              ],
              realTargetPoints: [
                {
                  "type": "point",
                  "label": "Real target point 1",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Real target point 1",
                  "lat": 41.2698205224768,
                  "lon": 9.499226927657332,
                  "markerClass": "oiko-leaflet-marker-work"
                },
                {
                  "type": "point",
                  "label": "Real target point 2",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Real target point 2",
                  "lat": 42.2698205224768,
                  "lon": 13.499226927657332,
                  "markerClass": "oiko-leaflet-marker-work"
                }
              ],
              realTargetLines: [
                {
                  popup: "Source 1 to Real 1",
                  source: 0,
                  target: 0,
                  forward: true,
                },
                {
                  popup: "Real 2 to Source 1",
                  source: 0,
                  target: 1,
                  forward: false,
                }
              ],
              fakeTargetPoints: [
                {
                  "type": "point",
                  "label": "Fake target point 1",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Fake target point 1",
                  "markerClass": "oiko-leaflet-marker-work"
                },
                {
                  "type": "point",
                  "label": "Fake target point 2",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Fake target point 2",
                  "markerClass": "oiko-leaflet-marker-work"
                },
                {
                  "type": "point",
                  "label": "Fake target point 3",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Fake target point 3",
                  "markerClass": "oiko-leaflet-marker-work"
                },
                {
                  "type": "point",
                  "label": "Fake target point 4",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Fake target point 4",
                  "markerClass": "oiko-leaflet-marker-work"
                },
                {
                  "type": "point",
                  "label": "Fake target point 5",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Fake target point 5",
                  "markerClass": "oiko-leaflet-marker-work"
                },
                {
                  "type": "point",
                  "label": "Fake target point 6",
                  "id": "15085",
                  "significance_id": "3",
                  "significance": "Cultural",
                  "popup": "<em>Cultural</em> <div class=\"category-label category-label--blue\">Work</div>: Fake target point 6",
                  "markerClass": "oiko-leaflet-marker-work"
                }
              ],
              fakeTargetLines: [
                {
                  popup: "Source 1 to Fake 1",
                  source: 0,
                  target: 0,
                  forward: true
                },
                {
                  popup: "Fake 2 to Source 1",
                  source: 0,
                  target: 1,
                  forward: false
                },
                {
                  popup: "Fake 3 to Source 1",
                  source: 0,
                  target: 2,
                  forward: false
                },
                {
                  popup: "Source 1 to Fake 3",
                  source: 0,
                  target: 2,
                  forward: true
                }
              ]
            });

            // We need to do display this data in the main window. Some of it is a bit...odd.
          });
      });
    }
  });


// This is the works layer plugin.
var OikoMedmusWorksLayer = L.Class.extend({
  options: {
    startX: 35,
    startY: 35,
    spacingX: 35,
    spacingY: 35,
    maxInColumn: 10,
    backgroundUIRectangleStyle: {
      fillOpacity: 1.0,
      fillColor: '#ffffff',
      weight: 2,
      color: '#000000',
      opacity: 0.2,
    }
  },
  initialize: function(drupalLeaflet, opts) {
    this.drupalLeaflet = drupalLeaflet;
    this._map = null;
    this._sourcePoints = L.featureGroup();
    this._fakeTargetPoints = L.featureGroup();
    this._realTargetPoints = L.featureGroup();
    this._fakeLines = L.featureGroup();
    this._realLines = L.featureGroup();
    this._backgroundUI = L.featureGroup();
    this._fakeTargetPointsMapping = [];
    this._fakeLinesMapping = [];
  },
  addTo: function(map) {
    this._map = map;
    map.on('move viewreset', this.mapMoved, this);
    this.proxyToAll('addTo', map);
  },
  remove: function() {
    this.removeFrom(this._map);
    this._map = null;
  },
  removeFrom(map) {
    map.off('move viewreset', this.mapMoved, this);
    if (map) {
      this.proxyToAll('removeFrom', map);
    }
  },
  proxyToAll: function(method) {
    var args = [].slice.call(arguments);
    // Remove the method argument.
    args.shift();

    var elements = [
      '_backgroundUI',
      '_sourcePoints',
      '_fakeTargetPoints',
      '_realTargetPoints',
      '_fakeLines',
      '_realLines',
    ];
    for (var i in elements) {
      this[elements[i]][method].apply(this[elements[i]], args);
    }
  },
  clearLayers: function() {
    this.proxyToAll('clearLayers');
    this._fakeTargetPointsMapping = [];
    this._fakeLinesMapping = [];
  },
  setData: function(data) {
    if (typeof data.sourcePoints != 'undefined') {
      // Set the source points.
      for (var i in data.sourcePoints) {
        var feature = data.sourcePoints[i];
        var lFeature = this.drupalLeaflet.create_feature(feature);

        if (lFeature) {
          this._sourcePoints.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
          feature.exclude_from_temporal_layer = true;
          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this.drupalLeaflet]);
        }
      }
    }

    if (typeof data.realTargetPoints != 'undefined') {
      // Set the target points.
      for (var i in data.realTargetPoints) {
        var feature = data.realTargetPoints[i];
        var lFeature = this.drupalLeaflet.create_feature(feature);

        if (lFeature) {
          this._realTargetPoints.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
          feature.exclude_from_temporal_layer = true;
          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this.drupalLeaflet]);
        }
      }
    }

    if (typeof data.realTargetLines != 'undefined') {
      // Set the target points.
      for (var i in data.realTargetLines) {
        var line = data.realTargetLines[i];
        var feature = {
          type: "linestring",
          directional: true,
          color: "deepblue",
          popup: line.popup,
          points: [],
        };

        if (typeof data.sourcePoints[line.source] != 'undefined') {
          feature.points.push({
            lat: data.sourcePoints[line.source].lat,
            lon: data.sourcePoints[line.source].lon
          });
        }

        if (typeof data.realTargetPoints[line.target] != 'undefined') {
          feature.points.push({
            lat: data.realTargetPoints[line.target].lat,
            lon: data.realTargetPoints[line.target].lon
          });
        }

        // Reverse the direction of the line if required.
        if (!line.forward) {
          feature.points.reverse();
        }

        var lFeature = this.drupalLeaflet.create_feature(feature);

        if (lFeature) {
          this._realLines.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
          feature.exclude_from_temporal_layer = true;
          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this.drupalLeaflet]);
          feature = null;
        }
      }
    }

    if (typeof data.fakeTargetPoints != 'undefined') {
      var count = data.fakeTargetPoints.length;
      var j = 0;
      // Set the target points.
      for (var i in data.fakeTargetPoints) {
        var originalFeature = feature = data.fakeTargetPoints[i];
        // Make the feature valid.
        feature.type = 'point';
        feature.popup_direction = 'auto';
        var latlng = this.getFakePointLatLng(count, j);
        feature.lat = latlng.lat;
        feature.lon = latlng.lng;
        data.fakeTargetPoints[i].lat = feature.lat;
        data.fakeTargetPoints[i].lon = feature.lon;
        var lFeature = this.drupalLeaflet.create_feature(feature);

        if (lFeature) {
          this._fakeTargetPoints.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
          feature.exclude_from_temporal_layer = true;
          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this.drupalLeaflet]);
          this._fakeTargetPointsMapping.push({
            i: i,
            j: j++,
            originalFeature: originalFeature,
            lFeature: lFeature
          });
        }

      }
    }

    if (typeof data.fakeTargetLines != 'undefined') {
      // Set the target points.
      for (var i in data.fakeTargetLines) {
        var line = data.fakeTargetLines[i];
        var feature = {
          type: "linestring",
          directional: true,
          color: "deepblue",
          popup: line.popup,
          points: [],
        };

        if (typeof data.sourcePoints[line.source] != 'undefined') {
          feature.points.push({
            lat: data.sourcePoints[line.source].lat,
            lon: data.sourcePoints[line.source].lon
          });
        }

        if (typeof data.fakeTargetPoints[line.target] != 'undefined') {
          feature.points.push({
            lat: data.fakeTargetPoints[line.target].lat,
            lon: data.fakeTargetPoints[line.target].lon
          });
        }

        // Reverse the direction of the line if required.
        if (!line.forward) {
          feature.points.reverse();
        }

        var lFeature = this.drupalLeaflet.create_feature(feature);

        if (lFeature) {
          this._fakeLines.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
          feature.exclude_from_temporal_layer = true;
          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this.drupalLeaflet]);

          // Find the fake endpoint, as we want a reference to the lFeature.
          var fakeEndpointMapping;
          for (var k in this._fakeTargetPointsMapping) {
            if (this._fakeTargetPointsMapping[k].i == line.target) {
              fakeEndpointMapping = this._fakeTargetPointsMapping[k];
            }
          }
          this._fakeLinesMapping.push({
            fakeEndpointMapping: fakeEndpointMapping,
            forward: line.forward,
            originalFeature: originalFeature,
            lFeature: lFeature
          });
        }
      }
    }
    this.mapMoved();
  },
  getFakePointLatLng: function(totalPoints, pointIdx) {
    return this._map.containerPointToLatLng([
      this.options.startX + Math.floor(pointIdx / this.options.maxInColumn) * this.options.spacingX,
      this.options.startY + (pointIdx % this.options.maxInColumn) * this.options.spacingY
    ]);
  },
  mapMoved: function() {
    // Recompute and move the fake points.
    for (var i in this._fakeTargetPointsMapping) {
      var thisMap = this._fakeTargetPointsMapping[i];
      thisMap.lFeature.setLatLng(this.getFakePointLatLng(this._fakeTargetPointsMapping.length, thisMap.j));
    }
    // Recompute and move the fake lines.
    for (var i in this._fakeLinesMapping) {
      var thisMap = this._fakeLinesMapping[i];
      // Get the latlngs.
      var latlngs = thisMap.lFeature.getLatLngs();
      if (thisMap.forward) {
        latlngs = latlngs.slice(0, 1);
        latlngs.push(thisMap.fakeEndpointMapping.lFeature.getLatLng());
      }
      else {
        latlngs = latlngs.slice(1, 2);
        latlngs.unshift(thisMap.fakeEndpointMapping.lFeature.getLatLng());
      }
      thisMap.lFeature.setLatLngs(latlngs);
    }
    this.updateBackgroundUI();
  },
  updateBackgroundUI: function() {
    if (this._fakeTargetPointsMapping.length) {
      // Ensure the background UI exists and is covering all the points.
      var topLeft = this._map.containerPointToLatLng([
        this.options.startX - 25,
        this.options.startY - 25
      ]);
      var bottomRight = this._map.containerPointToLatLng([
        this.options.startX + Math.ceil(this._fakeTargetPointsMapping.length / this.options.maxInColumn) * this.options.spacingX - 10,
        this.options.startY + this.options.spacingY * Math.min(this.options.maxInColumn, this._fakeTargetPointsMapping.length) - 10
      ]);
      if (this._backgroundUI.getLayers().length == 0) {
        var rect = L.rectangle([topLeft, bottomRight], this.options.backgroundUIRectangleStyle)
        this._backgroundUI.addLayer(rect);
        var tooltipText = 'Non-geographic events';
        rect.bindTooltip(tooltipText, {direction: 'auto', opacity: 1, sticky: true, permanent: false, interactive: true});
      }
      else {
        var corners = [
          [topLeft.lat, topLeft.lng],
          [bottomRight.lat, topLeft.lng],
          [bottomRight.lat, bottomRight.lng],
          [topLeft.lat, bottomRight.lng],
        ];
        this._backgroundUI.eachLayer(function (layer) {
          layer.setLatLngs(corners);
        });
      }
      this._backgroundUI.eachLayer(function (layer) {
        layer.bringToBack();
      });
    }
    else {
      // Remove the background UI.
      this._backgroundUI.clearLayers();
    }
  }
});

L.oikoMedmusWorksLayer = function(opts) {
  return new OikoMedmusWorksLayer(opts);
}

})(jQuery);

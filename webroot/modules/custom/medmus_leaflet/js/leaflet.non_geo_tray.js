(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {
    var drupalLeafletInstance = $('#'+ mapid).data('leaflet');

    // If the map is using non_geo_tray add in the non_geo_tray.
    if (mapDefinition.hasOwnProperty('non_geo_tray') && mapDefinition.non_geo_tray) {
      var previousSideBarRequest;
      var works = L.oikoMedmusWorksLayer(drupalLeafletInstance);
      works.addTo(map);
      var oikoLoaded = false;

      var previousLoad;
      var loadData = function(data) {
        if (previousLoad) {
          clearTimeout(previousLoad);
          previousLoad = null;
        }
        if (oikoLoaded) {
          // Remove all previous data.
          works.clearLayers();
          works.setData(data);
        }
        else {
          // Wait half a second before trying again.
          previousLoad = setTimeout(function() {
            loadData(data)
          }, 500);
        }
      }

      // Wait for the oiko app to have loaded.
      $(window).bind('oiko.loaded', function () {
        oikoLoaded = true;
      });

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
            loadData(data);
          });
      });
    }
  });


// This is the works layer plugin.
var OikoMedmusWorksLayer = L.Class.extend({
  options: {
    startX: 35,
    startY: 85,
    spacingX: 35,
    spacingY: 35,
    maxInColumn: 10,
    backgroundUIRectangleStyle: {
      fillOpacity: 1.0,
      fillColor: '#ffffff',
      weight: 2,
      color: '#000000',
      opacity: 0.2,
    },
    pane: 'nonGeoTray',
  },
  initialize: function(drupalLeaflet, opts) {
    this.drupalLeaflet = drupalLeaflet;
    this._map = null;
    this._sourcePoints = L.layerGroup();
    this._fakeTargetPoints = L.layerGroup();
    this._realTargetPoints = L.layerGroup();
    this._fakeLines = L.layerGroup();
    this._realLines = L.layerGroup();
    this._backgroundUI = L.layerGroup();
    this._fakeTargetPointsMapping = [];
    this._fakeLinesMapping = [];
  },
  addTo: function(map) {
    this._map = map;
    map.on('move viewreset', this.mapMoved, this);
    if (!map.getPane(this.options.pane)) {
      var pane = map.createPane(this.options.pane);
      pane.style.zIndex = 649;
    }
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
    var feature, lFeature, i;

    if (typeof data.sourcePoints != 'undefined') {
      // Set the source points.
      for (i in data.sourcePoints) {
        feature = data.sourcePoints[i];
        feature.pane = this.options.pane;
        feature.popup_direction = 'top';
        feature.popupAggregated = true;
        lFeature = this.drupalLeaflet.create_feature(feature);

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
      for (i in data.realTargetPoints) {
        feature = data.realTargetPoints[i];
        feature.pane = this.options.pane;
        feature.popup_direction = 'top';
        feature.popupAggregated = true;
        lFeature = this.drupalLeaflet.create_feature(feature);

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
          pane: this.options.pane,
          zIndexOffset: 900,
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
      var count = Object.keys(data.fakeTargetPoints).length;
      var j = 0;
      // Set the target points.
      for (var i in data.fakeTargetPoints) {
        var originalFeature = feature = data.fakeTargetPoints[i];
        // Make the feature valid.
        feature.type = 'point';
        feature.popup_direction = 'auto';
        feature.pane = this.options.pane;
        feature.zIndexOffset = 1000;
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
          zIndexOffset: 900,
          pane: this.options.pane,
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
      if (this._backgroundUI.getLayers().length === 0) {
        this.options.backgroundUIRectangleStyle.pane = this.options.pane;
        var rect = L.rectangle([topLeft, bottomRight], this.options.backgroundUIRectangleStyle)
        this._backgroundUI.addLayer(rect);
        var tooltipText = 'Non-geographic events';
        rect.bindMedmusTooltip(tooltipText, {direction: 'auto', opacity: 1, sticky: true, permanent: false, interactive: true});
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

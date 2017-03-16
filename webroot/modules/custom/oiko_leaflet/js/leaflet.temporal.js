(function ($) {
  'use strict';

  Drupal.oiko.addAppModule('temporal');

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {

      drupalLeaflet.timeSelectionWindowSize = 365.25 * 86400 / 2;

      // Add a timeline control to the map.
      drupalLeaflet.timelineControl = new L.TimeLineControl({
        temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize
      });
      drupalLeaflet.timelineControl.addTo(map);

      // We have a target layer that we want to add our feature to.
      drupalLeaflet.temporalDisplayedLayerGroup = L.featureGroup.subGroup(drupalLeaflet.mainLayer);
      drupalLeaflet.temporalDisplayedLayerGroup.addTo(map);
      // And then we have a TemporalLayerHelper that will add a remove layers to our layer group above.
      drupalLeaflet.temporalDisplayedLayerHelper = L.temporalLayerHelper(drupalLeaflet.temporalDisplayedLayerGroup, {temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize});
      drupalLeaflet.temporalDisplayedLayerHelper.addTo(map);


      $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
        if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
          if (feature.hasOwnProperty('temporal')) {
            lFeature.temporal = {
              start: parseInt(feature.temporal.minmin, 10),
              end: parseInt(feature.temporal.maxmax, 10)
            };
          }
          // Add this feature to the temporal layer group.
          drupalLeaflet.temporalDisplayedLayerHelper.addLayer(lFeature, feature);
        }
      });

      $(document).on('leaflet.features', function(e, initial, drupalLeaflet) {
        if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
          Drupal.oiko.appModuleDoneLoading('temporal');
        }
      });

      // @TODO: move this to the TimelineControl.
      drupalLeaflet.changeTimeToNearestWindow = function (window_start, window_end) {
        var windowsize = drupalLeaflet.timeSelectionWindowSize;
        var time = this.timelineControl.getTime();
        var min = Math.floor(time - windowsize);
        var max = Math.ceil(time + windowsize);
        // If we're not overlapping, change the time by the smallest amount.
        if (!(window_start <= max && window_end >= min)) {
          if (window_start > max) {
            this.timelineControl.setTime(window_start - windowsize + 1);
          }
          else {
            this.timelineControl.setTime(window_end + windowsize - 1);
          }
        }
      };

      // Search support.
      if (drupalLeaflet.map_definition.hasOwnProperty('search') && drupalLeaflet.map_definition.search || drupalLeaflet.map_definition.hasOwnProperty('sidebar') && drupalLeaflet.map_definition.sidebar) {
        var featureCache = {};

        // Build up a lovely map of Drupal feature id to a timestamp.
        $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
          if (drupalLeaflet.map_definition.hasOwnProperty('search') && drupalLeaflet.map_definition.search || drupalLeaflet.map_definition.hasOwnProperty('sidebar') && drupalLeaflet.map_definition.sidebar) {
            if (feature.hasOwnProperty('id') && feature.id) {
              if (feature.hasOwnProperty('temporal')) {
                featureCache[feature.id] = {
                  min: parseInt(feature.temporal.minmin, 10),
                  max: parseInt(feature.temporal.maxmax, 10),
                };
              }
            }
          }
        });

        // Listen for the searchItem event on the map, used when someone selects an item for searching.
        map.addEventListener('searchItem', function (e) {
          var id = e.properties.id;
          if (featureCache.hasOwnProperty(id)) {
            if (featureCache[id].hasOwnProperty('min') && featureCache[id].hasOwnProperty('max')) {
              drupalLeaflet.changeTimeToNearestWindow.call(drupalLeaflet, featureCache[id].min, featureCache[id].max);
            }
          }
        });

        // Listen for the sidebar being opened, and ensure that our time is correct.
        $(window).bind('oikoSidebarOpening', function(e, id) {
          if (featureCache.hasOwnProperty(id)) {
            if (featureCache[id].hasOwnProperty('min') && featureCache[id].hasOwnProperty('max')) {
              drupalLeaflet.changeTimeToNearestWindow.call(drupalLeaflet, featureCache[id].min, featureCache[id].max);
            }
          }
        });

      }
    }
    else {
      Drupal.oiko.appModuleDoneLoading('temporal');
    }
  });

})(jQuery);

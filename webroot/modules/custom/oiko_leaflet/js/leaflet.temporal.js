(function ($) {
  'use strict';

  Drupal.oiko.addAppModule('temporal');

  // Declare this variable here so we can use it in our filtering callback and
  // be able to update it when the categories change.
  var displayedCategories = [];
  var filterForCategories = function(layer, feature) {
    // Ensure that we really, really are using a number.
    var featureCategory = parseInt(feature.significance_id ? feature.significance_id : 0, 10);
    // If we have selected nothing, show everything.
    if (displayedCategories.length === 0) {
      return true;
    }
    else {
      return displayedCategories.indexOf(featureCategory) !== -1;
    }
  };
  var updateDisplayedCategories = function (categories) {
    // Make sure categories are numeric.
    displayedCategories = [];
    var parsed;
    for (var i = 0;i < categories.length;i++) {
      parsed = parseInt(categories[i], 10);
      if (!isNaN(parsed)) {
        displayedCategories.push(parsed);
      }
    }
  };

  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {
    var drupalLeaflet = Drupal.Leaflet[mapid];

    window.globalDrupalLeaflet = drupalLeaflet;
    if (mapDefinition.hasOwnProperty('timeline') && mapDefinition.timeline) {
      // Half a year, either side of the selection point, thus a 1 year window.
      drupalLeaflet.timeSelectionWindowSize = 365.25 * 86400 / 2;

      // Add a timeline control to the map.
      drupalLeaflet.timelineControl = new L.TimeLineControl({
        temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize
      });
      drupalLeaflet.timelineControl.addTo(map);

      // And then we have a TemporalLayerHelper that will add and remove layers
      // to and from our mainLayer.
      drupalLeaflet.temporalDisplayedLayerHelper = L.temporalLayerHelper(drupalLeaflet.mainLayer, {temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize});
      drupalLeaflet.temporalDisplayedLayerHelper.addTo(map);

      // We have a target layer helper that we want to add our feature to, this
      // handles filtering of the base data, adding/removing it from from the
      // temporal layer helper created above.
      drupalLeaflet.filteringLayerHelper = drupalLeaflet.filteringLayerHelper || [];
      drupalLeaflet.filteringLayerHelper = L.filterableLayerHelper(drupalLeaflet.temporalDisplayedLayerHelper, {}, drupalLeaflet.filteringLayerHelper);
      drupalLeaflet.filteringLayerHelper.addFilteringCallback(filterForCategories);


      $(window).bind('set.oiko.categories', function(e, categories) {
        // Update our record of the categories displayed.
        updateDisplayedCategories(categories);
        // Force the helper to recompute the status, this might be slow, so do
        // on the next browser process tick.
        setTimeout(function() {
          drupalLeaflet.filteringLayerHelper.recomputeFilteredItems();
        }, 25);
      });

      $(document).on('leaflet.feature', function(e, lFeature, feature, leafletInstance) {
        if (leafletInstance.map_definition.hasOwnProperty('timeline') && leafletInstance.map_definition.timeline) {
          if (feature.hasOwnProperty('temporal')) {
            lFeature.temporal = {
              start: parseInt(feature.temporal.minmin, 10),
              end: parseInt(feature.temporal.maxmax, 10)
            };
          }
          if (typeof feature.exclude_from_temporal_layer == 'undefined') {
            // Add this feature to the temporal layer group.
            Drupal.Leaflet[leafletInstance.mapid].filteringLayerHelper
              .addLayer(lFeature, feature);
          }
        }
      });

      $(document).on('leaflet.features', function(e, initial, drupalLeaflet) {
        if (mapDefinition.hasOwnProperty('timeline') && mapDefinition.timeline) {
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
      if (mapDefinition.hasOwnProperty('search') && mapDefinition.search || mapDefinition.hasOwnProperty('sidebar') && mapDefinition.sidebar) {
        var featureCache = {};

        // Build up a lovely map of Drupal feature id to a timestamp.
        $(document).on('leaflet.feature', function(e, lFeature, feature) {
          var id;
          if (mapDefinition.hasOwnProperty('search') && mapDefinition.search || mapDefinition.hasOwnProperty('sidebar') && mapDefinition.sidebar) {
            if (feature.hasOwnProperty('id') && feature.id && typeof feature.exclude_from_temporal_layer == 'undefined') {
              var id = parseInt(feature.id, 10);
              if (feature.hasOwnProperty('temporal')) {
                featureCache[feature.id] = {
                  min: parseInt(feature.temporal.minmin, 10),
                  max: parseInt(feature.temporal.maxmax, 10)
                };
              }
            }
          }
        });

        // Listen for the searchItem event on the map, used when someone selects an item for searching.
        $(window).bind('selected.map.searchitem', function (e, id) {
          if (featureCache.hasOwnProperty(id)) {
            if (featureCache[id].hasOwnProperty('min') && featureCache[id].hasOwnProperty('max')) {
              drupalLeaflet.changeTimeToNearestWindow.call(drupalLeaflet, featureCache[id].min, featureCache[id].max);
            }
          }
        });

        // Listen for the sidebar being opened, and ensure that our time is correct.
        $(window).bind('oikoSidebarOpening', function(e, id) {
          var id = parseInt(id, 10);
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

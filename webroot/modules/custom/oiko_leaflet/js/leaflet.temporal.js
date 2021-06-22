(function ($) {
  'use strict';

  Drupal.oiko.addAppModule('temporal');

  var ensureTemporalLayerGroup = function(map, drupalLeaflet, id) {

    if (typeof drupalLeaflet.temporalDisplayedLayerGroups[id] === 'undefined') {
      // We have a target layer that we want to add our feature to.
      drupalLeaflet.temporalDisplayedLayerGroups[id] = L.featureGroup.subGroup(drupalLeaflet.mainLayer);
      drupalLeaflet.temporalDisplayedLayerGroups[id].addTo(map);
      // And then we have a TemporalLayerHelper that will add a remove layers to our layer group above.
      drupalLeaflet.temporalDisplayedLayerHelpers[id] = L.temporalLayerHelper(drupalLeaflet.temporalDisplayedLayerGroups[id], {temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize});
      drupalLeaflet.temporalDisplayedLayerHelpers[id].addTo(map);
    }

    // We want our features added to the layer helpers.
    return drupalLeaflet.temporalDisplayedLayerHelpers[id];
  };

  /**
   * Handle the categories that the oiko App wants to display changing.
   *
   * We need to add and remove layers from the map as needed.
   *
   * @param categories
   *   The list of category IDs to display, or if empty, display all categories.
   */
  var handleCatergoryChange = function(map, layers, categories) {
    // Make sure categories are numeric.
    var numericCategories = [];
    for (var i = 0;i < categories.length;i++) {
      numericCategories.push(parseInt(categories[i], 10));
    }

    if (numericCategories.length === 0) {
      // Display all the feature groups.
      for (i in layers) {
        layers[i].addTo(map);
      }
    }
    else {
      var iNumeric;
      // Walk the feature groups and hide/display as needed.
      for (i in layers) {
        iNumeric = parseInt(i, 10);
        if (numericCategories.indexOf(iNumeric) > -1) {
          // This is displayed, ensure it is.
          layers[i].addTo(map);
        }
        else {
          // This category should be hidden.
          layers[i].removeFrom(map);
        }
      }
    }
  };

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    window.globalDrupalLeaflet = drupalLeaflet;

    if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {

      drupalLeaflet.timeSelectionWindowSize = 365.25 * 86400 / 2;

      // Add a timeline control to the map.
      drupalLeaflet.timelineControl = new L.TimeLineControl({
        temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize
      });
      drupalLeaflet.timelineControl.addTo(map);

      drupalLeaflet.temporalDisplayedLayerGroups = drupalLeaflet.temporalDisplayedLayerGroups || {};
      drupalLeaflet.temporalDisplayedLayerHelpers = drupalLeaflet.temporalDisplayedLayerHelpers || {};

      $(window).bind('set.oiko.categories', function(e, categories) {
        handleCatergoryChange(map, drupalLeaflet.temporalDisplayedLayerGroups, categories);
        handleCatergoryChange(map, drupalLeaflet.temporalDisplayedLayerHelpers, categories);
      });

      $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
        if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
          if (feature.hasOwnProperty('temporal')) {
            lFeature.temporal = {
              start: parseInt(feature.temporal.minmin, 10),
              end: parseInt(feature.temporal.maxmax, 10)
            };
          }
          if (typeof feature.exclude_from_temporal_layer == 'undefined') {
            // Add this feature to the temporal layer group.
            ensureTemporalLayerGroup(map, drupalLeaflet, feature.significance_id ? feature.significance_id : 0).addLayer(lFeature, feature);
          }
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
          var id;
          if (drupalLeaflet.map_definition.hasOwnProperty('search') && drupalLeaflet.map_definition.search || drupalLeaflet.map_definition.hasOwnProperty('sidebar') && drupalLeaflet.map_definition.sidebar) {
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

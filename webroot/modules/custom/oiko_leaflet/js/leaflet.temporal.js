(function ($) {
  'use strict';

  Drupal.oiko.addAppModule('temporal');

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {

      // This stuff is sort of a mess!
      // Sorry, working my best to refactor, honest!.

      drupalLeaflet.timeSelectionWindowSize = 365.25 * 86400 / 2;

      // Temporal stuff, we want a layer group to keep track of Leaflet features
      // with temporal data.
      // drupalLeaflet.temporalDisplayedLayerGroup = L.layerGroup.temporal({temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize});

      var targetLayer = L.featureGroup.subGroup(drupalLeaflet.mainLayer);
      targetLayer.addTo(map);
      var layerHelper = L.temporalLayerHelper(targetLayer, {temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize});
      layerHelper.addTo(map);

      // drupalLeaflet.clusterer.checkIn(drupalLeaflet.temporalDisplayedLayerGroup);
      // drupalLeaflet.temporalDisplayedLayerGroup.addTo(map);
      drupalLeaflet.temporalDisplayedLayerGroup = L.featureGroup.subGroup(drupalLeaflet.mainLayer);
      drupalLeaflet.temporalDisplayedLayerGroup.addTo(map);
      drupalLeaflet.temporalDisplayedLayerHelper = L.temporalLayerHelper(targetLayer, {temporalRangeWindow: drupalLeaflet.timeSelectionWindowSize});
      drupalLeaflet.temporalDisplayedLayerHelper.addTo(map);

      // Instantiate an IntervalTree to make searching for what to hide/show easier.
      drupalLeaflet.temporalTree = new IntervalTree();
      drupalLeaflet.temporalStart = Infinity;
      drupalLeaflet.temporalEnd = -Infinity;
      drupalLeaflet.rangeStart = Infinity;
      drupalLeaflet.rangeEnd = -Infinity;

      drupalLeaflet.drawOnSetTime = true;

      drupalLeaflet.changeTime = function (time) {
        time = typeof time === 'number' ? time : new Date(time).getTime();
        this.timelineControl.changeTime(time);
      };

      drupalLeaflet.changeTimeToNearestWindow = function (window_start, window_end) {
        var windowsize = drupalLeaflet.timeSelectionWindowSize;
        var time = this.timelineControl.getTime();
        var min = Math.floor(time - windowsize);
        var max = Math.ceil(time + windowsize);
        // If we're not overlapping, change the time by the smallest amount.
        if (!(window_start <= max && window_end >= min)) {
          if (window_start > max) {
            this.timelineControl.changeTime(window_start - windowsize + 1);
          }
          else {
            this.timelineControl.changeTime(window_end + windowsize - 1);
          }
        }
      };

      drupalLeaflet.changeTimeAndWindow = function (time, start, end) {
        time = typeof time === 'number' ? time : new Date(time).getTime();
        start = typeof start === 'number' ? start : new Date(start).getTime();
        end = typeof end === 'number' ? end : new Date(end).getTime();
        this.timelineControl.setTimeAndWindow(time, start, end);
      };

      drupalLeaflet.updateTime = function (time) {
        this.time = typeof time === 'number' ? time : new Date(time).getTime();
        if (this.drawOnSetTime) {
          this.updateTemporalLayersTrigger();
        }
      };

      drupalLeaflet.timelineControlDoneChanged = function (time) {
        // Fire an event so that anyone can respond.
        $(document).trigger('temporalShifted', [this]);
        // And fire an event on the map.
        map.fire('temporalShifted', this);
      };

      drupalLeaflet.timelineControlRangedChangedDone = function (start, end) {
        this.rangeStart = start;
        this.rangeEnd = end;
        // Fire an event so that anyone can respond.
        $(document).trigger('temporalRangeChange', [this]);
        // And fire an event on the map.
        map.fire('temporalRangeChange', this);
      };

      drupalLeaflet.getTime = function () {
        return this.time;
      };

      drupalLeaflet.timelineControl = new L.TimeLineControl({
        formatOutput: function (date) {
          return new Date(date).toString();
        },
        drupalLeaflet: drupalLeaflet
      });

      // Add an extra div, and plonk the timeline widget in it.
      drupalLeaflet.timelineContainerDiv = drupalLeaflet.timelineControl.onAdd(map);
      $(drupalLeaflet.container).after(drupalLeaflet.timelineContainerDiv);
      $(drupalLeaflet.timelineContainerDiv).hide();
      drupalLeaflet.timelineControl.addTimeline(
        drupalLeaflet.temporalTree,
        $.proxy(drupalLeaflet.updateTime, drupalLeaflet),
        $.proxy(drupalLeaflet.timelineControlDoneChanged, drupalLeaflet),
        $.proxy(drupalLeaflet.timelineControlRangedChangedDone, drupalLeaflet)
      );


      drupalLeaflet.updateTemporalLayersTrigger = function () {
        // Fire an event so that anyone can respond.
        $(document).trigger('temporalShift', [this]);
        // And fire an event on the map.
        map.fire('temporalShift', this);
        map.fire('temporal.shift', {time: this.time});
      };

      drupalLeaflet.recalculateTemporalBounds = function (min, max) {
        drupalLeaflet.temporalStart = Math.min(drupalLeaflet.temporalStart, min);
        drupalLeaflet.temporalEnd = Math.max(drupalLeaflet.temporalEnd, max);
      };

      $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
        if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
          if (feature.hasOwnProperty('temporal')) {

            lFeature.temporal = {
              start: parseInt(feature.temporal.minmin, 10),
              end: parseInt(feature.temporal.maxmax, 10)
            };

            drupalLeaflet.recalculateTemporalBounds(lFeature.temporal.start, lFeature.temporal.end);
            drupalLeaflet.timelineControl.addItem(lFeature.temporal.start, lFeature.temporal.end);
          }
          // Add this feature to the temporal layer group.
          drupalLeaflet.temporalDisplayedLayerHelper.addLayer(lFeature, feature);
        }
      });

      $(document).on('leaflet.features', function(e, initial, drupalLeaflet) {
        if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
          drupalLeaflet.timelineControl.updateTimelineWithData();

          var previousTime = drupalLeaflet.getTime();
          drupalLeaflet.timelineControl.recalculate();
          if (previousTime) {
            drupalLeaflet.changeTime(previousTime);
          }
          // if (drupalLeaflet.temporalDisplayedLayerGroup.getLayers().length > 1) {
            $(drupalLeaflet.timelineContainerDiv).show();
          // }
          Drupal.oiko.appModuleDoneLoading('temporal');
        }
      });


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

(function ($) {
  'use strict';
L.TimeLineControl = L.Control.extend({
  options: {
    position: 'bottomleft'
  },
  initialize: function initialize() {
    var options = arguments.length <= 0 || arguments[0] === undefined ? {} : arguments[0];

    var defaultOptions = {
      duration: 10000,
      enableKeyboardControls: false,
      enablePlayback: false,
      formatOutput: function formatOutput(output) {
        return '' + (output || '');
      },
      showTicks: false,
      waitToUpdateMap: false,
      position: 'bottomleft',
      steps: 1000,
      drupalLeaflet: {
        temporalStart: false,
        temporalEnd: false
      }
    };
    this.timelines = [];
    this.callbacks = [];
    L.Util.setOptions(this, defaultOptions);
    L.Util.setOptions(this, options);
    if (typeof options.start !== 'undefined') {
      this.start = options.start;
    }
    if (typeof options.end !== 'undefined') {
      this.end = options.end;
    }
  },
  /**
   * Create all of the DOM for the control.
   *
   * @private
   */
  _createDOM: function _createDOM() {
    var classes = ['leaflet-control-layers', 'leaflet-control-layers-expanded', 'leaflet-timeline-control'];
    var container = L.DomUtil.create('div', classes.join(' '));
    this.container = container;
    this._makeSlider(container);
    if (this.options.showTicks) {
      // this._buildDataList(container);
    }
  },
  /**
   * Creates the range input
   *
   * @private
   * @param {HTMLElement} container The container to which to add the input
   */
  _makeSlider: function _makeSlider(container) {
    var _this4 = this;

    var options = {
      width:  "100%",
      stack: false,
      showCurrentTime: false
    };

    // Create a Timeline
    this._visItems = new vis.DataSet([]);
    this._visTimeline = new vis.Timeline(container, this._visItems, options);
    var customDate = new Date();
    customDate = new Date(customDate.getFullYear(), customDate.getMonth(), customDate.getDate() + 1);
    this._visTimeline.addCustomTime(customDate, 'tdrag');
    // Set timeline time change event, so cursor is set after moving custom time (blue)
    this._visTimeline.on('timechange', function(e) {
      if (e.id === 'tdrag') {
        _this4._visTimelineChanged(e);
      }
    });
  },
  _visTimelineChanged: function _visTimelineChanged(properties) {
    var time = Math.round(properties.time.getTime() / 1000);
    this.time = time;
    if (!this.options.waitToUpdateMap || e.type === 'change') {
      this.callbacks.forEach(function (cb) {
        return cb(time);
      });
    }
  },
  _sliderChanged: function _sliderChanged(e) {
    var time = parseFloat(e.target.value, 10);
    this.time = time;
    if (!this.options.waitToUpdateMap || e.type === 'change') {
      this.callbacks.forEach(function (cb) {
        return cb(time);
      });
    }
    if (this._output) {
      // this._output.innerHTML = this.options.formatOutput(time);
    }
  },
  addItem: function addItem(min, max) {
    var obj = {
      start: min * 1000,
      end: max * 1000,
      type: 'background'
    };
    this._visItems.add(obj);
  },
  addTimeline: function addTimeline(timeline, cb) {
    var _this = this;

    // this.pause();
    var timelineCount = this.timelines.length;

    if (_this.timelines.indexOf(timeline) === -1) {
      _this.timelines.push(timeline);
      _this.callbacks.push(cb);
    }
    if (this.timelines.length !== timelineCount) {
      this._recalculate();
    }
  },
  /**
   * Adjusts start/end/step size/etc. Should be called if any of those might
   * change (e.g. when adding a new layer).
   *
   * @private
   */
  _recalculate: function _recalculate() {
    var manualStart = typeof this.options.start !== 'undefined';
    var manualEnd = typeof this.options.end !== 'undefined';
    var duration = this.options.duration;
    var min = Infinity;
    var max = -Infinity;
    if (typeof this.options.drupalLeaflet.temporalStart !== 'undefined') {
      min = this.options.drupalLeaflet.temporalStart;
    }
    if (typeof this.options.drupalLeaflet.temporalEnd !== 'undefined') {
      max = this.options.drupalLeaflet.temporalEnd;
    }
    if (!manualStart) {
      this.start = min;
    }
    if (!manualEnd) {
      this.end = max;
    }
    if (min != Infinity && max != Infinity) {
      this._visTimeline.setOptions({
        start: 1000 * min,
        min: 1000 * min,
        end: 1000 * max,
        max: 1000 * max
      });
      this._visTimeline.setCustomTime(500 * (min + max) , 'tdrag');
      this.setTime(Math.round((min + max)/2));
    }
  },
  recalculate: function recalculate() {
    this._recalculate();
  },
  /**
   * Set the time displayed.
   *
   * @param {Number} time The time to set
   */
  setTime: function setTime(time) {
    this._sliderChanged({
      type: 'change',
      target: { value: time }
    });
  },
  onAdd: function onAdd(map) {
    this.map = map;
    this._createDOM();
    this.setTime(this.start);
    return this.container;
  },
  onRemove: function onRemove() {
    if (this.options.enableKeyboardControls) {
      this._removeKeyListeners();
    }
  }


});

L.timeLineControl = function (timeline, start, end, timelist) {
  return new L.TimeLineControl(timeline, start, end, timelist);
};


})(jQuery);



(function ($) {
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    drupalLeaflet.clusterer = L.markerClusterGroup({
      // Make the radius of the clusters quite small.
      maxClusterRadius: 40
    });
    map.addLayer(drupalLeaflet.clusterer);

    // Set the clusterer be the main layer on the map for us.
    drupalLeaflet.mainLayer = drupalLeaflet.clusterer;

    // Temporal stuff, we want a layer group to keep track of Leaflet features
    // with temporal data.
    drupalLeaflet.temporalDisplayedLayerGroup = L.layerGroup();

    // Instantiate an IntervalTree to make searching for what to hide/show easier.
    drupalLeaflet.temporalTree = new IntervalTree();
    drupalLeaflet.temporalStart = Infinity;
    drupalLeaflet.temporalEnd = -Infinity;

    drupalLeaflet.drawOnSetTime = true;

    drupalLeaflet.updateTime = function(time) {
      this.time = typeof time === 'number' ? time : new Date(time).getTime();
      if (this.drawOnSetTime) {
        this.updateTemporalLayersTrigger();
      }
    };

    drupalLeaflet.timelineControl = new L.TimeLineControl({
      formatOutput: function(date){
        return new Date(date).toString();
      },
      drupalLeaflet: drupalLeaflet
    });

    // Add an extra div, and plonk the timeline widget in it.
    drupalLeaflet.timelineControl._map = map;
    drupalLeaflet.timelineContainerDiv = drupalLeaflet.timelineControl.onAdd(map);
    $(drupalLeaflet.container).after(drupalLeaflet.timelineContainerDiv);
    $(drupalLeaflet.timelineContainerDiv).hide();
    drupalLeaflet.timelineControl.addTimeline(drupalLeaflet.temporalTree, $.proxy(drupalLeaflet.updateTime, drupalLeaflet));


    drupalLeaflet.updateTemporalLayersTrigger = function() {
      // Fire an event so that anyone can respond.
      $(document).trigger('temporalShift', [this]);
    };

    drupalLeaflet.updateTemporalLayers = function() {
      var self = this;

      // Show half a year either side of our selection.
      var offset = 365.25 * 86400 / 2;
      // These are the features we want on our map.
      var features = self.temporalTree.overlap(Math.floor(self.time - offset), Math.ceil(self.time + offset));
      
      var found, layer;

      // Loop through the existing features on our map.
      for (var i = 0; i < self.temporalDisplayedLayerGroup.getLayers().length; i++) {
        found = false;
        layer = self.temporalDisplayedLayerGroup.getLayers()[i];
        // Search for this layer in our set of features we do want.
        for (var j = 0; j < features.length; j++) {
          if (features[j] === layer) {
            found = true;
            features.splice(j, 1);
            break;
          }
        }
        if (!found) {
          // We didn't find this layer, so remove it and decrement i, so we process this i again.
          i--;
          self.mainLayer.removeLayer(layer);
          self.temporalDisplayedLayerGroup.removeLayer(layer);
        }
      }

      features.forEach(function (feature) {
        self.mainLayer.addLayer(feature);
        self.temporalDisplayedLayerGroup.addLayer(feature);
      });
    };
    $(document).on('temporalShift', function(e, dl) {
      $.proxy(drupalLeaflet.updateTemporalLayers, dl)();
    });
  });

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    if (feature.hasOwnProperty('temporal')) {
      // We need to remove this feature from the map, and add it to our special LayerGroup.
      drupalLeaflet.lMap.removeLayer(lFeature);
      drupalLeaflet.temporalDisplayedLayerGroup.addLayer(lFeature);


      // And also add this feature to our IntervalTree;
      var min = parseInt(feature.temporal.minmin, 10);
      var max = parseInt(feature.temporal.maxmax, 10);
      drupalLeaflet.temporalTree.insert(min, max, lFeature);
      drupalLeaflet.temporalStart = Math.min(drupalLeaflet.temporalStart, min);
      drupalLeaflet.temporalEnd = Math.max(drupalLeaflet.temporalEnd, max);
      drupalLeaflet.timelineControl.addItem(min, max);
      drupalLeaflet.timelineControl.recalculate();
    }
    else {
      // We don't have any temporal data, so just add the marker to the main layer.
      drupalLeaflet.lMap.removeLayer(lFeature);
      drupalLeaflet.mainLayer.addLayer(lFeature);
    }

    if (drupalLeaflet.temporalDisplayedLayerGroup.getLayers().length > 1) {
      $(drupalLeaflet.timelineContainerDiv).show();
    }
  });

})(jQuery);

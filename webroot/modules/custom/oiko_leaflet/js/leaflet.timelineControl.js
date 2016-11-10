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
      this.doneCallbacks = [];
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
        showCurrentTime: false,
        hiddenDates: [
          {start: '0000-01-01 00:00:00', end: '0001-01-01 00:00:00'}
        ],
        format: {
          minorLabels: {
            year: 'PPPP'
          },
          majorLabels: {
            weekday:    'MMMM PPPP',
            day:        'MMMM PPPP',
            month: 'PPPP'
          }
        },
        moment: vis.moment.utc
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
          _this4._updateDragTitle();
        }
      });
      this._visTimeline.on('timechanged', function(e) {
        if (e.id === 'tdrag') {
          _this4._visTimelineChangedDone(e);
          _this4._updateDragTitle();
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
    _visTimelineChangedDone: function _visTimelineChangedDone(properties) {
      var time = Math.round(properties.time.getTime() / 1000);
      this.time = time;
      if (!this.options.waitToUpdateMap || e.type === 'change') {
        this.doneCallbacks.forEach(function (cb) {
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
      this._updateDragTitle();
    },
    _updateDragTitle: function () {
      var offset = 365.25 * 86400 / 2;
      var low = this.time - offset;
      var high = this.time + offset;
      var format = 'D MMMM PPPP';
      this._visTimeline.setCustomTimeTitle('Displaying: ' + vis.moment.utc(low * 1000).format(format) + ' -  ' + vis.moment.utc(high * 1000).format(format), 'tdrag');
    },
    addItem: function addItem(min, max) {
      var obj = {
        start: min * 1000,
        end: max * 1000,
        type: 'background'
      };
      this._visItems.add(obj);
    },
    addTimeline: function addTimeline(timeline, cb, dcb) {
      var _this = this;

      // this.pause();
      var timelineCount = this.timelines.length;

      if (_this.timelines.indexOf(timeline) === -1) {
        _this.timelines.push(timeline);
        _this.callbacks.push(cb);
        _this.doneCallbacks.push(dcb);
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
    changeTime: function changeTime(time) {
      this.time = time;
      this._visTimeline.setCustomTime(1000 * time, 'tdrag');
      if (!this.options.waitToUpdateMap) {
        this.callbacks.forEach(function (cb) {
          return cb(time);
        });
      }
      if (!this.options.waitToUpdateMap) {
        this.doneCallbacks.forEach(function (cb) {
          return cb(time);
        });
      }
      this._updateDragTitle();
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

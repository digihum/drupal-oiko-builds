(function () {
  'use strict';
  L.TimeLineControl = L.Control.extend({

    // Our default options.
    options: {
      position: 'bottomleft',
      waitToUpdateMap: false,
      waitToUpdateTimeline: true,
      timelineViewSlices: 100,

      // @TODO: We must remove this.
      drupalLeaflet: {
        temporalStart: false,
        temporalEnd: false
      }
    },

    initialize: function initialize(options) {
      L.Util.setOptions(this, options);
      this.timelines = [];
      this.callbacks = [];
      this.doneCallbacks = [];
      this.rangeChangedCallbacks = [];

      // @TODO: do we need these?
      if (typeof options.start !== 'undefined') {
        this.start = options.start;
      }
      if (typeof options.end !== 'undefined') {
        this.end = options.end;
      }
    },

    /**
     * We've been added to a map, return a container div and add events.
     *
     * @param map
     * @returns {div|*}
     */
    onAdd: function onAdd(map) {
      this.map = map;
      this._createDOM();

      // Setup some events.
      this.map.on('temporal.rebase', this._onTemporalRedraw, this);
      this.map.on('temporal.redraw', this._onTemporalRedraw, this);

      // @TODO: Ditch this.
      this.setTime(this.start);
      return this.container;
    },

    onRemove: function onRemove() {
      this.map.off('temporal.rebase', this._onTemporalBase, this);
      this.map.off('temporal.redraw', this._onTemporalRedraw, this);
    },

    /**
     * Rebase the timeline browser, or at least mark it as such.
     *
     * @private
     */
    _onTemporalRebase: function () {
      // Plan of attack:
      // 1. Fire an event asking for the maximal temporal bounds of the temporal layers on the map.
      // 2. Set these to be the min and max of the vistimeline.
      // 3. Alter the currently visible window of the vistimeline.
      // 4. Trigger a redraw of the vistimeline.
      this.map.fire('temporal.redraw');

    },

    /**
     * Redraw the timeline browser, or at least mark it as such.
     *
     * @private
     */
    _onTemporalRedraw: function () {
      // Plan of attack:
      // 3. Get the current visible window of the vistimeline.
      // 4. Break that into option.timelineViewSlices segments
      // 5. Ask each temporal layer for how many events are in each slice.
      // 6. Create an array of data of those items
      // 7. Update the vistimeline with those items.

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
      this._visTimeline = new vis.Timeline(container, [], options);
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
          _this4.map.fireEvent('temporalBrowserTimeChanged', {current: _this4.getTime()});
        }
      });
      this._visTimeline.on('rangechanged', function(e) {
        _this4._visTimelineRangedChanged(e);
        _this4.map.fireEvent('temporalBrowserRangeChanged');
      });
      this._visTimeline.on('doubleClick', function(e) {
        if (typeof e.time !== 'undefined') {
          var time = Math.round(e.time.getTime() / 1000);
          _this4.changeTime(time);
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

    _visTimelineRangedChanged: function _visTimelineRangedChanged(properties) {
      var start = Math.round(properties.start.getTime() / 1000);
      var end = Math.round(properties.end.getTime() / 1000);
      if (!this.options.waitToUpdateMap) {
        this.rangeChangedCallbacks.forEach(function (cb) {
          return cb(start, end);
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
      this.map.fireEvent('temporalBrowserTimeChanged', {current: this.getTime()});
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
      if (this.waitToUpdateTimeline) {
        this.updateTimelineWithData();
      }
    },

    addTimeline: function addTimeline(timeline, cb, dcb, rdcb) {
      var _this = this;

      // this.pause();
      var timelineCount = this.timelines.length;

      if (_this.timelines.indexOf(timeline) === -1) {
        _this.timelines.push(timeline);
        _this.callbacks.push(cb);
        _this.doneCallbacks.push(dcb);
        _this.rangeChangedCallbacks.push(rdcb);
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

    setWindow: function setWindow(start, end) {
      this._visTimeline.setWindow(start * 1000, end * 1000, {animation: false});
    },

    setTimeAndWindow: function setTimeAndWindow(time, start, end) {
      if (time !== 0) {
        this.time = time;
        this._visTimeline.setCustomTime(1000 * time, 'tdrag');
      }

      this._visTimeline.setWindow(1000 * start, 1000 * end, {animation: false});
      if (time !== 0) {
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
      }
      this.map.fireEvent('temporalBrowserRangeChanged');
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
      this.map.fireEvent('temporalBrowserTimeChanged', {current: this.getTime()});
    },

    getTime: function getTime() {
      return Math.round(this._visTimeline.getCustomTime('tdrag') / 1000);
    },

    getWindow: function getWindow() {
      var timewindow = this._visTimeline.getWindow();
      return {
        start: Math.round(timewindow.start.getTime() / 1000),
        end:  Math.round(timewindow.end.getTime() / 1000)
      }
    },

  });

  L.timeLineControl = function (options) {
    return new L.TimeLineControl(options);
  };


})();

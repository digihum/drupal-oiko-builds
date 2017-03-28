(function () {
  'use strict';
  L.TimeLineControl = L.Control.extend({

    // Our default options.
    options: {
      position: 'bottomleft',
      waitToUpdateMap: false,
      waitToUpdateTimeline: true,
      timelineViewSlices: 100,
      numberOfClasses: 25,
      temporalRangeWindow: 0
    },

    initialize: function initialize(options) {
      L.Util.setOptions(this, options);
      this._maxOfCounts = 1;

      this._onTemporalRebaseDebounced = this.debounce(this._onTemporalRebase, 50, false);
      this._onTemporalRedrawDebounced = this.debounce(this._onTemporalRedraw, 10, false);

      this.window = {
        start: NaN,
        end: NaN
      }
    },

    // @method addTo(map: Map): this
    // Adds the control to the given map.
    addTo: function (map) {
      this.remove();
      this._map = map;

      var container = this._container = this.onAdd(map);

      // Add the timeline after the map container, not within it.
      this._DOMinsertAfter(container, map._container);

      return this;
    },

    // Helper to insert a DOM node after an existing one.
    _DOMinsertAfter: function (newNode, referenceNode) {
      referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
    },

    // Returns a function, that, as long as it continues to be invoked, will not
    // be triggered. The function will be called after it stops being called for
    // N milliseconds. If `immediate` is passed, trigger the function on the
    // leading edge, instead of the trailing.
    debounce: function (func, wait, immediate) {
      var timeout;
      return function() {
        var context = this, args = arguments;
        var later = function() {
          timeout = null;
          if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
      };
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
      this.map.on('temporal.rebase', this._onTemporalRebaseDebounced, this);
      this.map.on('temporal.redraw', this._onTemporalRedrawDebounced, this);

      // Hook into the timeline.
      this._visTimeline.on('rangechanged', L.Util.bind(this._onTemporalRedrawDebounced, this));
      return this.container;
    },

    onRemove: function onRemove() {
      this.map.off('temporal.rebase', this._onTemporalRebaseDebounced, this);
      this.map.off('temporal.redraw', this._onTemporalRedrawDebounced, this);
    },

    /**
     * Rebase the timeline browser.
     *
     * This should be called when items on the browser are changed.
     *
     * @private
     */
    _onTemporalRebase: function () {
      var bounds = {
        min: Infinity,
        max: -Infinity
      };
      var boundsCallback = function(min, max) {
        bounds.min = Math.min(bounds.min, min);
        bounds.max = Math.max(bounds.max, max);
      };

      // 1. Fire an event asking for the maximal temporal bounds of the temporal layers on the map.
      this.map.fire('temporal.getBounds', {boundsCallback: boundsCallback});

      // If we got some valid bounds, then there's something to do.
      if (bounds.min < bounds.max) {
        // 2. Set these to be the min and max of the vistimeline.
        this._visTimeline.setOptions({
          min: bounds.min * 1000,
          // start: bounds.min * 1000,
          max: bounds.max * 1000,
          // end: bounds.max * 1000
        });

        // 3. Alter the currently visible window of the vistimeline.
        var window = this._visTimeline.getWindow();
        if (window.start.getTime() < bounds.min * 1000) {
          this._visTimeline.setWindow(bounds.min * 1000);
        }
        if (window.end.getTime() > bounds.max * 1000) {
          this._visTimeline.setWindow(null, bounds.max * 1000);
        }
        // Preserve an existing window if one is set.
        if (!isNaN(this.window.start) && !isNaN(this.window.end)) {
          this._visTimeline.setWindow(this.window.start * 1000, this.window.end * 1000);
        }

        // If we need to, move the current time marker.
        if (isNaN(this._visTimeline.getCustomTime('tdrag').getTime())) {
          this.setTime((bounds.max + bounds.min) / 2);
        }
        
        // Get the global count of events per biggest slice, this will be used to scale all events.
        var windowSize = bounds.max - bounds.min;

        var windowSegmentSize = windowSize / this.options.timelineViewSlices;

        var position = bounds.min;

        var count = 0;
        var countsCallback = function(items) {
          count += items;
        };

        var slice;
        this._maxOfCounts = 1;
        while (position < bounds.max) {
          slice = {
            start: position,
            end: position + windowSegmentSize,
          };
          count = 0;
          this.map.fire('temporal.getCounts', {slice: slice, countsCallback: countsCallback});
          this._maxOfCounts = Math.max(this._maxOfCounts, count);
          position += windowSegmentSize;
        }
      }

      // 4. Trigger a redraw of the vistimeline.
      this.map.fire('temporal.redraw');
    },

    // Get the unique, sorted items of a numeric array.
    _arrayUnique: function (a) {
      return a.sort(function (a, b) {return a - b}).filter(function(item, pos, ary) {
        return !pos || item != ary[pos - 1];
      })
    },

    /**
     * Redraw the timeline browser, or at least mark it as such.
     *
     * @private
     */
    _onTemporalRedraw: function () {
      var count = 0;
      var countsCallback = function(items) {
        count += items;
      };

      var allEventBounds = [];
      var startEndCallback = function(start, end) {
        allEventBounds.push(start);
        allEventBounds.push(end);
      };

      // Plan of attack:
      // 3. Get the current visible window of the vistimeline.
      var window = this._visTimeline.getWindow();
      // 4. Break that into option.timelineViewSlices segments.
      var windowSize = window.end.getTime() / 1000 - window.start.getTime() / 1000;
      if (windowSize < 1) {
        return;
      }

      var visSlices = [], slices = [], slice, visSlice;

      // If there are < this.options.timelineViewSlices events in this window, use them as the segments.
      count = 0;
      slice = {
        start: window.start.getTime() / 1000,
        end: window.end.getTime() / 1000
      };
      this.map.fire('temporal.getCounts', {slice: slice, countsCallback: countsCallback});
      // @TODO: If performance becomes an issue, then remove this 'true', and re-work the else case.
      if (true || count < this.options.timelineViewSlices) {
        // We can easily render the exact events, so get the start/end time of them all.
        allEventBounds = [];
        this.map.fire('temporal.getStartAndEnds', {slice: slice, startEndCallback: startEndCallback});
        // Make unique and sort.
        allEventBounds = this._arrayUnique(allEventBounds);
        // Make slices for each entry in the array.
        var last = false;
        for (var i = 0;i < allEventBounds.length;i++) {
          if (last) {
            slices.push({
              start: last,
              end: allEventBounds[i]
            });
          }
          last = allEventBounds[i];
        }
      }
      else {
        var position = window.start.getTime() / 1000;

        var endTime = window.end.getTime() / 1000;
        var windowSegmentSize = windowSize / this.options.timelineViewSlices;

        // 5. Ask each temporal layer for how many events are in each slice.
        while (position < endTime) {
          slice = {
            start: position,
            end: position + windowSegmentSize
          };
          slices.push(slice);
          position += windowSegmentSize;
        }
      }

      for (var i = 0;i < slices.length;i++) {
        count = 0;
        this.map.fire('temporal.getCounts', {slice: slices[i], countsCallback: countsCallback});
        visSlice = {
          start: slices[i].start * 1000,
          end: slices[i].end * 1000,
          className: 'timeline-browser-item-count--' + Math.round(count / this._maxOfCounts * this.options.numberOfClasses),
          type: 'background'
        };
        visSlices.push(visSlice);
      }

      // Dedupe the visSlices.
      var lastClass;
      for (var i = 0;i < visSlices.length;i++) {
        if (lastClass == visSlices[i].className) {
          // Expand the previous slice to this slices end.
          visSlices[i - 1].end = visSlices[i].end;
          // This is a duplicate slice and can go, and we will reprocess this i value.
          visSlices.splice(i, 1);
          i--;
        }
        else {
          lastClass = visSlices[i].className;
        }
      }

      // 7. Update the vistimeline with those items.
      this._visTimeline.setItems(visSlices);
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
      var options = {
        width:  "100%",
        stack: false,
        showCurrentTime: false,
        showMajorLabels: false,
        hiddenDates: [
          {start: '0000-01-01 00:00:00', end: '0001-01-01 00:00:00'}
        ],
        format: {
          minorLabels: {
            year: 'PPPP',
            month: 'MMM PPPP'
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
      this._visTimeline = new vis.Timeline(container, [], options);
      var customDate = new Date();
      customDate = new Date(customDate.getFullYear(), customDate.getMonth(), customDate.getDate() + 1);
      this._visTimeline.addCustomTime(customDate, 'tdrag');

      // Set timeline time change event, so cursor is set after moving custom time (blue)
      this._visTimeline
        .on('timechange', L.Util.bind(function(e) {
          if (e.id === 'tdrag') {
            this.map.fire('temporal.shift', {time: Math.round(e.time.getTime() / 1000)});
            this._updateDragTitle();
          }
        }, this))
        .on('timechanged', L.Util.bind(function(e) {
          if (e.id === 'tdrag') {
            this.map.fire('temporal.shifted', {time: Math.round(e.time.getTime() / 1000)});
            this._updateDragTitle();
          }
        }, this))
        .on('rangechanged', L.Util.bind(function(e) {
          this.window = {
            start: e.start.getTime() / 1000,
            end: e.end.getTime() / 1000
          };
          this.map.fireEvent('temporal.visibleWindowChanged');
        }, this))
        .on('doubleClick', L.Util.bind(function(e) {
          if (typeof e.time !== 'undefined') {
            this._visTimeline.setCustomTime(e.time, 'tdrag');
            this.map.fire('temporal.shift', {time: Math.round(e.time.getTime() / 1000)});
            this.map.fire('temporal.shifted', {time: Math.round(e.time.getTime() / 1000)});
            this._updateDragTitle();
          }
        }, this));
    },

    _updateDragTitle: function (time) {
      var thisTime = time ? time : this._visTimeline.getCustomTime('tdrag') / 1000;
      var offset = this.options.temporalRangeWindow;
      if (offset > 0) {
        var low = thisTime - offset;
        var high = thisTime + offset;
        var format = 'D MMMM PPPP';
        this._visTimeline.setCustomTimeTitle('Displaying: ' + vis.moment.utc(low * 1000).format(format) + ' -  ' + vis.moment.utc(high * 1000).format(format), 'tdrag');
      }
      else {
        var current = thisTime;
        var format = 'D MMMM PPPP';
        this._visTimeline.setCustomTimeTitle('Displaying: ' + vis.moment.utc(current * 1000).format(format), 'tdrag');
      }
    },

    /**
     * Set the time displayed.
     *
     * @param {Number} time The time to set
     */
    setTime: function (time) {
      this._visTimeline.setCustomTime(time * 1000, 'tdrag');
      this.map.fire('temporal.shift', {time: Math.round(time)});
      this.map.fire('temporal.shifted', {time: Math.round(time)});
      this._updateDragTitle();
    },

    setTimeAndWindow: function (time, start, end) {
      if (time !== 0) {
        this._visTimeline.setCustomTime(1000 * time, 'tdrag');
      }

      if (!isNaN(start) && !isNaN(end)) {
        this._visTimeline.setWindow(1000 * start, 1000 * end);
        this.window = {
          start: start,
          end: end
        };
      }
      if (time !== 0) {
        this._updateDragTitle();
        this.map.fire('temporal.shift', {time: Math.round(time)});
        this.map.fire('temporal.shifted', {time: Math.round(time)});
      }
    },

    getTime: function () {
      return Math.round(this._visTimeline.getCustomTime('tdrag') / 1000);
    },

    getWindow: function () {
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

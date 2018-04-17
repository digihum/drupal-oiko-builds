(function () {
  'use strict';
  L.TimeLineControl = L.Control.extend({

    // Our default options.
    options: {
      position: 'bottomleft',
      waitToUpdateMap: false,
      waitToUpdateTimeline: true,
      timelineViewSlices: 200,
      numberOfClasses: 25,
      temporalRangeWindow: 0
    },

    initialize: function initialize(options) {
      L.Util.setOptions(this, options);
      this._maxOfCounts = 1;

      this._onTemporalRebaseDebounced = this.debounce(this._onTemporalRebase, 100, false);
      this._onTemporalRedrawDebounced = this.debounce(this._onTemporalRedraw, 100, false);

      this.window = {
        start: NaN,
        end: NaN
      };

      this.currentTimeAdjusted = false;
      this.boundsAdjusted = false;

      this._segmentTree = new IntervalTree();
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

      // Hook into layers being added or removed.
      this.map.on('layeradd', this._onTemporalRebaseDebounced, this);
      this.map.on('layerremove', this._onTemporalRebaseDebounced, this);

      return this.container;
    },

    onRemove: function onRemove() {
      this.map.off('temporal.rebase', this._onTemporalRebaseDebounced, this);
      this.map.off('temporal.redraw', this._onTemporalRedrawDebounced, this);
      this.map.off('layeradd', this._onTemporalRebaseDebounced, this);
      this.map.off('layerremove', this._onTemporalRebaseDebounced, this);
    },

    /**
     * Rebase the timeline browser.
     *
     * This should be called when items on the browser are changed.
     *
     * We recompute all segments that may contain elements, dedupe and store them in an interval tree for retrieval by the rebase event handler.
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

      var count = 0, maxOfCounts = 0;
      var countsCallback = function(items) {
        count += items;
      };

      var allEventBounds = [];
      var startEndCallback = function(start, end) {
        allEventBounds.push(start - 1);
        allEventBounds.push(start);
        allEventBounds.push(end);
        allEventBounds.push(end + 1);
      };

      // 1. Fire an event asking for the maximal temporal bounds of the temporal layers on the map.
      this.map.fire('temporal.getBounds', {boundsCallback: boundsCallback});

      // Get a new blank interval tree for our segments to live in.
      this._segmentTree = new IntervalTree();

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
        else if (!this.boundsAdjusted) {
          this._visTimeline.setWindow(bounds.min * 1000, bounds.max * 1000);
          this.boundsAdjusted = true;
        }

        // If we need to, move the current time marker.
        if (!this.currentTimeAdjusted) {
          // Set to 230AD as per #43708.
          this.setTime(-54909100800);
        }

        // Get the global count of events per biggest slice, this will be used to scale all events.
        this._maxOfCounts = 1;

        allEventBounds = [bounds.min, bounds.max];

        var visSlices = [], slices = [], slice, visSlice;

        slice = {
          start: bounds.min,
          end: bounds.max
        };
        // Get all of the start and end points of events into our allEventBounds array.
        this.map.fire('temporal.getStartAndEnds', {slice: slice, startEndCallback: startEndCallback});
        allEventBounds = this._arrayUnique(allEventBounds);
        // Make slices for each entry in the array.
        var last = false;
        for (var i = 0; i < allEventBounds.length; i++) {
          if (last) {
            slices.push({
              start: last,
              end: allEventBounds[i]
            });
          }
          last = allEventBounds[i];
        }

        // Fire a temporal.getCounts event for each slice and record the number of items in that slice.
        for (var i = 0;i < slices.length;i++) {
          count = 0;
          this.map.fire('temporal.getCounts', {slice: slices[i], countsCallback: countsCallback});
          // If this is an empty slice, then we don't care about rendering it.
          if (count) {
            maxOfCounts = Math.max(maxOfCounts, count);
            visSlice = {
              start: slices[i].start * 1000,
              end: slices[i].end * 1000,
              _count: count,
            };
            visSlices.push(visSlice);
          }
        }

        // Compute the classes on each visSlice.
        for (var i = 0;i < visSlices.length;i++) {
          visSlices[i]._adjustedCount = Math.round(visSlices[i]._count / maxOfCounts * this.options.numberOfClasses);
        }

        // Dedupe the visSlices.
        var lastClass, lastEnd;
        for (var i = 0;i < visSlices.length;i++) {
          // Group slices within nearly a day of each other if they have the same className.
          if (lastClass === visSlices[i]._adjustedCount && (visSlices[i].start - lastEnd) <= 86401000) {
            // Expand the previous slice to this slices end.
            visSlices[i - 1].end = visSlices[i].end;
            // This is a duplicate slice and can go, and we will reprocess this i value.
            visSlices.splice(i, 1);
            i--;
          }
          else {
            lastClass = visSlices[i]._adjustedCount;
            lastEnd = visSlices[i].end;
          }
        }

        // Because we're going to insert into an unbalanced tree we will do better if our array isn't sorted, so randomise.
        this._shuffleArray(visSlices);

        // Add the correct classname to the visSlices.
        if (visSlices.length) {
          for (var i = 0;i < visSlices.length;i++) {
            visSlices[i].type = 'background';

            // Index the visSlices into to IntervalTree for drawing.
            this._segmentTree.insert(visSlices[i].start, visSlices[i].end, visSlices[i]);
          }
        }
      }

      // 4. Trigger a redraw of the vistimeline.
      this.map.fire('temporal.redraw');
    },

    // Get the unique, sorted items of a numeric array.
    _arrayUnique: function _arrayUnique(a) {
      return a.sort(function (a, b) {return a - b}).filter(function(item, pos, ary) {
        return !pos || item != ary[pos - 1];
      })
    },

    /**
     * Randomize array element order in-place.
     * Using Durstenfeld shuffle algorithm.
     */
    _shuffleArray: function _shuffleArray(array) {
      for (var i = array.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = array[i];
        array[i] = array[j];
        array[j] = temp;
      }
      return array;
    },

    /**
     * Redraw the timeline browser.
     *
     * @private
     */
    _onTemporalRedraw: function () {
      // Get the current visible window of the vistimeline.
      var window = this._visTimeline.getWindow();
      var start = window.start.getTime();
      var end = window.end.getTime();
      var windowSize = end / 1000 - start / 1000;
      if (windowSize < 1) {
        return;
      }

      var visSlices = [];

      // Get the slices from the interval tree.
      if (this._segmentTree.size) {
        visSlices = this._segmentTree.overlap(start, end);
      }

      // For rendering we want the slices to be in 'order'.
      visSlices.sort(function(a, b) {
        // Compare starts first, unless equal, then ends.
        return a.start - b.start ? a.start - b.start : a.end - b.end;
      });

      // If we need to, reduce the number of items on the timeline.
      if (visSlices.length > this.options.timelineViewSlices) {
        visSlices = this._combineSimilarSlices(visSlices, this.options.timelineViewSlices);
      }

      for (var i = 0; i < visSlices.length; i++) {
        visSlices[i].className = 'timeline-browser-item-count--' + visSlices[i]._adjustedCount;
      }

      // Update the vistimeline with those items.
      this._visTimeline.setItems(visSlices);
    },

    /**
     * Reduce the number of slices given by combining adjacent slices with similar counts.
     *
     * @param slices
     *   An array of slices to reduce the number of by combining.
     * @param targetNumberOfSlices
     *   The target number of slices to return.
     *
     * @returns {Array}
     * @private
     */
    _combineSimilarSlices: function _combineSimilarSlices(slices, targetNumberOfSlices) {

      // This is how nearby the slices can be to be combined, we gradually increase by powers of 2.
      var closenessTimeFactor = -1;
      var closenessCountFactor = -1;
      var iterations = -1;



      var i, j, closeness, closenessCount, lastEnd, lastCount, candidates, firstSliceSize, secondSliceSize;
      // Keep running until we've met our target.
      while (slices.length > targetNumberOfSlices) {
        closenessTimeFactor++;
        closenessCountFactor++;
        iterations++;

        closeness = 86400 * 1000 * Math.pow(2, closenessTimeFactor);
        closenessCount = closenessCountFactor;
        candidates = [];
        for (i = 0;i < slices.length;i++) {
          // Group slices within nearly a day of each other if they have the same className.
          if (i && ((slices[i].start - lastEnd) <= closeness) && (Math.abs(lastCount - slices[i]._adjustedCount) < closenessCount)) {
            // This slice is a candidate for combining.
            candidates.push({
              i: i,
              width: slices[i].end - slices[i].start
            });
          }
          lastEnd = slices[i].end;
          lastCount = slices[i]._adjustedCount;
        }

        if (candidates.length) {
          // We have some candidates for grouping, sort them from smallest to biggest.
          candidates.sort(function (a, b) {
            return a.width - b.width;
          });

          // Trim the candidates array if we need to.
          candidates.length = Math.min(candidates.length, slices.length - targetNumberOfSlices);

          // Re-sort to be in 'reverse' i order.
          candidates.sort(function (a, b) {
            return b.i - a.i;
          });

          // Combine the first candidate with it's 'previous' segment.
          for (i = 0;i < candidates.length;i++) {
            j = candidates[i].i;
            // Average the counts.
            firstSliceSize = slices[j - 1].end - slices[j - 1].start;
            secondSliceSize = slices[j].end - slices[j].start;
            slices[j - 1]._adjustedCount = Math.round((firstSliceSize * slices[j - 1]._adjustedCount + secondSliceSize * slices[j]._adjustedCount) / (firstSliceSize + secondSliceSize));

            // Set the end of the first slice to be the end of the second.
            slices[j - 1].end = slices[j].end;

            // Remove the jth slice.
            slices.splice(j, 1);
          }
        }

        // Avoid infinite loops.
        if (iterations > 20) {
          break;
        }
      }

      return slices;
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
        height: 60,
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
      this.currentTimeAdjusted = true;
      this._visTimeline.setCustomTime(time * 1000, 'tdrag');
      this.map.fire('temporal.shift', {time: Math.round(time)});
      this.map.fire('temporal.shifted', {time: Math.round(time)});
      this._updateDragTitle();
    },

    setTimeAndWindow: function (time, start, end) {
      if (!isNaN(time) && time !== 0) {
        this._visTimeline.setCustomTime(1000 * time, 'tdrag');
      }

      if (!isNaN(start) && !isNaN(end) && start !== 0 && end !== 0) {
        this._visTimeline.setWindow(1000 * start, 1000 * end);
        this.window = {
          start: start,
          end: end
        };
      }
      if (!isNaN(time) && time !== 0) {
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

(function ($) {
  "use strict";

Drupal.oiko.addAppModule('comparative-timeline');

var WINDOW_SLIDER_ID = 'window-slider';

Drupal.behaviors.comparative_timeline = {
  attach: function(context, settings) {
    $(context).find('.js-comparative-timeline-container').once('comparative_timeline').each(function() {
      var $component = $(this);
      if ($component.data('comparative_timeline') == undefined) {
        Drupal.oiko.timeline = new Drupal.OikoComparativeTimeline($component, settings.oiko_timeline);
        $component.data('comparative_timeline', Drupal.oiko.timeline);
      }
      Drupal.oiko.appModuleDoneLoading('comparative-timeline');
    });
  }
};


  Drupal.OikoComparativeTimeline = function ($outerContainer, element_settings) {
    var timeline = this;
    var defaults = {
      loadingItems: {},
      preselectedLinks: [],
      isLoading: false,
      categories: [],
      crmTypes: [],
      window: {},
      rangeAdjusted: false
    };

    $.extend(this, defaults, element_settings);

    this.$outerContainer = $outerContainer;

    this.$timelineContainer = this.$outerContainer.find('.js-comparative-timeline');
    this.$overviewContainer = this.$outerContainer.find('.js-comparative-timeline-overview');
    this.$addNewContainer = this.$outerContainer.find('.js-comparative-timeline-add-new');
    this.$preselectionsContainer = this.$outerContainer.find('.js-comparative-timeline-preselections');
    this.$ajaxLoader = this.$outerContainer.find('.js-loading-graphic');
    this._timelineOptions = {
      align: 'auto',
      showCurrentTime: false,
      margin: {
        item : {
          horizontal: 0,
          vertical: 1
        }
      },
      orientation: {
        axis: 'top',
        item: 'top'
      },
      selectable : true,
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
      showMajorLabels: false,
      moment: vis.moment.utc,
      zoomMax: 1000 * 86400 * 365.25 * 100,
      zoomMin: 1000 * 86400 * 365.25
    };

    // Settings for the overview timeline.
    this._overviewOptions = {
      width:  "100%",
      stack: false,
      showCurrentTime: false,
      showMajorLabels: false,
      height: 60,
      maxHeight: 60,
      margin: {
        axis: 0,
      },
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
      moment: vis.moment.utc,
      moveable: false,
      zoomable: false,
      selectable: true,
      snap: null,
      editable: {
        add: false,         // add new items by double tapping
        updateTime: true,  // drag items horizontally
        updateGroup: false, // drag items from one group to another
        remove: false,       // delete an item by tapping the delete button top right
        overrideItems: false  // allow these options to override item.editable
      },
      onMoving: function (item, callback) {
        if (item.id === WINDOW_SLIDER_ID) {
          // Make sure we can't drag outside the bounds.
          var window = timeline._visTimelineOverview.getWindow();
          if (item.start < window.start) {
            item.start = window.start;
          }
          else if (item.end > window.end) {
            item.end = window.end;
          }
          else {
            // Move the upper timeline to this timeline window.
            timeline._visTimeline.setWindow(item.start, item.end, {animation: false});
            callback(item); // send back adjusted item
          }
        }
      }
    };

    this._timelineMin = Infinity;
    this._timelineMax = -Infinity;

    this.initialise.call(this);
  };

  Drupal.OikoComparativeTimeline.prototype.getTimelines = function () {
    return this._visGroups.getIds();
  };

  Drupal.OikoComparativeTimeline.prototype.setTimelines = function (timelines) {
    // Remove all existing timelines.
    var oldTimelines = this.getTimelines();
    for (var i in oldTimelines) {
      this.removeGroupFromTimeline(oldTimelines[i]);
    }
    // And now add the timelines we want.
    for (var i in timelines) {
      if (timelines[i] && !this.isLoadingCheck(timelines[i])) {
        this.loadDataHandler(timelines[i]);
      }
    }
  };

  Drupal.OikoComparativeTimeline.prototype.getCategories = function () {
    return this.categories;
  };

  Drupal.OikoComparativeTimeline.prototype.setCategories = function (categories) {
    // Make sure categories are numeric.
    var numericCategories = [];
    for (var i = 0;i < categories.length;i++) {
      numericCategories.push(parseInt(categories[i], 10));
    }
    this.categories = numericCategories;
    // Refresh our data view.
    this._visDisplayedItems.refresh();
    // Should we fire an event?
  };

  Drupal.OikoComparativeTimeline.prototype.getCRMTypes = function () {
    return this.categories;
  };

  Drupal.OikoComparativeTimeline.prototype.setCRMTypes = function (types) {
    this.crmTypes = types;
    // Refresh our data view.
    this._visDisplayedItems.refresh();
    // Should we fire an event?
  };

  Drupal.OikoComparativeTimeline.prototype.loadDataHandler = function (groupID) {
    var timeline = this;
    var url = '/comparative-timeline/data/' + groupID;
    this.nowLoading(groupID);
    $.get(url, function(data) {
      timeline.doneLoading.call(timeline, groupID);
      timeline.addDataToTimeline.call(timeline, data);
    });
  };

  Drupal.OikoComparativeTimeline.prototype.nowLoading = function(id) {
    this.loadingItems[id] = true;
    this.evalLoadingState();
  };

  Drupal.OikoComparativeTimeline.prototype.isLoadingCheck = function(id) {
    return typeof this.loadingItems[id] !== 'undefined';
  };

  Drupal.OikoComparativeTimeline.prototype.doneLoading = function(id) {
    delete this.loadingItems[id];
    this.evalLoadingState();
  };

  Drupal.OikoComparativeTimeline.prototype.evalLoadingState = function() {
    var items = false;
    for (var i in this.loadingItems) {
      if (this.loadingItems.hasOwnProperty(i) && this.loadingItems[i]) {
        items = true;
        break;
      }
    }
    this.$ajaxLoader.toggleClass('js-loading-graphic--comparative-timeline-working', items);
    this.isLoading = items;
    this.updateTheDOM();
  };

  Drupal.OikoComparativeTimeline.prototype.isLoadingItems = function() {
    return this.isLoading;
  };

  Drupal.OikoComparativeTimeline.prototype._filterItemsTimeWindowCallback = function(item) {
    return item.id === WINDOW_SLIDER_ID;
  };

  Drupal.OikoComparativeTimeline.prototype._filterItemsCategoriesCallback = function(item) {
    // If the list of categories to show is empty, show everything.
    if (this.categories.length === 0) {
      return true;
    }
    else {
      return this.categories.indexOf(item.significance) > -1;
    }
  };

  Drupal.OikoComparativeTimeline.prototype._filterItemsCRMTypesCallback = function(item) {
    // If the list of types to show is empty, show everything.
    if (this.crmTypes.length === 0) {
      return true;
    }
    else {
      return this.crmTypes.indexOf(item.crmType) > -1;
    }
  };

  Drupal.OikoComparativeTimeline.prototype._filterItemsCallback = function(item) {
    return this._filterItemsTimeWindowCallback(item) || (this._filterItemsCategoriesCallback(item) && this._filterItemsCRMTypesCallback(item));
  };

  Drupal.OikoComparativeTimeline.prototype.initialise = function () {
    var timeline = this;
    // Construct the vis timeline datasets.
    this._visItems = new vis.DataSet({});
    // Add the item that we'll use to show what we're browsing.
    this._visDisplayedItems = new vis.DataView(this._visItems, {
      filter: $.proxy(this._filterItemsCallback, this)
    });

    this._visOverviewItems = new vis.DataView(this._visDisplayedItems, {
      fields: {
        id: 'id',
        start: 'start',
        end: 'end',
        _summaryType: 'type',
        _summaryClass: 'className'
      }
    });
    this._visGroups = new vis.DataSet({});
    this._visTimeline = new vis.Timeline(this.$timelineContainer.get(0), this._visDisplayedItems, this._visGroups, this._timelineOptions);
    // Setting this with the options array didn't work, so set it again here.
    this._visTimeline.setOptions({ orientation: {axis: this._timelineOptions.orientation.axis} });
    // Add another timeline that's the summary of our timelines.
    this._visTimelineOverview = new vis.Timeline(this.$overviewContainer.get(0), this._visOverviewItems, this._overviewOptions);


    // Add the preselected options.
    if (timeline.hasOwnProperty('defaultOptions')) {
      var defaultOptionTitle, defaultOptionId;
      for (defaultOptionId in timeline.defaultOptions) {
        defaultOptionTitle = timeline.defaultOptions[defaultOptionId];
        var $link = $('<a href="#">').html(defaultOptionTitle).data('groupId', defaultOptionId).addClass('comparative-timeline--preselect-link').click(function(e) {
          e.preventDefault();
          timeline.loadDataHandler.call(timeline, $(this).data('groupId'));
          $(this).hide();
        });
        this.$preselectionsContainer.find('.js-items').append($link);
        this.preselectedLinks.push($link);
      }
    }

    // Hook events up.
    this._visTimeline
      .on('select', function(properties) {
        timeline.selectedTimelineItems.call(timeline, properties);
      })
      .on('rangechange', $.proxy(function(e) {
        this.updateCurrentWindowItem();
      }, this))
      .on('rangechanged', $.proxy(function(e) {
        this.updateCurrentWindowItem();
        this.window = {
          start: Math.round(e.start.getTime() / 1000),
          end: Math.round(e.end.getTime() / 1000)
        };
        // Execute this in the 'next tick'.
        setTimeout(function() {
          $(window).trigger('oiko.timelineRangeChanged');
        }, 1);
      }, this));
    this.$timelineContainer.bind('click', function(e) {
      var $target = $(e.target);
      if ($target.is('.js-comparative-timeline-remove-link')) {
        // We need to remove this group.
        if ($target.data('groupId')) {
          timeline.removeGroupFromTimeline($target.data('groupId'));
        }
      }
    });
    this._visTimelineOverview
      .on('doubleClick', function(event) {
        if (event.time) {
          // Move our window to be centered on this time.
          timeline._visTimeline.moveTo(event.time, {animation: false});
        }
      })
      .on('select', function(e) {
        if (e.items.indexOf(WINDOW_SLIDER_ID) === -1) {
          timeline._visTimelineOverview.setSelection(WINDOW_SLIDER_ID);
        }
      });
    $(window).bind('oikoSidebarOpen', function(e, id) {
      // Find the selected item in our items, and select it.
      var selectedItems = timeline._visItems.getIds({filter: function(item) {
        return item.event == id;
      }});
      timeline._visTimeline.setSelection(selectedItems, {focus: selectedItems.length > 0});
    });
    $(window).bind('set.oiko.categories', function(e, categories) {
      timeline.setCategories(categories);
    });
    $(window).bind('selected.timeline.searchitem', function (e, id) {
      timeline.loadDataHandler.call(timeline, parseInt(id, 10));
    });

    // Now that we're all loaded up, update the DOM.
    this.updateTheDOM();
  };

  Drupal.OikoComparativeTimeline.prototype.getVisibleTimeWindow = function() {
    var w = this._visTimeline.getWindow();
    this.window = {
      start: Math.round(w.start.getTime() / 1000),
      end: Math.round(w.end.getTime() / 1000)
    };

    return this.window;
  };

  Drupal.OikoComparativeTimeline.prototype.setVisibleTimeWindow = function(start, end) {
    this._visTimeline.setWindow(start * 1000, end * 1000);
    this.window = {
      start: start,
      end: end
    };
    this.rangeAdjusted = true;
  };

  Drupal.OikoComparativeTimeline.prototype.removeGroupFromTimeline = function(groupId) {
    var timeline = this;
    // Find the items to remove.
    var ids = this._visItems.getIds({
      filter: function(item) {
        return item.group == groupId;
      }
    });
    if (ids.length) {
      this._visItems.remove(ids);
    }
    this._visGroups.remove(groupId);

    // Refresh our data view.
    this._visDisplayedItems.refresh();

    // Re-compute the min and max for our display.
    this._timelineMin = Infinity;
    this._timelineMax = -Infinity;
    this._visItems.forEach(function(item) {
      if (item.id !== WINDOW_SLIDER_ID) {
        timeline._timelineMin = Math.min(timeline._timelineMin, ((item.start / 1000) - 86400 * 365 * 10));
        timeline._timelineMax = Math.max(timeline._timelineMax, ((item.end / 1000) + 86400 * 365 * 10));
      }
    });

    this.updateTimelineBounds();
    this.updateCurrentWindowItem();

    // If this is one of the pre-built links, put it back.
    for (var i in this.preselectedLinks) {
      if ($(this.preselectedLinks[i]).data('groupId') == groupId) {
        $(this.preselectedLinks[i]).show();
      }
    }

    // Show/hide the preselections as needed.
    this.updateTheDOM();
    $(window).trigger('oiko.timelines_updated', [this.getTimelines()]);
  };

  Drupal.OikoComparativeTimeline.prototype.selectedTimelineItems = function (properties) {
    var selected;
    for (var i in properties.items) {
      selected = properties.items[i];
    }

    if (selected) {
      var item = this._visItems.get(selected);
      Drupal.oiko.openSidebar(selected.substr(1 + properties.items[i].lastIndexOf('-')), item.title, true);
    }
  };

  Drupal.OikoComparativeTimeline.prototype.addDataToTimeline = function(data) {
    // We have some data, we should add it to the timeline.
    var groupId = data.id;
    // Add a group:
    this._visGroups.add([{
      id: groupId,
      content: '<span class="js-comparative-timeline-remove-link fa fa-times" data-group-id="' + data.id + '"></span>&nbsp;' + data.label + data.logo
    }]);

    if (data.events !== null) {
      var newEvents = [];
      for (var i = 0; i < data.events.length;i++) {
        var event = data.events[i];
        var minmin = parseInt(event.minmin, 10);
        var maxmax = parseInt(event.maxmax, 10);
        newEvents.push({
          // Timeline information.
          id: groupId + '-' + event.id,
          type: 'range',
          content: event.label + ' ' + event.date_title,
          title: event.label,
          start: minmin * 1000,
          end: maxmax * 1000,
          group: data.id,

          // CRM information for filtering and displaying etc.
          event: event.id,
          className: 'oiko-timeline-item--' + event.color,
          significance: parseInt(event.significance, 10),
          crmType: event.crm_type,

          // Information for our summary timeline.
          _summaryType: 'background',
          _summaryClass: ''
        });

        this._timelineMin = Math.min(this._timelineMin, (minmin - 86400 * 365 * 10));
        this._timelineMax = Math.max(this._timelineMax, (maxmax + 86400 * 365 * 10));
      }
      this._visItems.add(newEvents);
    }

    // Refresh our data view.
    this._visDisplayedItems.refresh();

    this.updateTimelineBounds();
    this.updateCurrentWindowItem();

    // If this is one of the pre-built links, hide it.
    for (var i in this.preselectedLinks) {
      if ($(this.preselectedLinks[i]).data('groupId') == groupId) {
        $(this.preselectedLinks[i]).hide();
      }
    }

    this._visTimeline.setOptions({ orientation: {axis: 'top'} });

    this.updateTheDOM();
    $(window).trigger('oiko.timelines_updated', [this.getTimelines()]);
  };

  Drupal.OikoComparativeTimeline.prototype.updateCurrentWindowItem = function() {
    var timeWindow = this._visTimeline.getWindow();
    // Ensure that we have a browser window element.
    this._visItems.update({
      // Timeline information.
      id: WINDOW_SLIDER_ID,
      type: 'range',
      start: timeWindow.start,
      end: timeWindow.end,

      // Information for our summary timeline.
      _summaryType: 'range',
      _summaryClass: 'currentWindow'
    });
    
    // Ensure that the current window item is selected in the overview.
    var selections = this._visTimelineOverview.getSelection();
    if (selections.indexOf(WINDOW_SLIDER_ID) === -1) {
      this._visTimelineOverview.setSelection(WINDOW_SLIDER_ID);
    }
  };

  Drupal.OikoComparativeTimeline.prototype.updateTimelineBounds = function() {
    var moved = false;
    if (this._timelineMin != Infinity) {
      this._visTimeline.setOptions({
        min: this._timelineMin * 1000
      });
      this._visTimelineOverview.setOptions({
        min: this._timelineMin * 1000,
        start: this._timelineMin * 1000
      });
      moved = true;
    }
    if (this._timelineMax != -Infinity) {
      this._visTimeline.setOptions({
        max: this._timelineMax * 1000
      });
      this._visTimelineOverview.setOptions({
        max: this._timelineMax * 1000,
        end: this._timelineMax * 1000
      });
      moved = true;
    }

    if ((this._timelineMin != Infinity) && (this._timelineMax != -Infinity)) {
      this._visTimeline.setOptions({
        zoomMax: (this._timelineMax - this._timelineMin) * 1000
      });
      this._visTimelineOverview.setOptions({
        zoomMax: (this._timelineMax - this._timelineMin) * 1000
      });
    }

    if (moved && !this.rangeAdjusted) {
      this._visTimeline.fit({animation: false});
      this._visTimelineOverview.fit({animation: false});
    }

    this._visTimeline.redraw();
    this._visTimelineOverview.redraw();
  };

  /**
   * Update the DOM to match our state.
   *
   * The idea is that this function should be idempotent.
   */
  Drupal.OikoComparativeTimeline.prototype.updateTheDOM = function() {
    // Hide or show the overview container depending on the number of timelines we have.
    this.$overviewContainer.toggle(this.getTimelines().length > 0);

    // Hide or show the overview container depending on the number of timelines we have.
    this.$timelineContainer.toggle(this.getTimelines().length > 0);

    // Hide preseleections if you have more than one item in the timeline.
    this.$preselectionsContainer.toggle(this.getTimelines().length < 2);
  };

})(jQuery);

// Polyfill for indexOf.
// Production steps of ECMA-262, Edition 5, 15.4.4.14
// Reference: http://es5.github.io/#x15.4.4.14
if (!Array.prototype.indexOf) {
  Array.prototype.indexOf = function(searchElement, fromIndex) {

    var k;

    // 1. Let o be the result of calling ToObject passing
    //    the this value as the argument.
    if (this == null) {
      throw new TypeError('"this" is null or not defined');
    }

    var o = Object(this);

    // 2. Let lenValue be the result of calling the Get
    //    internal method of o with the argument "length".
    // 3. Let len be ToUint32(lenValue).
    var len = o.length >>> 0;

    // 4. If len is 0, return -1.
    if (len === 0) {
      return -1;
    }

    // 5. If argument fromIndex was passed let n be
    //    ToInteger(fromIndex); else let n be 0.
    var n = fromIndex | 0;

    // 6. If n >= len, return -1.
    if (n >= len) {
      return -1;
    }

    // 7. If n >= 0, then Let k be n.
    // 8. Else, n<0, Let k be len - abs(n).
    //    If k is less than 0, then let k be 0.
    k = Math.max(n >= 0 ? n : len - Math.abs(n), 0);

    // 9. Repeat, while k < len
    while (k < len) {
      // a. Let Pk be ToString(k).
      //   This is implicit for LHS operands of the in operator
      // b. Let kPresent be the result of calling the
      //    HasProperty internal method of o with argument Pk.
      //   This step can be combined with c
      // c. If kPresent is true, then
      //    i.  Let elementK be the result of calling the Get
      //        internal method of o with the argument ToString(k).
      //   ii.  Let same be the result of applying the
      //        Strict Equality Comparison Algorithm to
      //        searchElement and elementK.
      //  iii.  If same is true, return k.
      if (k in o && o[k] === searchElement) {
        return k;
      }
      k++;
    }
    return -1;
  };
}

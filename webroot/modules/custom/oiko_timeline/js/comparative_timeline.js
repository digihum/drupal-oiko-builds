(function ($) {
  "use strict";

var WINDOW_SLIDER_ID = 'window-slider';

Drupal.behaviors.comparative_timeline = {
  attach: function(context, settings) {
    $(context).find('.js-comparative-timeline-container').once('comparative_timeline').each(function() {
      var $component = $(this);
      var id = $component.attr('id');
      var timelineSettings, timeline;
      // Ensure that there's an oiko_timeline collection on the settings collection.
      settings.oiko_timeline = settings.oiko_timeline || {};
      if ($component.data('comparative_timeline') == undefined) {
        // Find our settings.
        timelineSettings = settings.oiko_timeline[id] || {};

        if (timelineSettings.hasOwnProperty('pagestate') && timelineSettings.pagestate) {
          Drupal.oiko.addAppModule('comparative-timeline');
        }
        timeline = new Drupal.OikoComparativeTimeline($component, timelineSettings);
        $component.data('comparative_timeline', timeline);
        if (timelineSettings.hasOwnProperty('pagestate') && timelineSettings.pagestate) {
          Drupal.oiko.timeline = timeline;
          Drupal.oiko.appModuleDoneLoading('comparative-timeline');
        }

      }
    });
  }
};


  /**
   * Define a comparative timeline widget.
   *
   * @param $outerContainer
   * @param element_settings
   * @constructor
   */
  Drupal.OikoComparativeTimeline = function ($outerContainer, element_settings) {
    var timeline = this;
    var defaults = {
      loadingItems: {},
      preselectedLinks: [],
      isLoading: false,
      categories: [],
      crmTypes: [],
      window: {},
      rangeAdjusted: false,
      interactive: true,
      pagestate: false,
      initialData: false,
      hasPreselectionsAvailable: false,
      $outerContainer: $outerContainer,
      _timelineMin: Infinity,
      _timelineMax: -Infinity
    };

    $.extend(this, defaults, element_settings);

    // Set vis timeline options.
    timeline._setVisTimelineOptions.call(timeline);

    // Find the DOM elements.
    timeline._findDOMElements.call(timeline);

    // Init the vis items.
    timeline._initVisElements.call(timeline);

    // Add the preselected options.
    timeline._loadPreselectionsAndInitialData.call(timeline);

    // Hook events up.
    timeline._initialiseEvents.call(timeline);

    // Now that we're all loaded up, update the DOM.
    timeline.updateTheDOM.call(timeline);
  };

  /**
   * Setup our vis timeline options.
   */
  Drupal.OikoComparativeTimeline.prototype._setVisTimelineOptions = function () {
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
      selectable : this.isInteractive(),
      moveable: this.isInteractive(),
      zoomable: this.isInteractive(),
      hiddenDates: [
        {start: '0000-01-01 00:00:00', end: '0001-01-01 00:00:00'}
      ],
      format: {
        minorLabels: {
          year: 'PPPP',
          month: 'MMM PPPP'
        },
        majorLabels: {
          weekday: 'MMMM PPPP',
          day: 'MMMM PPPP',
          month: 'PPPP'
        }
      },
      showMajorLabels: false,
      moment: vis.moment.utc,
      zoomMax: 1000 * 86400 * 365.25 * 100,
      zoomMin: 1000 * 86400 * 365.25
    };

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
  };

  /**
   * Find our DOM elements.
   */
  Drupal.OikoComparativeTimeline.prototype._findDOMElements = function () {
    this.$timelineContainer = this.$outerContainer.find('.js-comparative-timeline');
    this.$overviewContainer = this.$outerContainer.find('.js-comparative-timeline-overview');
    this.$addNewContainer = this.$outerContainer.find('.js-comparative-timeline-add-new');
    this.$preselectionsContainer = this.$outerContainer.find('.js-comparative-timeline-preselections');
    this.$ajaxLoader = this.$outerContainer.find('.js-loading-graphic');
  };

  /**
   * Init the vis items we'll be using.
   */
  Drupal.OikoComparativeTimeline.prototype._initVisElements = function () {
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
  };

  /**
   * Add preselections and initial data.
   */
  Drupal.OikoComparativeTimeline.prototype._loadPreselectionsAndInitialData = function () {
    var timeline = this;
    if (timeline.hasOwnProperty('defaultOptions')) {
      var defaultOptionTitle, defaultOptionId;
      for (defaultOptionId in timeline.defaultOptions) {
        timeline.hasPreselectionsAvailable = true;
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

    // If we've got some initialData then set that up now.
    if (timeline.initialData && typeof timeline.initialData.id !== 'undefined') {
      timeline.addDataToTimeline.call(timeline, timeline.initialData);
    }
  };

  /**
   * Setup the event handling and hooks for our widget.
   */
  Drupal.OikoComparativeTimeline.prototype._initialiseEvents = function () {
    var timeline = this;
    if (this.isInteractive()) {
      this._visTimeline
        // Selecting a timeline item, should open the sidebar.
        .on('select', function(properties) {
          timeline.selectedTimelineItems.call(timeline, properties);
        })
        // Changing the range of the main vis timeline.
        .on('rangechange', $.proxy(function(e) {
          this.updateCurrentWindowItem();
        }, this))
        // Changed the range of the main vis timeline.
        .on('rangechanged', $.proxy(function(e) {
          this.updateCurrentWindowItem();
          this.window = {
            start: Math.round(e.start.getTime() / 1000),
            end: Math.round(e.end.getTime() / 1000)
          };
          if (timeline.isPagestate.call(timeline)) {
            // Execute this in the 'next tick'.
            setTimeout(function () {
              $(window).trigger('oiko.timelineRangeChanged');
            }, 1);
          }
        }, this));

      // Clicking remove links should remove those items.
      this.$timelineContainer.bind('click', function(e) {
        var $target = $(e.target);
        if ($target.is('.js-comparative-timeline-remove-link')) {
          // We need to remove this group.
          if ($target.data('groupId')) {
            timeline.removeGroupFromTimeline($target.data('groupId'));
          }
        }
      });


      // Interacting with the overview timeline should update the main vis timeline.
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
    }

    // If we're tied into the global page state, reflect that.
    if (timeline.isPagestate.call(timeline)) {
      $(window).bind('oikoSidebarOpen', function (e, id) {
        // Find the selected item in our items, and select it.
        var selectedItems = timeline._visItems.getIds({
          filter: function (item) {
            return item.event == id;
          }
        });
        timeline._visTimeline.setSelection(selectedItems, {focus: selectedItems.length > 0});
      });
      $(window).bind('set.oiko.categories', function (e, categories) {
        timeline.setCategories(categories);
      });
      $(window).bind('selected.timeline.searchitem', function (e, id) {
        timeline.loadDataHandler.call(timeline, parseInt(id, 10));
      });
    }
  };

  /**
   * Should the timeline be connected to the global pagestate.
   *
   * @returns {boolean}
   */
  Drupal.OikoComparativeTimeline.prototype.isPagestate = function () {
    return this.pagestate;
  };

  /**
   * Should the timeline be interactive.
   *
   * @returns {boolean}
   */
  Drupal.OikoComparativeTimeline.prototype.isInteractive = function () {
    return this.interactive;
  };

  /**
   * Get timelines displayed by this widget.
   *
   * @param includeLoading
   *   Include timelines that have yet to load their data.
   *
   * @returns {*}
   */
  Drupal.OikoComparativeTimeline.prototype.getTimelines = function (includeLoading) {
    if (typeof includeLoading === 'undefined') {
      includeLoading = true;
    }
    if (includeLoading) {
      var visGroups = this._visGroups.getIds();
      var loadingItems = this.getLoadingIds();
      return loadingItems.concat(visGroups);
    }
    else {
      return this._visGroups.getIds();
    }
  };

  Drupal.OikoComparativeTimeline.prototype.setTimelines = function (timelines) {
    if (!timelines.length) {debugger};
    // Remove all existing timelines.
    this.removeGroupsFromTimeline(this.getTimelines(), false);

    // And now add the timelines we want.
    for (var i in timelines) {
      if (timelines[i] && !this.isLoadingCheck(timelines[i])) {
        this.loadDataHandler(timelines[i]);
      }
    }

    if (timelines.length) {
      // Show/hide the preselections as needed.
      this.updateTheDOM();
      $(window).trigger('oiko.timelines_updated', [this.getTimelines()]);
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

  /**
   * Handler to load data for a group.
   *
   * @param groupID
   */
  Drupal.OikoComparativeTimeline.prototype.loadDataHandler = function (groupID) {
    var timeline = this;
    var url = '/comparative-timeline/data/' + groupID;
    var request = $.get(url, function(data) {
      timeline.doneLoading.call(timeline, groupID);
      timeline.addDataToTimeline.call(timeline, data);
    });
    this.nowLoading(groupID, request);
  };

  Drupal.OikoComparativeTimeline.prototype.nowLoading = function(id, request) {
    this.loadingItems[id] = request;
    this.evalLoadingState();
  };

  Drupal.OikoComparativeTimeline.prototype.isLoadingCheck = function(id) {
    return typeof this.loadingItems[id] !== 'undefined';
  };

  Drupal.OikoComparativeTimeline.prototype.doneLoading = function(id) {
    delete this.loadingItems[id];
    this.evalLoadingState();
  };

  Drupal.OikoComparativeTimeline.prototype.abortLoading = function(id) {
    if (this.loadingItems[id]) {
      this.loadingItems[id].abort();
      delete this.loadingItems[id];
    }
    this.evalLoadingState();
  };

  Drupal.OikoComparativeTimeline.prototype.getLoadingIds = function() {
    return Object.keys(this.loadingItems);
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

  /**
   * Recompute the displayed min and max for our timeline items.
   *
   * If the timeline is in interactive mode, then we add a little more padding
   * to either side of the bounds for nicer display and scrolling.
   */
  Drupal.OikoComparativeTimeline.prototype.recomputeMinMaxOfItems = function() {
    var timeline = this;
    this._timelineMin = Infinity;
    this._timelineMax = -Infinity;
    var pad = this.isInteractive() ? 86400 * 365 * 10 : 0;
    this._visItems.forEach(function(item) {
      if (item.id !== WINDOW_SLIDER_ID) {
        timeline._timelineMin = Math.min(timeline._timelineMin, ((item.start / 1000) - pad));
        timeline._timelineMax = Math.max(timeline._timelineMax, ((item.end / 1000) + pad));
      }
    });

  };

  Drupal.OikoComparativeTimeline.prototype.removeGroupFromTimeline = function(groupId, fireEvents) {
    if (typeof fireEvents === 'undefined') {
      fireEvents = true;
    }

    // If we're loading this group, then abort that Ajax request.
    if (this.isLoadingCheck(groupId)) {
      this.abortLoading(groupId);
    }

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

    this.recomputeMinMaxOfItems();
    this.updateTimelineBounds();
    this.updateCurrentWindowItem();

    // If this is one of the pre-built links, put it back.
    for (var i in this.preselectedLinks) {
      if ($(this.preselectedLinks[i]).data('groupId') == groupId) {
        $(this.preselectedLinks[i]).show();
      }
    }

    if (fireEvents) {
      // Show/hide the preselections as needed.
      this.updateTheDOM();
      $(window).trigger('oiko.timelines_updated', [this.getTimelines()]);
    }
  };

  Drupal.OikoComparativeTimeline.prototype.removeGroupsFromTimeline = function(groups, fireEvents) {
    if (typeof fireEvents === 'undefined') {
      fireEvents = true;
    }

    if (groups.length) {
      for (var i in groups) {
        this.removeGroupFromTimeline(groups[i], false);
      }

      if (fireEvents) {
        // Show/hide the preselections as needed.
        this.updateTheDOM();
        $(window).trigger('oiko.timelines_updated', [this.getTimelines()]);
      }
    }
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

  Drupal.OikoComparativeTimeline.prototype.stringsGroupLabel = function(data) {
    if (this.isInteractive()) {
      return '<span class="js-comparative-timeline-remove-link fa fa-times" data-group-id="' + data.id + '"></span>&nbsp;' + data.label + data.logo;
    }
    else {
      return data.label + data.logo;
    }
  };

  Drupal.OikoComparativeTimeline.prototype.addDataToTimeline = function(data) {
    // We have some data, we should add it to the timeline.
    var groupId = data.id;
    // Add a group:
    this._visGroups.add([{
      id: groupId,
      content: this.stringsGroupLabel(data)
    }]);

    if (typeof data.events !== 'undefined') {
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
      }
      this._visItems.add(newEvents);
    }

    this.recomputeMinMaxOfItems();

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
    this.updateTimelineBounds();
    this.updateCurrentWindowItem();
  };

  Drupal.OikoComparativeTimeline.prototype.updateCurrentWindowItem = function() {
    if (this.getTimelines().length > 0) {
      var timeWindow = this._visTimeline.getWindow();
      // Ensure that we have a browser window element.
      this._visItems.update({
        // Timeline information.
        id: WINDOW_SLIDER_ID,
        type: 'range',
        start: timeWindow.start,
        end: timeWindow.end,

        // Flag to force an update.
        __random: Math.random(),

        // Information for our summary timeline.
        _summaryType: 'range',
        _summaryClass: 'currentWindow'
      });

      // Ensure that the current window item is selected in the overview.
      var selections = this._visTimelineOverview.getSelection();
      if (selections.indexOf(WINDOW_SLIDER_ID) === -1) {
        this._visTimelineOverview.setSelection(WINDOW_SLIDER_ID);
      }
    }
    else {
      this._visItems.remove(WINDOW_SLIDER_ID);
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
      this._visTimeline.setWindow(this._timelineMin * 1000, this._timelineMax * 1000, {animation: false});
    }
    if (moved) {
      this._visTimelineOverview.setWindow(this._timelineMin * 1000, this._timelineMax * 1000, {animation: false});
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
    if (this.isInteractive()) {
      // Hide or show the overview container depending on the number of timelines we have.
      this.$overviewContainer.toggle(this.getTimelines().length > 0);
    }
    else {
      this.$overviewContainer.toggle(false);
    }

    // Hide or show the overview container depending on the number of timelines we have.
    this.$timelineContainer.toggle(this.getTimelines().length > 0);

    // Hide preseleections if you have more than one item in the timeline.
    if (this.hasPreselectionsAvailable) {
      this.$preselectionsContainer.toggle(this.getTimelines().length < 2);
    }
    else {
      this.$preselectionsContainer.toggle(false);
    }

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

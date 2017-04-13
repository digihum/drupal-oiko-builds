(function ($) {

Drupal.oiko.addAppModule('comparative-timeline');

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

  // Debounced keyup.
  $.fn.delayKeyup = function (callback, ms) {
    var timer = 0;
    $(this).keyup(function (event) {

      if (event.keyCode !== 13 && event.keyCode !== 38 && event.keyCode !== 40) {
        clearTimeout(timer);
        timer = setTimeout(function () {
          callback(event);
        }, ms);
      }
      else {
        callback(event);
      }
    });
    return $(this);
  };

  Drupal.OikoComparativeTimelineSearch = function ($outerContainer, element_settings) {
    var instance = this;
    var defaults = {
      results: [],
      resultCount: 0,
      collapseOnBlur: true,
      options: {
        placeholderMessage: "Search",
        searchButtonTitle: "Search",
        clearButtonTitle: "Clear",
        notFoundMessage: "not found.",
        notFoundHint: "Make sure your search criteria is correct and try again."
      },
      init : function (container) {
        var element = $(container);
        var $searchBox = $('<input class="leaflet-searchBox" placeholder="' + this.options.placeholderMessage + '"/>');
        element.append($searchBox);
        var $searchButton = $('<input class="leaflet-searchButton" type="submit" value="" title="' + this.options.searchButtonTitle + '"/>');
        element.append($searchButton);
        element.append('<span class="leaflet-divider"></span>');
        var $clearButton = $('<input class="leaflet-clearButton" type="submit"  value="" title="' + this.options.clearButtonTitle + '">');
        element.append($clearButton);

        instance.$resultsDiv = $("<div class='leaflet-result'><div>");
        element.append(instance.$resultsDiv);


        $searchBox.delayKeyup(function (event) {
          switch (event.keyCode) {
            case 13: // enter
              if (instance.activeResult !== -1) {
                instance.searchResultSelected.call(instance, instance.activeResult);
              }
              else {
                instance.searchButtonClick.call(instance);
              }
              break;
            case 38: // up arrow
              instance.prevResult.call(instance);
              break;
            case 40: // down arrow
              instance.nextResult.call(instance);
              break;
            case 37: //left arrow, Do Nothing
            case 39: //right arrow, Do Nothing
              break;
            default:
              if ($searchBox.val().length > 0) {
                instance.getValuesAsGeoJson.call(instance);
              }
              else {
                instance.clearButtonClick.call(instance);
              }
              break;
          }
        }, 300);

        $searchBox.focus(function () {
          if (instance.$resultsDiv.length) {
            instance.$resultsDiv[0].style.display = "block";
          }
        });

        $searchBox.blur(function () {
          if (instance.$resultsDiv.length) {
            if (instance.collapseOnBlur) {
              instance.$resultsDiv[0].style.display = "none";
            }
            else {
              instance.collapseOnBlur = true;

              window.setTimeout(function ()
              {
                instance.$searchBox.focus();
              }, 0);
            }
          }

        });

        $searchButton.click(function () {
          instance.searchButtonClick.call(instance);
        });

        $clearButton.click(function () {
          instance.clearButtonClick.call(instance);
        });

        this.$searchBox = $searchBox;
        this.$searchButton = $searchButton;
        this.$clearButton = $clearButton;

        return container;
      },
      searchButtonClick: function() {
        this.$searchBox.focus();
      },
      clearButtonClick: function () {
        this.$searchBox.val('');
        this.lastSearch = "";
        this.resultCount = 0;
        this.results = [];
        this.activeResult = -1;
        this.$resultsDiv.empty();
        this.$searchBox.focus();
      },
      nextResult: function() {
        if (this.resultCount > 0) {
          this.$resultsDiv.find('.leaflet-result-list-item').removeClass('mouseover');
          if (this.activeResult !== -1) {
            this.$resultsDiv.find('.leaflet-result-list-item').removeClass('active');
          }

          if (this.activeResult < this.resultCount - 1) {
            this.activeResult++;
            this.$resultsDiv.find(".leaflet-result-list-item[data-index='" + this.activeResult + "']").addClass('active');
          }
          else {
            this.activeResult = -1;
          }

          this.fillSearchBox.call(this);
        }
      },
      prevResult: function() {
        if (this.resultCount > 0) {
          this.$resultsDiv.find('.leaflet-result-list-item').removeClass('mouseover');
          if (this.activeResult !== -1) {
            this.$resultsDiv.find('.leaflet-result-list-item').removeClass('active');
          }

          if (this.activeResult === -1) {
            this.activeResult = this.resultCount - 1;
            this.$resultsDiv.find(".leaflet-result-list-item[data-index='" + this.activeResult + "']").addClass('active');
          }
          else if (this.activeResult === 0) {
            this.activeResult--;
          }
          else {
            this.activeResult--;
            this.$resultsDiv.find(".leaflet-result-list-item[data-index='" + this.activeResult + "']").addClass('active');
          }

          this.fillSearchBox.call(this);
        }
      },
      processNoRecordsFoundOrError: function() {
        this.resultCount = 0;
        this.results = [];
        this.activeResult = -1;
        this.$resultsDiv.empty();

        this.$resultsDiv.append("<i>" + this.lastSearch + " " + this.options.notFoundMessage + " <p><small>" + this.options.notFoundHint + "</small></i>");
      },
      getValuesAsGeoJson: function () {

        var instance = this;

        this.activeResult = -1;
        this.lastSearch = this.$searchBox.val();

        if (this.lastSearch === "") {
          return;
        }

        $.ajax({
          url: Drupal.url('search/timeline-crm-entities/' + this.lastSearch),
          type: 'GET',
          dataType: 'json',
          success: function (json) {
            instance.results = [];
            // Populate the instance.results;
            for (var i in json) {
              if (json.hasOwnProperty(i)) {
                var result = json[i];
                instance.results[instance.results.length] = {
                  properties: {
                    title: result.name,
                    description: result.bundle,
                    id: result.id
                  }
                };
              }
            }
            instance.resultCount = instance.results.length;
            if (instance.resultCount) {
              instance.createDropDown.call(instance);
            }
            else {
              instance.processNoRecordsFoundOrError.call(instance);
            }
          },
          error: function () {
            instance.processNoRecordsFoundOrError.call(instance);
          }
        });
      },
      createDropDown: function createDropDown() {
        var instance = this;
        var parent = this.$searchBox.parent();

        instance.$resultsDiv.empty();
        var $resultsList = $("<ul class='leaflet-result-list'></ul>");
        instance.$resultsDiv.append($resultsList);

        for (var i = 0; i < this.results.length; i++) {
          var html = "<li class='leaflet-result-list-item' data-index='" + i + "'>";
          html += "<span class='content'>";
          html += "<font size='2' color='#333' class='title'>" + this.results[i].properties.title + "</font><font size='1' color='#8c8c8c'> " + this.results[i].properties.description + "<font></span></li>";

          var $resultItem = $(html);

          $resultsList.append($resultItem);

          $resultItem.mouseenter(function () {
            instance.listElementMouseEnter.call(instance, this);
          });

          $resultItem.mouseleave(function () {
            instance.listElementMouseLeave.call(instance, this);
          });

          $resultItem.mousedown(function () {
            instance.listElementMouseDown.call(instance, this);
          });
        }
      },
      listElementMouseEnter: function (listElement) {

        var $listElement = $(listElement);

        var index = parseInt($listElement.data('index'), 10);

        if (index !== this.activeResult) {
          $listElement.addClass('mouseover');
        }
      },
      listElementMouseLeave: function (listElement) {
        var $listElement = $(listElement);
        var index = parseInt($listElement.data('index'), 10);

        if (index !== this.activeResult) {
          $listElement.removeClass('mouseover');
        }
      },
      listElementMouseDown: function (listElement) {
        var $listElement = $(listElement);
        var index = parseInt($listElement.data('index'), 10);

        if (index !== this.activeResult) {
          if (this.activeResult !== -1) {
            this.$resultsDiv.find('.leaflet-result-list-item').removeClass('active');
          }

          $listElement.removeClass('mouseover');
          $listElement.addClass('active');

          this.activeResult = index;
          this.fillSearchBox.call(this);

          this.searchResultSelected.call(this, this.activeResult);
        }
      },
      fillSearchBox: function () {
        if (this.activeResult === -1) {
          this.$searchBox.val(this.lastSearch);
        }
        else {
          this.$searchBox.val(this.results[this.activeResult].properties.title);
        }
      },
      searchResultSelected: function(index) {
        this.$searchBox.blur();
        this.timeline.searchBoxSelectHandler.call(this.timeline, this.results[index]);

      }
    };

    $.extend(this, defaults, element_settings);

    this.$outerContainer = this.init.call(this, $outerContainer);

    return this;
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
    this.$addNewContainer = this.$outerContainer.find('.js-comparative-timeline-add-new');
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
        axis: 'bottom',
        item: 'top'
      },
      selectable : true,
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
      moment: vis.moment.utc,
      zoomMax: 1000 * 86400 * 365.25 * 100,
      zoomMin: 1000 * 86400 * 365.25
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
      if (!this.isLoadingCheck(timelines[i])) {
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

  Drupal.OikoComparativeTimeline.prototype.buildSearchBox = function () {
    return this.$addNewContainer.data('search_box', new Drupal.OikoComparativeTimelineSearch(this.$addNewContainer, {timeline: this}));
  };

  Drupal.OikoComparativeTimeline.prototype.searchBoxSelectHandler = function (item) {
    this.loadDataHandler.call(this, item.properties.id);
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
  };

  Drupal.OikoComparativeTimeline.prototype.isLoadingItems = function() {
    return this.isLoading;
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
    return this._filterItemsCategoriesCallback(item) && this._filterItemsCRMTypesCallback(item);
  };

  Drupal.OikoComparativeTimeline.prototype.initialise = function () {
    var timeline = this;
    // Construct the vis timeline datasets.
    this._visItems = new vis.DataSet({});
    this._visDisplayedItems = new vis.DataView(this._visItems, {
      filter: $.proxy(timeline._filterItemsCallback, timeline),
    });
    this._visGroups = new vis.DataSet({});
    this._visTimeline = new vis.Timeline(this.$timelineContainer.get(0), this._visDisplayedItems, this._visGroups, this._timelineOptions);

    // Add the preselected options.
    if (timeline.hasOwnProperty('defaultOptions')) {
      var defaultOptionTitle, defaultOptionId;
      for (defaultOptionId in timeline.defaultOptions) {
        defaultOptionTitle = timeline.defaultOptions[defaultOptionId];
        var $link = $('<a href="#">').text(defaultOptionTitle).data('groupId', defaultOptionId).addClass('comparative-timeline--preselect-link').click(function(e) {
          e.preventDefault();
          timeline.loadDataHandler.call(timeline, $(this).data('groupId'));
          $(this).hide();
        });
        this.$addNewContainer.append($link);
        this.preselectedLinks.push($link);
      }
    }

    // Add the comparision select box.
    this.buildSearchBox.call(this);

    // Hook events up.
    this._visTimeline
      .on('select', function(properties) {
        timeline.selectedTimelineItems.call(timeline, properties);
      })
      .on('rangechanged', $.proxy(function(e) {
        this.window = {
          start: Math.round(e.start.getTime() / 1000),
          end: Math.round(e.end.getTime() / 1000)
        };
        $(window).trigger('oiko.timelineRangeChanged');
      }, this));
    this.$timelineContainer.bind('click', function(e) {
      $target = $(e.target);
      if ($target.is('.js-comparative-timeline-remove-link')) {
        // We need to remove this group.
        if ($target.data('groupId')) {
          timeline.removeGroupFromTimeline($target.data('groupId'));
        }
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

    // If this is one of the pre-built links, put it back.
    for (var i in this.preselectedLinks) {
      if ($(this.preselectedLinks[i]).data('groupId') == groupId) {
        $(this.preselectedLinks[i]).show();
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
          id: groupId + '-' + event.id,
          type: event.type == 'period' ? 'background' : 'range',
          content: event.label + ' ' + event.date_title,
          title: event.label,
          start: minmin * 1000,
          end: maxmax * 1000,
          group: data.id,
          event: event.id,
          className: 'oiko-timeline-item--' + event.color,
          significance: parseInt(event.significance, 10),
          crmType: event.crm_type
        });

        this._timelineMin = Math.min(this._timelineMin, (minmin - 86400 * 365 * 10));
        this._timelineMax = Math.max(this._timelineMax, (maxmax + 86400 * 365 * 10));
      }
      this._visItems.add(newEvents);
    }

    // Refresh our data view.
    this._visDisplayedItems.refresh();

    this.updateTimelineBounds();

    // If this is one of the pre-built links, put it back.
    for (var i in this.preselectedLinks) {
      if ($(this.preselectedLinks[i]).data('groupId') == groupId) {
        $(this.preselectedLinks[i]).hide();
      }
    }

    $(window).trigger('oiko.timelines_updated', [this.getTimelines()]);
  };

  Drupal.OikoComparativeTimeline.prototype.updateTimelineBounds = function() {
    var moved = false;
    if (this._timelineMin != Infinity) {
      this._visTimeline.setOptions({
        min: this._timelineMin * 1000
      });
      moved = true;
    }
    if (this._timelineMax != -Infinity) {
      this._visTimeline.setOptions({
        max: this._timelineMax * 1000
      });
      moved = true;
    }

    if ((this._timelineMin != Infinity) && (this._timelineMax != -Infinity)) {
      this._visTimeline.setOptions({
        zoomMax: (this._timelineMax - this._timelineMin) * 1000
      });
    }

    if (moved && !this.rangeAdjusted) {
      this._visTimeline.fit();
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

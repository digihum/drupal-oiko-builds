(function ($) {

Drupal.behaviors.comparative_timeline = {
  attach: function(context, settings) {
    $(context).find('.js-comparative-timeline-container').once('comparative_timeline').each(function() {
      var $component = $(this);
      if ($component.data('comparative_timeline') == undefined) {
        $component.data('comparative_timeline', new Drupal.OikoComparativeTimeline($component, settings.oiko_timeline));
      }
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
      preselectedLinks: []
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
      timeline.doneLoading(groupID);
      timeline.addDataToTimeline.call(timeline, data);
    });
  };

  Drupal.OikoComparativeTimeline.prototype.nowLoading = function(id) {
    this.loadingItems[id] = true;
    this.evalLoadingState();
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
  };

  Drupal.OikoComparativeTimeline.prototype.initialise = function () {
    var timeline = this;
    // Construct the vis timeline datasets.
    this._visItems = new vis.DataSet({});
    this._visGroups = new vis.DataSet({});
    this._visTimeline = new vis.Timeline(this.$timelineContainer.get(0), this._visItems, this._visGroups, this._timelineOptions);

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
        this.$addNewContainer.append($link)
        this.preselectedLinks.push($link);
      }
    }

    // Add the comparision select box.
    this.buildSearchBox.call(this);

    // Hook events up.
    this._visTimeline.on('select', function(properties) {
      timeline.selectedTimelineItems.call(timeline, properties);
    });
    this.$timelineContainer.bind('click', function(e) {
      $target = $(e.target);
      if ($target.is('.js-comparative-timeline-remove-link')) {
        // We need to remove this group.
        if ($target.data('groupId')) {
          timeline.removeGroupFromTimeline($target.data('groupId'));
        }
      }
    });
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
      Drupal.oiko.openSidebar(selected.substr(1 + properties.items[i].lastIndexOf('-')), item.title, false);
    }
  };

  Drupal.OikoComparativeTimeline.prototype.addDataToTimeline = function(data) {
    // We have some data, we should add it to the timeline.
    var groupId = data.id;
    // Add a group:
    this._visGroups.add([{
      id: groupId,
      content: '<span class="js-comparative-timeline-remove-link fa fa-times" data-group-id="' + data.id + '"></span>&nbsp;' + data.label
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
          className: 'oiko-timeline-item--' + event.color
        });

        this._timelineMin = Math.min(this._timelineMin, (minmin - 86400 * 365 * 10));
        this._timelineMax = Math.max(this._timelineMax, (maxmax + 86400 * 365 * 10));
      }
      this._visItems.add(newEvents);
    }
    this.updateTimelineBounds();

    // If this is one of the pre-built links, put it back.
    for (var i in this.preselectedLinks) {
      if ($(this.preselectedLinks[i]).data('groupId') == groupId) {
        $(this.preselectedLinks[i]).hide();
      }
    }
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

    if (moved) {
      this._visTimeline.fit();
    }
  };

})(jQuery);

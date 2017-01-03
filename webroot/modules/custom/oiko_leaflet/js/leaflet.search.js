(function ($) {

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

  L.SearchControl = L.Control.extend({
    options: {
      position: 'topleft',
      placeholderMessage: "Search",
      searchButtonTitle: "Search",
      clearButtonTitle: "Clear",
      notFoundMessage: "not found.",
      notFoundHint: "Make sure your search criteria is correct and try again."
    },
    onAdd: function (map) {
      var instance = this;
      var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom searchContainer');

      this.results = [];
      this.resultCount = 0;
      this.collapseOnBlur = true;
      this.map = map;

      //  @TOOO convert to use leaflet creation functions.
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

      container.style.backgroundColor = 'white';

      $searchBox.delayKeyup(function (event) {
        switch (event.keyCode) {
          case 13: // enter
            instance.searchButtonClick.call(instance);
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

        if (this.activeResult !== -1) {
          this.searchResultSelected.call(this, this.activeResult);
        }
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

        if (this.activeResult !== -1) {
          this.searchResultSelected.call(this, this.activeResult);
        }
      }
    },
    processNoRecordsFoundOrError: function() {
      this.resultCount = 0;
      this.results = [];
      this.activeResult = -1;
      this.$resultsDiv.empty();

      this.$resultsDiv.append('<div class="leaflet-search--not-found"><i>' + this.lastSearch + " " + this.options.notFoundMessage + " <p><small>" + this.options.notFoundHint + "</small></i></div>");
    },
    getValuesAsGeoJson: function () {

      var instance = this;

      this.activeResult = -1;
      this.lastSearch = this.$searchBox.val();

      if (this.lastSearch === "") {
        return;
      }

      $.ajax({
        url: Drupal.url('search/crm-entities/' + this.lastSearch),
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
      this.map.fireEvent('searchItem', this.results[index]);
    }
  });

  L.searchControl = function (opts) {
    return new L.SearchControl(opts);
  };



})(jQuery);

(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    if (drupalLeaflet.map_definition.hasOwnProperty('search') && drupalLeaflet.map_definition.search) {
      map.addControl(L.searchControl());

      var featureCache = {};

      // Build up a lovely map of Drupal feature id to lat/lon or bounds.
      $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
        if (drupalLeaflet.map_definition.hasOwnProperty('search') && drupalLeaflet.map_definition.search) {
          if (feature.hasOwnProperty('id') && feature.id) {
            if (feature.hasOwnProperty('lat') && feature.hasOwnProperty('lon')) {
              featureCache[feature.id] = {
                lat: feature.lat,
                lon: feature.lon
              };
            }
            else if (typeof lFeature.getBounds === 'function') {
              featureCache[feature.id] = {
                bounds: lFeature.getBounds().pad(0.5)
              };
            }
            else {
              // We don't know how to handle anything else at the moment.
            }
          }
        }
      });

      // Listen for the searchItem event on the map, used when someone selects an item for searching.
      map.addEventListener('searchItem', function (e) {
        var id = e.properties.id;
        var title = e.properties.title;
        if (featureCache.hasOwnProperty(id)) {
          if (featureCache[id].hasOwnProperty('lat')) {
            map.panTo(L.latLng(featureCache[id].lat, featureCache[id].lon), {animate: true, duration: 0.5});
          }
          else if (featureCache[id].hasOwnProperty('bounds')) {
            map.fitBounds(featureCache[id].bounds, {animate: true, duration: 0.5});
          }
          else {
            // We don't know how to handle anything else at the moment.
          }
        }
      });

    }

  });

})(jQuery);
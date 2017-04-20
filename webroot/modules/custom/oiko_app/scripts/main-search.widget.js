(function ($) {
  "use strict";

  Drupal.behaviors.oiko_main_search = {
    attach: function(context, settings) {
      $(context).find('.js-main-search-wrapper').once('oiko_main_search').each(function() {
        var $component = $(this);
        if (!$component.data('oiko_main_search')) {
          var mainSearch = new Drupal.OikoMainSearch($component);
          $component.data('oiko_main_search', mainSearch);
        }
      });
    }
  };


  Drupal.OikoMainSearch = function ($outerContainer, element_settings) {
    var that = this;
    var defaults = {

      /**
       * Initialise the search box.
       */
      initialise: function () {
        var that = this;

        // Bind so we know where to send our searches.
        $(window).on('set.oiko.visualisation', function(e, visualisation) {
          if (visualisation === 'map' || visualisation === 'timeline') {
            that._visualisation = visualisation;
            // Invalidate the current results.
            if (that._ajaxRequest) {
              that._ajaxRequest.abort();
            }
            that._isLoadingResults = false;
            that._currentResults = [];
            that._activeResult = -1;
            if (that._currentSearch.length > 0) {
              that.executeSearch(that._currentSearch);
            }
          }
        });

        // Bind to the events we need on the search box.
        this.$searchBox.on('focus blur', function() {
          that.render();
        });

        this.$searchBox.delayKeyup(function (event) {
          switch (event.keyCode) {
            case 13: // enter
              if (that._activeResult !== -1) {
                that.searchResultSelected(that._currentResults[that._activeResult].id);
              }
              break;
            case 38: // up arrow
              that.prevResult();
              break;
            case 40: // down arrow
              that.nextResult();
              break;
            case 37: //left arrow, Do Nothing
            case 39: //right arrow, Do Nothing
              break;
            default:
              that._currentSearch = that.$searchBox.val();
              if (that._currentSearch.length > 0) {
                that.executeSearch(that._currentSearch);
              }
              else {
                if (that._ajaxRequest) {
                  that._ajaxRequest.abort();
                }
                that._isLoadingResults = false;
                that._currentResults = [];
                that._activeResult = -1;
              }
              break;
          }

          that.render();
        }, 250);
        // Finally, do a quick render.
        that.render();
      },

      /**
       * Render the current state of the widget, displaying the correct stuff etc.
       */
      render: function () {
        var that = this;

        // Hide and show the various elements.
        that.$noSearchContainer.toggleClass('is-hidden', that._currentSearch.length > 0);
        that.$noResultsContainer.toggleClass('is-hidden', that._isLoadingResults || that._currentSearch.length === 0 || that._currentResults.length > 0);
        that.$resultsContainer.toggleClass('is-hidden', that._isLoadingResults || that._currentResults.length === 0);
        that.$loadingContainer.toggleClass('is-hidden', !that._isLoadingResults);

        // If this is a different search from the last one we rendered, clear out the results container.
        var lastRenderedSearch = '';
        // @TODO: use this condition, and re-write the code below it.
        // if (lastRenderedSearch !== that._currentSearch) {
          that.$resultsContainer.empty();
        // }

        // Render the search results, if we have some.
        var result, $resultItem;
        for (var i = 0; i < that._currentResults.length; i++) {
          result = that._currentResults[i];
          var html = '<li class="result-link" data-index="'+ i + '">';
          html += '<span class="category-label category-label--' + result.color + '">' + result.type + '</span> ';
          html += result.title + '</li>';

          $resultItem = $(html);

          that.$resultsContainer.append($resultItem);

          // Bind to the click handler.
          $resultItem.on('mousedown', function () {
            that._resultClicked(this);
          });

          $resultItem.toggleClass('active', that._activeResult === i);
        }


      },

      /**
       * Event handler for went a search result is clicked.
       *
       * @param listElement
       * @private
       */
      _resultClicked: function (listElement) {
        var that = this;
        var $listElement = $(listElement);
        var index = parseInt($listElement.data('index'), 10);
        that.searchResultSelected(that._currentResults[index].id);
      },

      /**
       * Select a search result and send appropriate events so that the page reacts.
       *
       * @param id
       */
      searchResultSelected: function(id) {
        var that = this;
        that.$searchBox.blur();
        that.render();
        if (that._visualisation === 'timeline') {
          $(window).trigger('selected.timeline.searchitem', [id])
        }
        else {
          $(window).trigger('selected.map.searchitem', [id])
        }
      },

      /**
       * Move the selected result to the 'previous' result.
       */
      prevResult: function() {
        var that = this;
        if (that._currentResults.length > 0) {
          if (that._activeResult === -1) {
            that._activeResult = that._currentResults.length - 1;
          }
          else {
            that._activeResult--;
          }
        }
      },

      /**
       * Move the selected result to the 'next' result.
       */
      nextResult: function() {
        var that = this;
        if (that._currentResults.length > 0) {
          if (that._activeResult < that._currentResults.length - 1) {
            that._activeResult++;
          }
          else {
            that._activeResult = -1;
          }
        }
      },

      /**
       * Execute a search, via ajax.
       *
       * @param searchString
       */
      executeSearch:  function (searchString) {
        var that = this;
        var url;
        // Cancel the previous search if there was one.
        if (that._ajaxRequest) {
          that._ajaxRequest.abort();
        }

        that._isLoadingResults = false;

        if (that._visualisation === 'timeline') {
          url = 'search/timeline-crm-entities/' + searchString;
        }
        else {
          url = 'search/crm-entities/' + searchString;
        }

        that._ajaxRequest = $.ajax({
          // @TODO: We need to change this search URL somehow.
          url: Drupal.url(url),
          type: 'GET',
          dataType: 'json',
          beforeSend: function () {
            that._isLoadingResults = true;
            that._currentResults = [];
            that._activeResult = -1;
            that.render();
          },
          success: function (json) {
            that._isLoadingResults = false;
            // Populate the instance.results;
            for (var i in json) {
              if (json.hasOwnProperty(i)) {
                var result = json[i];
                that._currentResults.push({
                  title: result.name,
                  type: result.bundle,
                  color: result.color,
                  id: result.id
                });
              }
            }
            that.render();
          },
          error: function () {
            that._isLoadingResults = false;
            that.render();
          }
        });
      }
    };

    $.extend(this, defaults, element_settings);

    this._currentSearch = '';
    this._currentResults = [];
    this._ajaxRequest = false;
    this._isLoadingResults = false;
    this._activeResult = -1;
    this._visualisation = 'map';

    this.$outerContainer = $outerContainer;

    this.$searchBox = $outerContainer.find('.js-input');
    this.$noSearchContainer = $outerContainer.find('.js-no-search-text');
    this.$noResultsContainer = $outerContainer.find('.js-no-results-text');
    this.$resultsContainer = $outerContainer.find('.js-results-listing');
    this.$loadingContainer = $outerContainer.find('.js-loading-results-text');


    this.initialise.call(this);
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

})(jQuery);

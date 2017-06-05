(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  var event_history = localforage.createInstance({
    name: "event_history"
  });

  var redraw_history = function() {
    var events = [];
    event_history.iterate(function(value, key, iterationNumber) {
      events.push(value);
    })
    .then(function() {
      return events.sort(function(a, b) {
        return b.timestamp - a.timestamp;
      }).splice(0, 5);
    })
    .then(function(processed_events) {
      $('.js-event-history').html(Drupal.theme('eventHistory', processed_events));
    });
  };

  Drupal.oiko.eventHistory = {
    add: function(id, title) {
      var item = {
        id: id,
        title: title,
        timestamp: new Date().getTime()
      };
      event_history.setItem('' + id, item).then(redraw_history);
    },
    remove: function(id) {
      event_history.removeItem('' + id).then(redraw_history);
    }
  };

  /**
   * A toggle is an interactive element often bound to a click handler.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.eventHistory = function (events) {
    if (events.length) {
      var output = '<ul class="event-list__list">';
      $.each(events, function (key, val) {
        output += '<li class="event-list__item" data-event-id="' + val.id + '"><a href="#" class="js-event-history-item">' + val.title + '</a><button class="js-event-history-close event-list__remove"><span aria-hidden="true">&times;</span></button></li>'
      });
      output += '</ul>';

      return output;
    }
    else {
      return Drupal.t('Start browsing around the site to start building your history.');
    }
  };


  Drupal.behaviors.oiko_event_history = {
    attach: function(context, settings) {
      $(context).find('.js-event-history').once('oiko_event_history').each(function () {
        redraw_history();

        var $history = $(this);
        $history.on('click', '.js-event-history-item', function(e) {
          $(window).trigger('oikoSidebarOpen', $(e.target).closest('li').data('event-id'));
          e.preventDefault();
        });
        $history.on('click', '.js-event-history-close', function(e) {
          Drupal.oiko.eventHistory.remove($(e.target).closest('li').data('event-id'));
        });
      });
    }
  };

  /**
   * Command to push a state into the history API.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.oikoEventHistoryAdd = function (ajax, response) {
    Drupal.oiko.eventHistory.add(response.id, response.title);
  };

})(jQuery);

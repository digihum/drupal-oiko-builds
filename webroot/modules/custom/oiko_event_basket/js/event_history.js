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
      event_history.setItem(id, item).then(redraw_history);
    },
    remove: function(id) {
      event_history.removeItem(id).then(redraw_history);
    }
  };

  /**
   * A toggle is an interactive element often bound to a click handler.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.eventHistory = function (events) {
    var output = '<h4>My journey</h4><ul>';
    $.each(events, function(key, val) {
      output += '<li data-event-id="' + val.id + '">' + val.title + '<button class="js-event-history-close"><span aria-hidden="true">&times;</span></button></li>'
    });
    return output + '</ul>';
  };


  Drupal.behaviors.oiko_event_history = {
    attach: function(context, settings) {
      $(context).find('.js-event-history').once('oiko_event_history').each(function () {
        redraw_history();

        var $history = $(this);
        $history.on('click', '.js-event-history-close', function(e) {
          Drupal.oiko.eventHistory.remove($(e.target).closest('li').data('event-id'));
        });

        $(window).on('oikoSidebarOpened', function(e, id) {
          Drupal.oiko.eventHistory.add('event-' + id, $('.sidebar-information-content-title').text());
        })
      });
    }
  };

})(jQuery);

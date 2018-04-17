(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  var event_basket = localforage.createInstance({
    name: "event_basket"
  });

  var redraw_basket = function() {
    var events = [];
    event_basket.iterate(function(value, key, iterationNumber) {
      events.push(value);
    })
    .then(function() {
      return events.sort(function(a, b) {
        return a.timestamp - b.timestamp;
      })
    })
    .then(function(processed_events) {
      $('.js-event-basket').html(Drupal.theme('eventBasket', processed_events));
    });
  };

  Drupal.oiko.eventBasket = {
    add: function(id, title, delay) {
      var item = {
        id: id,
        title: title,
        timestamp: new Date().getTime()
      };
      // For some reason opening the dropdown in the same event loop closed, it, so do so with a delay.
      setTimeout(function() {
        $('.js-event-basket-dropdown:visible').foundation('open');
      }, 1);
      // Add the item with a small delay so it's more obvious what has happened.
      setTimeout(function() {
        event_basket.setItem('' + id, item).then(redraw_basket);
      }, delay ? delay : 300);
    },
    remove: function(id) {
      event_basket.removeItem('' + id).then(redraw_basket);
    }
  };

  /**
   * A toggle is an interactive element often bound to a click handler.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.eventBasket = function (events) {
    if (events.length) {
      var output = '<ul class="event-list__list">';
      $.each(events, function (key, val) {
        output += '<li class="event-list__item" data-event-id="' + val.id + '"><a href="#" class="js-event-basket-item">' + val.title + '</a><button class="js-event-basket-close event-list__remove"><span aria-hidden="true">&times;</span></button></li>'
      });
      output += '</ul>';

      if (typeof Drupal.oiko.timeline !== 'undefined') {
        output += '<button class="event-list__cta button button-highlight js-view-timelines">' + Drupal.t('View timelines') + '</button>';
      }

      return output;
    }
    else {
      return Drupal.t('Add some events to get started.');
    }
  };


  Drupal.behaviors.oiko_event_basket = {
    attach: function(context, settings) {
      $(context).find('.js-event-basket').once('oiko_event_basket').each(function () {
        redraw_basket();

        var $basket = $(this);
        $basket.on('click', '.js-event-basket-item', function(e) {
          $(window).trigger('oikoSidebarOpen', $(e.target).closest('li').data('event-id'));
          e.preventDefault();
        });
        $basket.on('click', '.js-event-basket-close', function(e) {
          Drupal.oiko.eventBasket.remove($(e.target).closest('li').data('event-id'));
        });
        $basket.on('click', '.js-view-timelines', function(e) {
          // Scoop up all the items, and set the timelines viewer to view them.
          var events = [];
          event_basket.iterate(function(value, key, iterationNumber) {
            events.push(value.id);
          })
          .then(function() {
            $('.js-event-basket-dropdown').foundation('close');
            $(window).trigger('set.oiko.visualisation', 'timeline');
            Drupal.oiko.timeline.setTimelines(events);
          });
        });
      });
    }
  };

})(jQuery);

(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  var event_basket = localforage.createInstance({
    name: "event_basket"
  });

  var redraw_basket = function() {
    var events = {};
    event_basket.iterate(function(value, key, iterationNumber) {
      events[key] = value;
    }).then(function() {
      $('.js-event-basket').html(Drupal.theme('eventBasket', events));
    });
  };

  Drupal.oiko.eventBasket = {
    add: function(id, title) {
      event_basket.setItem(id, title).then(redraw_basket);
    },
    remove: function(id) {
      event_basket.removeItem(id).then(redraw_basket);
    }
  };

  /**
   * A toggle is an interactive element often bound to a click handler.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.eventBasket = function (events) {
    var output = '<ul>';
    $.each(events, function(key, val) {
      output += '<li data-event-id="' + key + '">' + val + '<button class="js-event-basket-close"><span aria-hidden="true">&times;</span></button></li>'
    });
    return output + '</ul>';
  };


  Drupal.behaviors.oiko_event_basket = {
    attach: function(context, settings) {
      $(context).find('.js-event-basket').once('oiko_event_basket').each(function () {
        redraw_basket();

        var $basket = $(this);
        $basket.on('click', '.js-event-basket-close', function(e) {
          Drupal.oiko.eventBasket.remove($(e.target).closest('li').data('event-id'));
        });
      });
    }
  };

})(jQuery);
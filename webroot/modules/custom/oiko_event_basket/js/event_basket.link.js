(function ($) {
  'use strict';

  Drupal.behaviors.oiko_event_basket_links = {
    attach: function(context) {
      $(context).find('.js-add-event-basket').once('oiko_event_basket_links').each(function () {
        var $link = $(this);
        $link.bind('click', function(e) {
          e.preventDefault();
          Drupal.oiko.eventBasket.add($link.data('eventId'), $link.data('eventTitle'));
        });
      });
    }
  };


})(jQuery);

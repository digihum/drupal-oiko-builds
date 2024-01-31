(function ($) {
  'use strict';

  Drupal.behaviors.oiko_event_basket_links = {
    attach: function(context) {
      $(once('oiko_event_basket_links', '.js-add-event-basket', context)).each(function () {
        var $link = $(this);
        $link.bind('click', function(e) {
          e.preventDefault();
          Drupal.oiko.eventBasket.add($link.data('eventId'), $link.data('eventTitle'));
        });
      });
    }
  };


})(jQuery);

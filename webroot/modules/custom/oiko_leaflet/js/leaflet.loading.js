(function ($) {
  'use strict';

  $('.js-legend-tab-link').removeClass('disabled');

  $(window).bind('oiko.loaded', function() {
    $('.js-map-loader').hide();
  });
})(jQuery);

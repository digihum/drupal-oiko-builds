(function ($, Drupal) {
  Drupal.behaviors.oiko_visualization_switcher = {
    attach: function (context) {
      $('.js-switch-visualization', context).once('oiko_visualization_switcher').on('click', function() {
        var $link = $(this);
        if ($link.attr('data-visualization')) {
          $(window).trigger('set.oiko.visualisation', $link.attr('data-visualization'));
        }
      });
    }
  };
})(jQuery, Drupal);

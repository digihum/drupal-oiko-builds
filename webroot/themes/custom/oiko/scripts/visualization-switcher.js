(function ($, Drupal, once) {
  Drupal.behaviors.oiko_visualization_switcher = {
    attach: function (context) {
      $(once('oiko_visualization_switcher', '.js-switch-visualization', context)).on('click', function() {
        var $link = $(this);
        if ($link.attr('data-visualization')) {
          $(window).trigger('set.oiko.visualisation', $link.attr('data-visualization'));
        }
      });
    }
  };
})(jQuery, Drupal, once);

(function($) {
  Drupal.behaviors.foundation = {
    attach: function (context) {
      $(context).foundation();
      $('[data-toggle]', context).click(function(e) {
        e.preventDefault();
      });
    }
  };
})(jQuery);
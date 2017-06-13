(function ($, Drupal) {
  Drupal.behaviors.oiko_messages_reveal = {
    attach: function (context) {
      $('.js-highlighted-reveal', context).once('oiko_highlighted_reveal').each(function() {
        var $this = $(this);
        if ($this.children().length || $this.text().trim().length) {
          $this.foundation('open');
        }
      });
    }
  };
})(jQuery, Drupal);

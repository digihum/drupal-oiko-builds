(function ($, Drupal) {
  Drupal.behaviors.oiko_messages_reveal = {
    attach: function (context) {
      $('.js-highlighted-reveal', context).not('.js-no-process').once('oiko_highlighted_reveal').each(function() {
        var $this = $(this);
        if ($this.children().length || $this.text().trim().length) {
          // Add the close button.
          $this.append('<button class="close-button" data-close aria-label="Close modal" type="button"><span aria-hidden="true">&times;</span></button>');
          $this.foundation('open');
        }
      });
    }
  };
})(jQuery, Drupal);

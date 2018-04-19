(function ($, Drupal, storage) {
  "use strict";

  Drupal.behaviors.oiko_modal_welcome = {
    attach: function (context) {
      $('.js-modal-welcome', context).once('oiko_modal_welcome').each(function() {
        var $modal = $(this);
        var id = $modal.attr('id');
        $modal.on('closed.zf.reveal', function() {
          // Record the date the modal was dismissed, in the future, we can support displaying it again if there are changes.
          storage.setItem('Drupal.oiko_modal_welcome.' + id, (new Date).getTime());
        });
        if (id) {
          var item = storage.getItem('Drupal.oiko_modal_welcome.' + id);
          if (!item) {
            $modal.foundation('open');
          }
        }

        // Search for a menu link that should also display the modal.
        $(document).on('click', function(e) {
          var $link = $(e.target);
          if ($link.attr('href') && $link.attr('href').indexOf('#welcome-modal') !== -1) {
            // This is a link to reveal the modal
            $modal.foundation('open');
            e.preventDefault();
          }

        })
      });
    }
  };
})(jQuery, Drupal, window.localStorage);

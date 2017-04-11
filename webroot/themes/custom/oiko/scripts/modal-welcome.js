(function ($, Drupal, storage) {
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
      });
    }
  };
})(jQuery, Drupal, window.localStorage);

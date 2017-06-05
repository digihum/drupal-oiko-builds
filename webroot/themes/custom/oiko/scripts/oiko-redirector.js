(function ($, Drupal) {
  "use strict";
  $(window).bind('oikoSidebarOpen', function(e, id) {
    // Redirect to the CRM URL.
    window.location = Drupal.url('cidoc-entity/' + id);
  });

})(jQuery, Drupal);

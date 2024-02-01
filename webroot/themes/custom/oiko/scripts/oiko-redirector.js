(function ($, Drupal) {
  "use strict";
  Drupal.oiko = Drupal.oiko || {};

  // Add some nice code so that the sidebar will work on any page.
  Drupal.behaviors.oiko_sidebar_not_app = {
    attach: function (context, settings) {
      $(once('oiko_sidebar_not_app', 'a[data-cidoc-id]', context)).on('click', function (e) {
        var $target = $(e.target);
        var id = $target.data('cidoc-id');
        if (id) {
          e.preventDefault();
          Drupal.oiko.openSidebar(id);
        }
      });
    }
  };

  var currentSidebarEntity, lastAjaxRequest;
  $(once('oiko-redirector', window)).bind('oikoSidebarOpen', function(e, id) {
    // @TODO: Maybe introduce a setting to control this.
    if (false) {
      // Redirect to the CRM URL.
      window.location = Drupal.url('cidoc-entity/' + id);
    }
    else {
      // If the 'app' is around, let it do the work.
      if (typeof Drupal.oiko.getAppState === 'undefined') {
        if (id !== currentSidebarEntity) {
          currentSidebarEntity = id;
          if (lastAjaxRequest) {
            lastAjaxRequest.abort();
          }
          Drupal.oiko.sidebar.open('information');
          // Replace the content with the loading content.
          Drupal.oiko.displayLoadingContentInLeafletSidebar();
          lastAjaxRequest = Drupal.oiko.displayContentInLeafletSidebar(id, function () {
            lastAjaxRequest = null;
          }, function () {
          });
        }
      }
    }
  });

})(jQuery, Drupal);

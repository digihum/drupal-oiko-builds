(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};


  Drupal.behaviors.oiko_sidebar = {
    attach: function(context, settings) {

      $(context).find('.oiko-sidebar').once('oiko_sidebar').each(function () {
        Drupal.oiko.sidebar = new Drupal.Sidebar(this, {position: 'right'});
      });
    }
  };

  Drupal.oiko.openSidebar = function (id, label, changeHistoryState) {
    // Open the sidebar.
    if (Drupal.oiko.hasOwnProperty('sidebar')) {
      Drupal.oiko.sidebar.open('information');

      // Replace the content with the loading content.
      Drupal.oiko.displayLoadingContentInLeafletSidebar(label);

      // Set up an AJAX request to replace the content.
      Drupal.oiko.displayContentInLeafletSidebar(id, changeHistoryState);
    }

  };

  Drupal.oiko.displayLoadingContentInLeafletSidebar = function(label) {
    $('#leaflet-sidebar .sidebar-information-content-title').text(label);
    $('#leaflet-sidebar .sidebar-information-content-content').text(Drupal.t('Loading details...'));
    $('#leaflet-sidebar .sidebar-discussion-content-content').text(Drupal.t('Loading discussions...'));
  };

  Drupal.oiko.displayContentInLeafletSidebar = function(id, changeHistoryState) {
    var element_settings = {};
    // Clicked links look better with the throbber than the progress bar.
    element_settings.progress = {type: 'none'};

    // For anchor tags, these will go to the target of the anchor rather
    // than the usual location.
    element_settings.url = '/cidoc-entity/' + id + '/popup';
    element_settings.oikoLeafletHistoryState = changeHistoryState;
    Drupal.ajax(element_settings).execute();
  };

  window.addEventListener('popstate', function(e) {
    if (e.state && e.state.hasOwnProperty('type') && e.state.type === 'popup') {
      Drupal.oiko.openSidebar(e.state.id, e.state.label);
    }
  });

  /**
   * Command to push a state into the history API.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.historyPush = function (ajax, response, status) {
    if (ajax.hasOwnProperty('oikoLeafletHistoryState') && ajax.oikoLeafletHistoryState) {
      // Change the address in the URL bar, if possible.
      if (!!(window.history && history.pushState)) {
        var fragment = location.hash;
        window.history.pushState(response.data, response.title, response.url + fragment);
      }
      else {
        // Should we polyfill?
      }
    }
  };

})(jQuery);
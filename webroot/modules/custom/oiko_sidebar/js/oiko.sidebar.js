(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  Drupal.behaviors.oiko_sidebar = {
    attach: function(context, settings) {

      $(context).find('.oiko-sidebar').once('oiko_sidebar').each(function () {
        Drupal.oiko.sidebar = new Drupal.Sidebar(this, {position: 'right'});
        Drupal.oiko.sidebar.$sidebar.on('click', function(e) {
          var $target = $(e.target);
          var id = $target.data('cidoc-id');
          var label = $target.data('cidoc-label');
          if (id) {
            e.preventDefault();
            // Fall back to using the link text as the new sidebar title.
            Drupal.oiko.openSidebar(id, !!(label) ? label : $target.text(), true);
          }
        });
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

  Drupal.oiko.openSidebarLegend = function () {
    // Open the sidebar.
    if (Drupal.oiko.hasOwnProperty('sidebar')) {
      Drupal.oiko.sidebar.open('legend');
    }
  };

  Drupal.oiko.displayLoadingContentInLeafletSidebar = function(label) {
    $('#leaflet-sidebar .sidebar-information-content-title').text(label);
    $('#leaflet-sidebar .sidebar-information-content-content').text(Drupal.t('Loading details...'));
    $('#leaflet-sidebar .sidebar-discussion-content-content').text(Drupal.t('Loading discussions...'));
    $('#leaflet-sidebar .sidebar-share-content-content').text(Drupal.t('Loading share links...'));
  };

  Drupal.oiko.displayContentInLeafletSidebar = function(id, changeHistoryState) {
    // Display the main information.
    var element_settings = {};
    element_settings.progress = {type: 'none'};

    // For anchor tags, these will go to the target of the anchor rather
    // than the usual location.
    element_settings.url = '/cidoc-entity/' + id + '/popup';
    element_settings.oikoLeafletHistoryState = changeHistoryState;
    Drupal.ajax(element_settings).execute();

    // Load in the discussion content.
    var discussion_element_settings = {};
    discussion_element_settings.progress = {type: 'none'};

    // For anchor tags, these will go to the target of the anchor rather
    // than the usual location.
    discussion_element_settings.url = '/discussion/' + id + '/popup';
    discussion_element_settings.oikoLeafletHistoryState = false;
    Drupal.ajax(discussion_element_settings).execute();

    // Load in the social links.
    var social_element_settings = {};
    social_element_settings.progress = {type: 'none'};
    social_element_settings.url = '/share/' + id + '/popup';
    social_element_settings.oikoLeafletHistoryState = false;
    Drupal.ajax(social_element_settings).execute();
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
  Drupal.AjaxCommands.prototype.oikoGAEvent = function (ajax, response) {
    if (typeof ga !== 'undefined') {
      ga('send', response.event, response.args);
    }
  };

})(jQuery);

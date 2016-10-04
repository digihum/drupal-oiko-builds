(function ($) {
  'use strict';

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    // Add the sidebar control if there's a sidebar control in the page markup.
    var $leafletSidebar = $('#leaflet-sidebar');
    if ($leafletSidebar.length) {
      Drupal.globalDrupalLeaflet = drupalLeaflet;
      drupalLeaflet.sidebarControl = L.control.sidebar('leaflet-sidebar', {position: 'right'}).addTo(map);

      // Link into the click event for markers.

      // When clicking a link to a cidoc entity from the sidebar, replace the
      // sidebar instead of navigating to it.
      $leafletSidebar.on('click', function(e) {
        var $target = $(e.target);
        var id = $target.data('cidoc-id');
        var label = $target.data('cidoc-label');
        if (id) {
          e.preventDefault();
          // Fall back to using the link text as the new sidebar title.
          Drupal.oiko.openLeafletSidebar(id, !!(label) ? label : $target.text(), drupalLeaflet, true);
        }
      });

      // Check to see if we need to open the sidebar immediately.
      $(document).once('oiko_leaflet__popups').each(function () {
        if (drupalSettings.hasOwnProperty('oiko_leaflet') && drupalSettings.oiko_leaflet.hasOwnProperty('popup') && drupalSettings.oiko_leaflet.popup) {
          // We might need to wait for everything we need to be loaded.

          Drupal.oiko.openLeafletSidebar(drupalSettings.oiko_leaflet.popup.id, drupalSettings.oiko_leaflet.popup.label, Drupal.globalDrupalLeaflet, false);
        }
      });
    }
  });

  var leafletPopupOpen = function(e) {
    this.openPopup();
  };
  var leafletPopupClose = function(e) {
    this.closePopup();
  };
  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    // Remove the popup.
    if (typeof lFeature.unbindPopup !== 'undefined') {
      lFeature.unbindPopup();
    }

    // lFeature.on('mouseover', leafletPopupOpen);
    // lFeature.on('mouseout', leafletPopupClose);


    // Add a click event that opens our marker in the sidebar.
    lFeature.on('click', function() {
      Drupal.oiko.openLeafletSidebar(feature.id, feature.label, drupalLeaflet, true);
    });
  });

  Drupal.oiko = Drupal.oiko || {};

  Drupal.oiko.openLeafletSidebar = function(id, label, drupalLeaflet, changeHistoryState) {
    // Open the sidebar.
    drupalLeaflet.sidebarControl.open('information');

    // Replace the content with the loading content.
    Drupal.oiko.displayLoadingContentInLeafletSidebar(label, drupalLeaflet);

    // Set up an AJAX request to replace the content.
    Drupal.oiko.displayContentInLeafletSidebar(id, drupalLeaflet, changeHistoryState);

  };

  Drupal.oiko.displayLoadingContentInLeafletSidebar = function(label, drupalLeaflet) {
    $('#leaflet-sidebar .sidebar-information-content-title').text(label);
    $('#leaflet-sidebar .sidebar-information-content-content').text(Drupal.t('Loading details...'));
    $('#leaflet-sidebar .sidebar-discussion-content-content').text(Drupal.t('Loading discussions...'));
  };

  Drupal.oiko.displayContentInLeafletSidebar = function(id, drupalLeaflet, changeHistoryState) {
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
      // This was a popup that we displayed earlier, call the same function again.
      if (typeof Drupal.globalDrupalLeaflet !== 'undefined') {
        Drupal.oiko.openLeafletSidebar(e.state.id, e.state.label, Drupal.globalDrupalLeaflet, false);
      }
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
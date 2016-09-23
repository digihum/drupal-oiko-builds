(function ($) {
  'use strict';

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    // Add the sidebar control if there's a sidebar control in the page markup.
    if ($('#leaflet-sidebar').length) {
      drupalLeaflet.sidebarControl = L.control.sidebar('leaflet-sidebar', {position: 'right'}).addTo(map);

      // Link into the click event for markers.

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
    lFeature.unbindPopup();

    // lFeature.on('mouseover', leafletPopupOpen);
    // lFeature.on('mouseout', leafletPopupClose);


    // Add a click event that opens our marker in the sidebar.
    lFeature.on('click', function() {
      Drupal.oiko.openLeafletSidebar(feature.id, feature.label, drupalLeaflet);
    });
  });

  Drupal.oiko = Drupal.oiko || {};

  Drupal.oiko.openLeafletSidebar = function(id, label, drupalLeaflet) {
    // Open the sidebar.
    drupalLeaflet.sidebarControl.open('information');

    // Replace the content with the loading content.
    Drupal.oiko.displayLoadingContentInLeafletSidebar(label, drupalLeaflet);

    // Set up an AJAX request to replace the content.
    Drupal.oiko.displayContentInLeafletSidebar(id, drupalLeaflet);

  };

  Drupal.oiko.displayLoadingContentInLeafletSidebar = function(label, drupalLeaflet) {
    $('#leaflet-sidebar .sidebar-information-content-title').text(label);
    $('#leaflet-sidebar .sidebar-information-content-content').text(Drupal.t('Loading details...'));
    $('#leaflet-sidebar .sidebar-discussion-content-content').text(Drupal.t('Loading discussions...'));
  };

  Drupal.oiko.displayContentInLeafletSidebar = function(id, drupalLeaflet) {
    var element_settings = {};
    // Clicked links look better with the throbber than the progress bar.
    element_settings.progress = {type: 'none'};

    // For anchor tags, these will go to the target of the anchor rather
    // than the usual location.
    element_settings.url = '/cidoc-entity/' + id + '/popup';
    // element_settings.event = 'oiko.leaflet_sidebar_data_load' + id;
    // element_settings.element = $('#leaflet-sidebar')[0];
    Drupal.ajax(element_settings).execute();
  };

})(jQuery);
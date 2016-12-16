(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    // Add the sidebar control if there's a sidebar control in the page markup.
    if (mapDefinition.sidebar && Drupal.oiko.hasOwnProperty('sidebar')) {
      drupalLeaflet.hasSidebar = true;

      // Link into the click event for markers.
      // When clicking a link to a cidoc entity from the sidebar, replace the
      // sidebar instead of navigating to it.
      Drupal.oiko.sidebar.$sidebar.on('click', function(e) {
        var $target = $(e.target);
        var id = $target.data('cidoc-id');
        var label = $target.data('cidoc-label');
        if (id) {
          e.preventDefault();
          // Fall back to using the link text as the new sidebar title.
          Drupal.oiko.openSidebar(id, !!(label) ? label : $target.text(), drupalLeaflet, true);
        }
      });

      // Check to see if we need to open the sidebar immediately.
      $(document).once('oiko_leaflet__popups').each(function () {
        if (drupalSettings.hasOwnProperty('oiko_leaflet') && drupalSettings.oiko_leaflet.hasOwnProperty('popup') && drupalSettings.oiko_leaflet.popup) {
          // We might need to wait for everything we need to be loaded.

          Drupal.oiko.openSidebar(drupalSettings.oiko_leaflet.popup.id, drupalSettings.oiko_leaflet.popup.label, false);
        }
      });
    }

    map.addEventListener('searchItem', function(e) {
      var id = e.properties.id;
      var title = e.properties.title;
      Drupal.oiko.openSidebar(id, title, true);
    });
  });

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    if (drupalLeaflet.hasSidebar) {
      // Remove the popup.
      if (typeof lFeature.unbindPopup !== 'undefined') {
        lFeature.unbindPopup();
      }

      // Add a click event that opens our marker in the sidebar.
      lFeature.on('click', function () {
        Drupal.oiko.openSidebar(feature.id, feature.label, true);
      });
    }
  });

})(jQuery);

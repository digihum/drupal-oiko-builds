(function ($) {

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    drupalLeaflet.oms = new OverlappingMarkerSpiderfier(map);

    drupalLeaflet.oms.addListener('spiderfy', function() {
      // Handily this closes the 'normal' popup we get on click.
      map.closePopup();
    });
  });

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    switch (feature.type) {
      case 'point':
        // We only handle points at the moment.
        drupalLeaflet.oms.addMarker(lFeature);
        break;
    }
  });

})(jQuery);

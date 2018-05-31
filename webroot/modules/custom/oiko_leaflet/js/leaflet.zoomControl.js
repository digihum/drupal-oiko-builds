(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    if (drupalLeaflet.map_definition.hasOwnProperty('zoomControl') && drupalLeaflet.map_definition.zoomControl) {
      L.control.zoom({
        position: drupalLeaflet.map_definition.zoomControl
      }).addTo(map);
    }
  });

})(jQuery);

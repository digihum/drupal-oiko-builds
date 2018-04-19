(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    if (drupalLeaflet.map_definition.hasOwnProperty('locate') && drupalLeaflet.map_definition.locate) {
      L.control.locate({
        position: 'bottomright',
        drawCircle: false,
        locateOptions: {
          maxZoom: 10
        }
      }).addTo(map);
    }
  });

})(jQuery);

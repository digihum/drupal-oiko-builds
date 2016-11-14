(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    if (drupalLeaflet.map_definition.hasOwnProperty('locate') && drupalLeaflet.map_definition.locate) {
      L.control.locate({
        position: 'bottomleft',
        drawCircle: false,
        locateOptions: {
          maxZoom: 10
        }
      }).addTo(map);
    }
  });

})(jQuery);

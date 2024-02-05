(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {
    if (mapDefinition.hasOwnProperty('locate') && mapDefinition.locate) {
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

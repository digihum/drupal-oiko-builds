(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {

    if (mapDefinition.hasOwnProperty('zoomControl') && mapDefinition.zoomControl) {
      L.control.zoom({
        position: mapDefinition.zoomControl
      }).addTo(map);
    }
  });

})(jQuery);

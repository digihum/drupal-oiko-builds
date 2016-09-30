(function ($) {
  'use strict';

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    if (feature.hasOwnProperty('type') && feature.type == 'linestring') {
      if (feature.hasOwnProperty('directional') && feature.directional) {
        lFeature.setText('            \u25BA            ', {
          repeat: true,
          offset: 7,
          center: true,
          attributes: {
            fill: lFeature.options.color,
            'fill-opacity': Math.min(1, lFeature.options.opacity * 1.1),
            'font-size': '20px'
          }
        });
      }
    }
  });

})(jQuery);
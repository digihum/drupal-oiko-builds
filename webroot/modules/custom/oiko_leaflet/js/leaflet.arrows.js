(function ($) {
  'use strict';

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    if (feature.hasOwnProperty('type') && feature.type === 'linestring') {
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

  var hashCode = function(str){
    var hash = 0, char;
    if (str.length == 0) return hash;
    for (var i = 0; i < str.length; i++) {
      char = str.charCodeAt(i);
      hash = ((hash<<5)-hash)+char;
      hash = hash & hash; // Convert to 32bit integer
    }
    return hash;
  }

})(jQuery);
(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    drupalLeaflet.create_point = function (marker) {
      var latLng = new L.LatLng(marker.lat, marker.lon);
      this.bounds.push(latLng);
      var lMarker;

      if (marker.hasOwnProperty('color') && marker.color) {
        var icon = this.create_icon_with_color(marker.color);
        lMarker = new L.Marker(latLng, {icon: icon});
      }
      else {
        lMarker = new L.Marker(latLng);
      }
      return lMarker;
    };

    drupalLeaflet.create_icon_with_color = function (color) {
      var iconcolor;
      if (drupalSettings.leaflet_icons.hasOwnProperty(color)) {
        iconcolor = color;
      }
      else {
        iconcolor = 'blue';
      }

      var icon = new L.Icon({
        iconUrl: drupalSettings.leaflet_icons[iconcolor],
        iconSize: [25, 40],
        iconAnchor:   [13, 39],
        shadowUrl: drupalSettings.leaflet_icons['shadow'],
        shadowSize: [41, 41]
      });

      return icon;
    }

  });

})(jQuery);

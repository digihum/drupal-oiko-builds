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

    var lookupColor = function(color) {
      switch (color) {
        case 'green':
          return '#44CB80';

        case 'purple':
          return '#6745CC';

        case 'red':
          return '#B1364B';

        case 'turquoise':
          return '#45CCC7';

        case 'yellow':
          return '#CCC344';

        case 'blue':
        default:
          return '#4798D0';
      }
    };

    drupalLeaflet.create_polygon = function (polygon) {
      var latlngs = [];
      for (var i = 0; i < polygon.points.length; i++) {
        var latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
        latlngs.push(latlng);
        this.bounds.push(latlng);
      }
      var options = {
        stroke: false,
        smoothFactor: 2.0,
        fillOpacity: 0.3
      };
      if (polygon.hasOwnProperty('color')) {
        options.color = lookupColor(polygon.color);
      }
      else {
        options.color = lookupColor('');
      }
      return new L.Polygon(latlngs, options);
    };

    drupalLeaflet.create_linestring = function (polyline) {
      var latlngs = [];
      for (var i = 0; i < polyline.points.length; i++) {
        var latlng = new L.LatLng(polyline.points[i].lat, polyline.points[i].lon);
        latlngs.push(latlng);
        this.bounds.push(latlng);
      }
      var options = {
      };
      if (polyline.hasOwnProperty('color')) {
        options.color = lookupColor(polyline.color);
      }
      else {
        options.color = lookupColor('');
      }
      return new L.Polyline(latlngs, options);
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
    };

    drupalLeaflet.create_multipoly = function (multipoly) {
      var polygons = [];
      for (var x = 0; x < multipoly.component.length; x++) {
        var latlngs = [];
        var polygon = multipoly.component[x];
        for (var i = 0; i < polygon.points.length; i++) {
          var latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
          latlngs.push(latlng);
          this.bounds.push(latlng);
        }
        polygons.push(latlngs);
      }
      if (multipoly.multipolyline) {
        var options = {
        };
        if (multipoly.hasOwnProperty('color')) {
          options.color = lookupColor(multipoly.color);
        }
        else {
          options.color = lookupColor('');
        }
        return new L.MultiPolyline(polygons, options);
      }
      else {
        var options = {
          stroke: false,
          smoothFactor: 2.0,
          fillOpacity: 0.3
        };
        if (multipoly.hasOwnProperty('color')) {
          options.color = lookupColor(multipoly.color);
        }
        else {
          options.color = lookupColor('');
        }
        return new L.Polygon(polygons, options);
      }
    };

  });

})(jQuery);

(function ($) {
  'use strict';
  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    drupalLeaflet.create_point = function (marker) {
      var latLng = new L.LatLng(marker.lat, marker.lon);
      this.bounds.push(latLng);
      var lMarker;
      var options = {};

      if (marker.hasOwnProperty('color') && marker.color) {
        options.icon = this.create_icon_with_color(marker.color);
      }
      else if (marker.hasOwnProperty('markerClass') && marker.markerClass) {
        options.icon = this.create_div_icon(marker);
      }
      if (marker.hasOwnProperty('pane') && marker.pane) {
        options.pane = marker.pane;
      }
      if (marker.hasOwnProperty('zIndexOffset') && marker.zIndexOffset) {
        options.zIndexOffset = marker.zIndexOffset;
      }
      return new L.Marker(latLng, options);
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

        case 'deepblue':
          return '#0067A3';

        case 'white':
          return '#FFFFFF';

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
      if (polygon.hasOwnProperty('fillOpacity')) {
        options.fillOpacity = polygon.fillOpacity;
      }
      if (polygon.hasOwnProperty('pane') && polygon.pane) {
        options.pane = polygon.pane;
      }
      if (polygon.hasOwnProperty('zIndexOffset') && polygon.zIndexOffset) {
        options.zIndexOffset = polygon.zIndexOffset;
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
      if (polyline.hasOwnProperty('pane') && polyline.pane) {
        options.pane = polyline.pane;
      }
      if (polyline.hasOwnProperty('zIndexOffset') && polyline.zIndexOffset) {
        options.zIndexOffset = polyline.zIndexOffset;
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
        iconAnchor: [13, 39],
        shadowUrl: drupalSettings.leaflet_icons['shadow'],
        shadowSize: [41, 41]
      });

      return icon;
    };

    drupalLeaflet.create_div_icon = function (marker) {

      switch (marker.markerClass) {
        case 'medmus-leaflet-marker-work':
          var icon = L.divIcon({
            className: marker.markerClass,
            html: '<i class=\'fa fa-music awesome\'>',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
          });

          break;

        case 'medmus-leaflet-marker-work-upside-down':
          var icon = L.divIcon({
            className: marker.markerClass,
            html: '<i class=\'fa fa-music awesome\'>',
            iconSize: [30, 30],
            iconAnchor: [15, 0],
          });

          break;

        default:
          var icon = L.divIcon({
            className: marker.markerClass,
            html: '<i class=\'fa awesome\'>',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
          });

          break;
      }

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

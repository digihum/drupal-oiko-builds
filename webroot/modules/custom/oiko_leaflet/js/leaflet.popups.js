(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  // Number of pixels a feature has to be within a mouse pointer to be
  // considered to be 'over' it.
  var tooltipFeatureRadius = 5;

  var featureCache = {};

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
    // Add the sidebar control if there's a sidebar control in the page markup.
    if (mapDefinition.sidebar && Drupal.oiko.hasOwnProperty('sidebar')) {
      drupalLeaflet.hasSidebar = true;

      $(window).bind('selected.map.searchitem selected.timeline.searchitem', function (e, id) {
        var id = parseInt(id, 10);
        Drupal.oiko.openSidebar(id);
      });

      $(window).bind('oikoSidebarOpening', function (e, id) {
        if (featureCache.hasOwnProperty(id)) {
          if (!map.getBounds().contains(featureCache[id])) {
            // Check to see if the map is visible.
            if ($(map.getContainer()).is(':visible')) {
              map.panInsideBounds(featureCache[id]);
            }
          }
        }
      });

      // Check to see if we need to open the sidebar immediately.
      $(document).once('oiko_leaflet__popups').each(function () {
        if (drupalSettings.hasOwnProperty('oiko_leaflet') && drupalSettings.oiko_leaflet.hasOwnProperty('popup') && drupalSettings.oiko_leaflet.popup) {
          // We might need to wait for everything we need to be loaded.
          $(window).bind('load', function () {
            Drupal.oiko.openSidebar(drupalSettings.oiko_leaflet.popup.id);
          });
        }
      });
    }
  });

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeaflet) {
    if (drupalLeaflet.hasSidebar) {
      // Remove the popup and add it back as a tooltip.
      if (typeof lFeature.unbindPopup !== 'undefined') {
        lFeature.unbindPopup();
      }
      if (feature.popup) {
        // If this is a point, then we want the tooltip to not move around.
        var sticky = feature.type !== 'point';
        var tooltipText = feature.popup;
        if (feature.location) {
          tooltipText = '<div class="leaflet-tooltip--location">' + feature.location + '</div><div class="leaflet-tooltip--popup">' + tooltipText + '</div>';
        }
        if (L.Browser.mobile) {
          tooltipText = tooltipText + '<div class="leaflet-tooltip--cta">Tap for more information</div>';
        }
        lFeature.bindTooltip(tooltipText, {direction: feature.popup_direction ? feature.popup_direction : 'bottom', opacity: 1, sticky: sticky, permanent: false, interactive: true, _oiko: {popup: feature.popup, location: feature.location ? feature.location : false}});
        var tooltip = lFeature.getTooltip();
        if (typeof feature.id !== 'undefined') {
          tooltip.on('click', function (e) {
            Drupal.oiko.openSidebar(feature.id);
          });
        }
        // This is an attempt to help when the tooltip appears on lines or
        // polygons or other objects that are not quite as defined as a 'point'.
        // We attempt to work out all the map layers that are 'near' the mouse
        // pointer and show the popup for all of them.
        // For lines we are really looking for lines that intersect for some
        // non-trivial amount.
        if (feature.type !== 'point') {
          lFeature.on('tooltipopen mousemove', function (e) {
            var latLng, target, containerPoint, layerPoint, tooltip;
            if (e.type === 'tooltipopen') {
              target = e.target;
              tooltip = e.tooltip;
              latLng = tooltip.getLatLng();
            }
            else {
              target = e.target;
              latLng = e.latlng;
              if (e.originalEvent) {
                containerPoint = drupalLeaflet.lMap.mouseEventToContainerPoint(e.originalEvent);
                layerPoint = drupalLeaflet.lMap.containerPointToLayerPoint(containerPoint);
                latLng = drupalLeaflet.lMap.layerPointToLatLng(layerPoint);
              }
            }
            // Attempt to locate layers at this point.
            var intersectingLayers = [];
            var ll = [latLng.lat, latLng.lng];
            var container_ll = drupalLeaflet.lMap.latLngToContainerPoint(ll);
            var latlngs;
            drupalLeaflet.lMap.eachLayer(function(layer) {
              // Handle lines between two points specially, so that we include
              // lines 'near' the point that was clicked.
              if ((layer instanceof L.Polyline) && (latlngs = layer.getLatLngs()) && (latlngs.length == 2)) {
                var p1 = drupalLeaflet.lMap.latLngToContainerPoint(latlngs[0]);
                var p2 = drupalLeaflet.lMap.latLngToContainerPoint(latlngs[1]);
                if (L.LineUtil.pointToSegmentDistance(container_ll, p1, p2) < tooltipFeatureRadius) {
                  intersectingLayers.push(layer);
                }
              }
              else if (typeof layer.pointToPolygonDistance !== 'undefined') {
                if (layer.pointToPolygonDistance(container_ll) < tooltipFeatureRadius) {
                  intersectingLayers.push(layer);
                }
              }
              else {
                // For everything else, if we can get the bounds of the shape,
                // pull that into the tooltip if appropriate.
                if (layer.getBounds && layer.getBounds().isValid() && (layer.getBounds().contains(ll))) {
                  intersectingLayers.push(layer);
                }
              }
            });

            // Build up new tooltip content to display.
            var tooltipContent = {'__none__': []};

            var thisTooltip;
            for (var i = 0; i < intersectingLayers.length; i++) {
              if ((thisTooltip = intersectingLayers[i].getTooltip()) && thisTooltip.options && thisTooltip.options._oiko) {
                if (thisTooltip.options._oiko.location) {
                  tooltipContent[thisTooltip.options._oiko.location] = tooltipContent[thisTooltip.options._oiko.location] || [];
                  tooltipContent[thisTooltip.options._oiko.location].push(thisTooltip.options._oiko.popup);
                }
                else {
                  tooltipContent['__none__'] = tooltipContent['__none__'] || [];
                  tooltipContent['__none__'].push(thisTooltip.options._oiko.popup);
                }
              }
            }

            var tooltipString = '';
            // Add the no location events to the top.
            if (tooltipContent['__none__'].length) {
              tooltipString += '<ul><li>' + tooltipContent['__none__'].join("</li><li>") + '</li></ul>';
            }
            // Now add in all other events with locations.
            for (var i in tooltipContent) {
              if (i !== '__none__') {
                tooltipString += '<div class="leaflet-tooltip--location">' + i + '</div><div class="leaflet-tooltip--popup"><ul><li>' + tooltipContent[i].join("</li><li>") + '</li></ul></div>';
              }
            }
            if (L.Browser.mobile) {
              tooltipString += '<div class="leaflet-tooltip--cta">Tap for more information</div>';
            }
            target.setTooltipContent(tooltipString);
          });
        }
      }

      // Store away the bounds of the feature.
      if (typeof lFeature.getBounds !== 'undefined') {
        featureCache[feature.id] = lFeature.getBounds();
      }
      else if (typeof lFeature.getLatLng !== 'undefined') {
        var center = lFeature.getLatLng();
        featureCache[feature.id] = leafletLatLngToBounds(center, 1000);
      }

      if (typeof feature.id !== 'undefined') {
        // Add a click event that opens our marker in the sidebar.
        if (L.Browser.touch) {
          lFeature.on('preclick', function (e) {
            // A second click should open the popup.
            if (lFeature.isTooltipOpen()) {
              Drupal.oiko.openSidebar(feature.id);
            }
          });

        }
        else {
          lFeature.on('click', function (e) {
            Drupal.oiko.openSidebar(feature.id);
          });
        }
      }
    }
  });

  var leafletLatLngToBounds = function(latlng, sizeInMeters) {

    if (typeof latlng.toBounds !== 'undefined') {
      return latlng.toBounds(sizeInMeters);
    }
    else {
      var latAccuracy = 180 * sizeInMeters / 40075017,
        lngAccuracy = latAccuracy / Math.cos((Math.PI / 180) * latlng.lat);

      return L.latLngBounds(
        [latlng.lat - latAccuracy, latlng.lng - lngAccuracy],
        [latlng.lat + latAccuracy, latlng.lng + lngAccuracy]);
    }
  };

  Drupal.behaviors.oiko_iframe_container = {
    attach: function(context) {
      $('.discussion-iframe', context).once('oiko_iframe_container').each(function() {
        var $this = $(this);
        var isOldIE = (navigator.userAgent.indexOf("MSIE") !== -1); // Detect IE10 and below
        $this.iFrameResize({
          log: false,
          heightCalculationMethod: isOldIE ? 'max' : 'lowestElement',
          messageCallback: Drupal.oiko.iframeMessageCallback($this)
        });
      });
    }
  };


  Drupal.oiko.iframeMessageCallback = function (container) {
    var $sidebar = $(container).closest('.sidebar-content');
    return function(e) {
      var iframe = e.iframe;
      var message = e.message;
      if (typeof message.type !== 'undefined') {
        switch (message.type) {
          case 'scrolltop':
            // Scroll the container to the right place.
            $sidebar.scrollTop(0);
            break;

          case 'cidoc_link':
            if (typeof message.id !== 'undefined') {
              // Fire an event to open the sidebar.
              if (Drupal.oiko.openSidebar) {
                Drupal.oiko.openSidebar(message.id);
              }
            }
            break;

          case 'messages':
            if (typeof message.messages !== 'undefined') {
              // Remove previous messages
              $('#highlighted-child').remove();
              var $highlighted = $('<div>').addClass('reveal js-highlighted-reveal').attr('data-reveal', 'true').attr('id', 'highlighted-child');
              $('body').append($highlighted);
              $highlighted.append(message.messages);
              Drupal.attachBehaviors($highlighted.parent().get(0));
            }
            break;
        }
      }
    };
  }


  var PointInPolygon = {
    pointToPolygonDistance: function(point) {
      // Handle each polygon in turn, this might actually be detecting holes, not sure.
      var latlngs = this.getLatLngs();
      if (latlngs[0][0] instanceof Array) {
        // This is an array of polygons.
        var mindist = Infinity;
        for (var i = 0; i < latlngs.length; i++) {
          var poly = latlngs[i];
          var dist = this._pointToSinglePolygonDistance(point, poly);
          if (dist <= mindist) {
            mindist = dist;
          }
        }
        return mindist;
      }
      else {
        return this._pointToSinglePolygonDistance(point, latlngs);
      }
    },
    _pointToSinglePolygonDistance: function(point, latlngs) {
      // Determine if the point lines _inside_ the polygon.
      var inside = this._pointInPolygon(point, latlngs[0]);
      if (inside && (latlngs.length === 1)) {
        return 0;
      }
      else if (inside && (latlngs.length > 1)) {
        // Need to check that we are not in any 'holes'.
        var inHole = false;
        for (var i = 1; i < latlngs.length; i++) {
          if (this._pointInPolygon(point, latlngs[i])) {
            inHole = true;
            break;
          }
        }
        // We were in the outer polygon, not in any holes.
        if (!inHole) {
          return 0;
        }
      }
      else {
        var mindist = Infinity;
        for (var i = 0; i < latlngs.length; i++) {
          var poly = latlngs[i];
          for (var j = 0; j < poly.length - 1; j++) {
            var pointA = this._map.latLngToContainerPoint(poly[j]),
              pointB = this._map.latLngToContainerPoint(poly[j + 1]);
            var dist = L.LineUtil.pointToSegmentDistance(point, pointA, pointB);
            if (dist <= mindist) {
              mindist = dist;
            }
          }
        }
        return mindist;
      }
    },
    _pointInPolygon: function(point, poly) {
      var thisPoint, thatPoint, points = [];
      thisPoint = [point.x, point.y];
      for (var j = 0; j < poly.length - 1; j++) {
        thatPoint = this._map.latLngToContainerPoint(poly[j]);
        points[points.length] = [thatPoint.x, thatPoint.y];
      }
      return this._pointInPolygonNested(thisPoint, points);
    },
    // https://github.com/substack/point-in-polygon/blob/master/index.js
    _pointInPolygonNested: function(point, vs, start, end) {
      var x = point[0], y = point[1];
      var inside = false;
      if (start === undefined) start = 0;
      if (end === undefined) end = vs.length;
      var len = end - start;
      for (var i = 0, j = len - 1; i < len; j = i++) {
        var xi = vs[i+start][0], yi = vs[i+start][1];
        var xj = vs[j+start][0], yj = vs[j+start][1];
        var intersect = ((yi > y) !== (yj > y))
          && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
      }
      return inside;
    }
  }

  L.Polygon.include(PointInPolygon);

})(jQuery);

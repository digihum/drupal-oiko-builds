(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  // Number of pixels a feature has to be within a mouse pointer to be
  // considered to be 'over' it.
  var tooltipFeatureRadius = 5;

  var featureCache = {};

  $(document).on('leaflet.map', function(e, mapDefinition, map, mapid) {
    var drupalLeaflet = Drupal.Leaflet[mapid];
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

  var changePopupStringIntoLink = function(string, options) {
    if (typeof options._oiko !== 'undefined' && typeof options._oiko.id !== 'undefined') {
      return '<a href="#" data-cidoc-id="' + options._oiko.id + '">' + string + '</a>';
    }
    else {
      return string;
    }
  }

  $(document).on('leaflet.feature', function(e, lFeature, feature, drupalLeafletInstance) {
    var drupalLeaflet = Drupal.Leaflet[drupalLeafletInstance.mapid];
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
        var oikoOptions = {
          popup: feature.popup,
          location: feature.location ? feature.location : false
        };
        if (typeof feature.id !== 'undefined') {
          oikoOptions.id = feature.id;
        }
        lFeature.bindMedmusTooltip(tooltipText, {
          direction: feature.popup_direction ? feature.popup_direction : 'bottom',
          opacity: 1,
          sticky: sticky,
          permanent: false,
          interactive: true,
          _oiko: oikoOptions
        });
        var tooltip = lFeature.getMedmusTooltip();
        if (typeof feature.id !== 'undefined') {
          tooltip.on('click', function (e) {
            Drupal.oiko.openSidebar(feature.id);
          });
        }

        // If this feature is marked as such, find all layers 'nearby' that are
        // also aggregated.
        if (typeof feature.popupAggregated !== 'undefined') {
          lFeature.options.popupAggregated = feature.popupAggregated;
          lFeature.on('tooltipupdatecontent', function (e) {
            var latLng, target, tooltip;
            target = e.target;
            tooltip = e.medmusTooltip;
            latLng = tooltip.getLatLng();

            // Attempt to locate layers at this point.
            var intersectingLayers = [];
            var ll = [latLng.lat, latLng.lng];
            var container_ll = drupalLeafletInstance.lMap.latLngToContainerPoint(ll);

            drupalLeafletInstance.lMap.eachLayer(function(layer) {
              // Pick out other popupAggregated items at the event point.
              if (typeof layer.options.popupAggregated !== 'undefined' && layer instanceof L.Marker) {
                if (container_ll.distanceTo(drupalLeafletInstance.lMap.latLngToContainerPoint(layer.getLatLng())) < tooltipFeatureRadius) {
                  intersectingLayers.push(layer);
                }
              }
            });

            // Build up new tooltip content to display.
            var tooltipContent = {'__none__': []};

            var thisTooltip;
            for (i = 0; i < intersectingLayers.length; i++) {
              if ((thisTooltip = intersectingLayers[i].getMedmusTooltip()) && thisTooltip.options && thisTooltip.options._oiko) {
                if (thisTooltip.options._oiko.location) {
                  tooltipContent[thisTooltip.options._oiko.location] = tooltipContent[thisTooltip.options._oiko.location] || [];
                  tooltipContent[thisTooltip.options._oiko.location].push(changePopupStringIntoLink(thisTooltip.options._oiko.popup, thisTooltip.options));
                }
                else {
                  tooltipContent['__none__'] = tooltipContent['__none__'] || [];
                  tooltipContent['__none__'].push(changePopupStringIntoLink(thisTooltip.options._oiko.popup, thisTooltip.options));
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
            target.setMedmusTooltipContent(tooltipString);
          });
        }
        // This is an attempt to help when the tooltip appears on lines or
        // polygons or other objects that are not quite as defined as a 'point'.
        // We attempt to work out all the map layers that are 'near' the mouse
        // pointer and show the popup for all of them.
        // For lines we are really looking for lines that intersect for some
        // non-trivial amount.
        else if (feature.type !== 'point') {
          lFeature.on('tooltipupdatecontent', function (e) {
            var latLng, target, containerPoint, layerPoint, tooltip;
            target = e.target;
            tooltip = e.medmusTooltip;
            latLng = tooltip.getLatLng();

            // Attempt to locate layers at this point.
            var intersectingLayers = [];
            var ll = [latLng.lat, latLng.lng];
            var container_ll = drupalLeafletInstance.lMap.latLngToContainerPoint(ll);
            var latlngs;
            drupalLeafletInstance.lMap.eachLayer(function(layer) {
              // Handle lines between two points specially, so that we include
              // lines 'near' the point that was clicked.
              if ((layer instanceof L.Polyline) && (latlngs = layer.getLatLngs()) && (latlngs.length === 2)) {
                var p1 = drupalLeafletInstance.lMap.latLngToContainerPoint(latlngs[0]);
                var p2 = drupalLeafletInstance.lMap.latLngToContainerPoint(latlngs[1]);
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
            for (i = 0; i < intersectingLayers.length; i++) {
              if ((thisTooltip = intersectingLayers[i].getMedmusTooltip()) && thisTooltip.options && thisTooltip.options._oiko) {
                if (thisTooltip.options._oiko.location) {
                  tooltipContent[thisTooltip.options._oiko.location] = tooltipContent[thisTooltip.options._oiko.location] || [];
                  tooltipContent[thisTooltip.options._oiko.location].push(changePopupStringIntoLink(thisTooltip.options._oiko.popup, thisTooltip.options));
                }
                else {
                  tooltipContent['__none__'] = tooltipContent['__none__'] || [];
                  tooltipContent['__none__'].push(changePopupStringIntoLink(thisTooltip.options._oiko.popup, thisTooltip.options));
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
            target.setMedmusTooltipContent(tooltipString);
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
            if (lFeature.isMedmusTooltipOpen()) {
              if ($(e.originalEvent.target).is('a[data-cidoc-id]')) {
                Drupal.oiko.openSidebar($(e.originalEvent.target).data('cidoc-id'));
              }
              else {
                Drupal.oiko.openSidebar(feature.id);
              }
            }
          });

        }
        else {
          lFeature.on('click', function (e) {
            if ($(e.originalEvent.target).is('a[data-cidoc-id]')) {
              Drupal.oiko.openSidebar($(e.originalEvent.target).data('cidoc-id'));
            }
            else {
              Drupal.oiko.openSidebar(feature.id);
            }
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


/*
 * @class Tooltip
 * @inherits DivOverlay
 * @aka L.Tooltip
 * Used to display small texts on top of map layers.
 *
 * @example
 *
 * ```js
 * marker.bindTooltip("my tooltip text").openTooltip();
 * ```
 * Note about tooltip offset. Leaflet takes two options in consideration
 * for computing tooltip offseting:
 * - the `offset` Tooltip option: it defaults to [0, 0], and it's specific to one tooltip.
 *   Add a positive x offset to move the tooltip to the right, and a positive y offset to
 *   move it to the bottom. Negatives will move to the left and top.
 * - the `tooltipAnchor` Icon option: this will only be considered for Marker. You
 *   should adapt this value if you use a custom icon.
 */


// @namespace MedmusTooltip
L.MedmusTooltip = L.DivOverlay.extend({

  // @section
  // @aka Tooltip options
  options: {
    // @option pane: String = 'tooltipPane'
    // `Map pane` where the tooltip will be added.
    pane: 'tooltipPane',

    // @option offset: Point = Point(0, 0)
    // Optional offset of the tooltip position.
    offset: [0, 0],

    // @option direction: String = 'auto'
    // Direction where to open the tooltip. Possible values are: `right`, `left`,
    // `top`, `bottom`, `center`, `auto`.
    // `auto` will dynamicaly switch between `right` and `left` according to the tooltip
    // position on the map.
    direction: 'auto',

    // @option permanent: Boolean = false
    // Whether to open the tooltip permanently or only on mouseover.
    permanent: false,

    // @option sticky: Boolean = false
    // If true, the tooltip will follow the mouse instead of being fixed at the feature center.
    sticky: false,

    // @option interactive: Boolean = false
    // If true, the tooltip will listen to the feature events.
    interactive: false,

    // @option opacity: Number = 0.9
    // Tooltip container opacity.
    opacity: 0.9
  },

  onAdd: function (map) {
    L.DivOverlay.prototype.onAdd.call(this, map);
    this.setOpacity(this.options.opacity);

    // @namespace Map
    // @section Tooltip events
    // @event tooltipopen: TooltipEvent
    // Fired when a tooltip is opened in the map.
    map.fire('tooltipopen', {medmusTooltip: this});

    if (this._source) {
      // @namespace Layer
      // @section Tooltip events
      // @event tooltipopen: TooltipEvent
      // Fired when a tooltip bound to this layer is opened.
      this._source.fire('tooltipopen', {medmusTooltip: this}, true);
    }
  },

  onUpdateTooltipContent(map) {
    // @namespace Map
    // @section Tooltip events
    // @event tooltipopen: TooltipEvent
    // Fired when a tooltip is opened in the map.
    map.fire('tooltipupdatecontent', {medmusTooltip: this});

    if (this._source) {
      // @namespace Layer
      // @section Tooltip events
      // @event tooltipopen: TooltipEvent
      // Fired when a tooltip bound to this layer is opened.
      this._source.fire('tooltipupdatecontent', {medmusTooltip: this}, true);
    }
  },

  onMove: function (map) {

    // @namespace Map
    // @section Tooltip events
    // @event tooltipopen: TooltipEvent
    // Fired when a tooltip is opened in the map.
    map.fire('tooltipmove', {medmusTooltip: this});

    if (this._source) {
      // @namespace Layer
      // @section Tooltip events
      // @event tooltipopen: TooltipEvent
      // Fired when a tooltip bound to this layer is opened.
      this._source.fire('tooltipmove', {medmusTooltip: this}, true);
    }
  },

  onRemove: function (map) {
    L.DivOverlay.prototype.onRemove.call(this, map);

    // @namespace Map
    // @section Tooltip events
    // @event tooltipclose: TooltipEvent
    // Fired when a tooltip in the map is closed.
    map.fire('tooltipclose', {tooltip: this});

    if (this._source) {
      // @namespace Layer
      // @section Tooltip events
      // @event tooltipclose: TooltipEvent
      // Fired when a tooltip bound to this layer is closed.
      this._source.fire('tooltipclose', {tooltip: this}, true);
    }
  },

  getEvents: function () {
    var events = L.DivOverlay.prototype.getEvents.call(this);

    if (L.Browser.touch && !this.options.permanent) {
      events.preclick = this._close;
    }

    return events;
  },

  _close: function () {
    if (this._map) {
      this._map.closeMedmusTooltip(this);
    }
  },

  _initLayout: function () {
    var prefix = 'leaflet-tooltip',
      className = prefix + ' ' + (this.options.className || '') + ' leaflet-zoom-' + (this._zoomAnimated ? 'animated' : 'hide');

    this._contentNode = this._container = L.DomUtil.create('div', className);
  },

  _updateLayout: function () {},

  _adjustPan: function () {},

  _setPosition: function (pos) {
    var map = this._map,
      container = this._container,
      centerPoint = map.latLngToContainerPoint(map.getCenter()),
      tooltipPoint = map.layerPointToContainerPoint(pos),
      direction = this.options.direction,
      tooltipWidth = container.offsetWidth,
      tooltipHeight = container.offsetHeight,
      offset = L.point(this.options.offset),
      anchor = this._getAnchor();

    if (direction === 'top') {
      pos = pos.add(L.point(-tooltipWidth / 2 + offset.x, -tooltipHeight + offset.y + anchor.y, true));
    } else if (direction === 'bottom') {
      pos = pos.subtract(L.point(tooltipWidth / 2 - offset.x, -offset.y, true));
    } else if (direction === 'center') {
      pos = pos.subtract(L.point(tooltipWidth / 2 + offset.x, tooltipHeight / 2 - anchor.y + offset.y, true));
    } else if (direction === 'right' || direction === 'auto' && tooltipPoint.x < centerPoint.x) {
      direction = 'right';
      pos = pos.add(L.point(offset.x + anchor.x, anchor.y - tooltipHeight / 2 + offset.y, true));
    } else {
      direction = 'left';
      pos = pos.subtract(L.point(tooltipWidth + anchor.x - offset.x, tooltipHeight / 2 - anchor.y - offset.y, true));
    }

    L.DomUtil.removeClass(container, 'leaflet-tooltip-right');
    L.DomUtil.removeClass(container, 'leaflet-tooltip-left');
    L.DomUtil.removeClass(container, 'leaflet-tooltip-top');
    L.DomUtil.removeClass(container, 'leaflet-tooltip-bottom');
    L.DomUtil.addClass(container, 'leaflet-tooltip-' + direction);
    L.DomUtil.setPosition(container, pos);
    this._computeLatLngBounds(pos);
  },

  _computeLatLngBounds: function(pos) {
    var map = this._map,
      container = this._container,
      tooltipLatLng = map.layerPointToLatLng(pos),
      tooltipWidth = container.offsetWidth,
      tooltipHeight = container.offsetHeight,
      otherCorner = map.layerPointToLatLng(pos.add(L.point(tooltipWidth, tooltipHeight)));
    this._latLngBounds = L.latLngBounds(tooltipLatLng, otherCorner).pad(0.1);
  },

  _updatePosition: function () {
    var pos = this._map.latLngToLayerPoint(this._latlng);
    this._setPosition(pos);
    this.onMove(this._map);
  },

  setOpacity: function (opacity) {
    this.options.opacity = opacity;

    if (this._container) {
      L.DomUtil.setOpacity(this._container, opacity);
    }
  },

  _animateZoom: function (e) {
    var pos = this._map._latLngToNewLayerPoint(this._latlng, e.zoom, e.center);
    this._setPosition(pos);
  },

  _getAnchor: function () {
    // Where should we anchor the tooltip on the source layer?
    return L.point(this._source && this._source._getTooltipAnchor && !this.options.sticky ? this._source._getTooltipAnchor() : [0, 0]);
  }

});

// @namespace Tooltip
// @factory L.tooltip(options?: Tooltip options, source?: Layer)
// Instantiates a Tooltip object given an optional `options` object that describes its appearance and location and an optional `source` object that is used to tag the tooltip with a reference to the Layer to which it refers.
L.medmusTooltip = function (options, source) {
  return new L.MedmusTooltip(options, source);
};

// @namespace Map
// @section Methods for Layers and Controls
L.Map.include({

  // @method openTooltip(tooltip: Tooltip): this
  // Opens the specified tooltip.
  // @alternative
  // @method openTooltip(content: String|HTMLElement, latlng: LatLng, options?: Tooltip options): this
  // Creates a tooltip with the specified content and options and open it.
  openMedmusTooltip: function (tooltip, latlng, options) {
    if (!(tooltip instanceof L.MedmusTooltip)) {
      tooltip = new L.MedmusTooltip(options).setContent(tooltip);
    }

    if (latlng) {
      tooltip.setLatLng(latlng);
    }

    if (this.hasLayer(tooltip)) {
      return this;
    }

    return this.addLayer(tooltip);
  },

  // @method closeTooltip(tooltip?: Tooltip): this
  // Closes the tooltip given as parameter.
  closeMedmusTooltip: function (tooltip) {
    if (tooltip) {
      this.removeLayer(tooltip);
    }
    return this;
  }

});

/*
 * @namespace Layer
 * @section Tooltip methods example
 *
 * All layers share a set of methods convenient for binding tooltips to it.
 *
 * ```js
 * var layer = L.Polygon(latlngs).bindTooltip('Hi There!').addTo(map);
 * layer.openTooltip();
 * layer.closeTooltip();
 * ```
 */

// @section Tooltip methods
L.Layer.include({

  // @method bindTooltip(content: String|HTMLElement|Function|Tooltip, options?: Tooltip options): this
  // Binds a tooltip to the layer with the passed `content` and sets up the
  // neccessary event listeners. If a `Function` is passed it will receive
  // the layer as the first argument and should return a `String` or `HTMLElement`.
  bindMedmusTooltip: function (content, options) {

    if (content instanceof L.MedmusTooltip) {
      L.setOptions(content, options);
      this._Medmustooltip = content;
      content._source = this;
    } else {
      if (!this._Medmustooltip || options) {
        this._Medmustooltip = L.medmusTooltip(options, this);
      }
      this._Medmustooltip.setContent(content);

    }

    this._initMedmusTooltipInteractions();

    if (this._Medmustooltip.options.permanent && this._map && this._map.hasLayer(this)) {
      this.openMedmusTooltip();
    }

    return this;
  },

  // @method unbindTooltip(): this
  // Removes the tooltip previously bound with `bindTooltip`.
  unbindMedmusTooltip: function () {
    if (this._Medmustooltip) {
      this._initMedmusTooltipInteractions(true);
      this.closeMedmusTooltip();
      this._Medmustooltip = null;
    }
    return this;
  },

  _initMedmusTooltipInteractions: function (remove) {
    if (!remove && this._MedmustooltipHandlersAdded) { return; }
    var onOff = remove ? 'off' : 'on',
      events = {
        remove: this.closeMedmusTooltip,
        move: this._moveMedmusTooltip
      };
    if (!this._Medmustooltip.options.permanent) {
      events.mouseover = this._openMedmusTooltip;
      events.mouseout = this._mouseOutMedmusTooltip;
      events.mousemove = this._moveMedmusTooltip;
      if (L.Browser.touch) {
        events.click = this._openMedmusTooltip;
      }
    } else {
      events.add = this._openMedmusTooltip;
    }
    this[onOff](events);
    this._MedmustooltipHandlersAdded = !remove;
  },

  _mouseOverMedmusTooltip: function(e) {
    clearTimeout(this._Medmustooltip._openTimer);
    var that = this;
    this._Medmustooltip._openTimer = setTimeout(function() {
      that._openMedmusTooltip();
    }, 25);
  },

  _mouseOutMedmusTooltip: function(e) {
    clearTimeout(this._Medmustooltip._closeTimer);
    var that = this;
    this._Medmustooltip._closeTimer = setTimeout(function() {
      that.closeMedmusTooltip();
    }, 25);
  },

  // @method openTooltip(latlng?: LatLng): this
  // Opens the bound tooltip at the specificed `latlng` or at the default tooltip anchor if no `latlng` is passed.
  openMedmusTooltip: function (layer, latlng) {
    if (!(layer instanceof L.Layer)) {
      latlng = layer;
      layer = this;
    }

    if (layer instanceof L.FeatureGroup) {
      for (var id in this._layers) {
        layer = this._layers[id];
        break;
      }
    }

    if (!latlng) {
      latlng = layer.getCenter ? layer.getCenter() : layer.getLatLng();
    }

    if (this._Medmustooltip && this._map) {

      // Clear any close timer we have going.
      clearTimeout(this._Medmustooltip._closeTimer);

      // set tooltip source to this layer
      this._Medmustooltip._source = layer;

      // update the tooltip (content, layout, ect...)
      this._Medmustooltip.update();

      // open the tooltip on the map
      this._map.openMedmusTooltip(this._Medmustooltip, latlng);
      this._Medmustooltip.onUpdateTooltipContent(this._map);

      // Tooltip container may not be defined if not permanent and never
      // opened.
      if (this._Medmustooltip.options.interactive && this._Medmustooltip._container) {
        L.DomUtil.addClass(this._Medmustooltip._container, 'leaflet-clickable');
        this.addInteractiveTarget(this._Medmustooltip._container);
      }
    }

    return this;
  },

  // @method closeTooltip(): this
  // Closes the tooltip bound to this layer if it is open.
  closeMedmusTooltip: function () {
    if (this._Medmustooltip) {
      this._Medmustooltip._close();
      if (this._Medmustooltip.options.interactive && this._Medmustooltip._container) {
        L.DomUtil.removeClass(this._Medmustooltip._container, 'leaflet-clickable');
        this.removeInteractiveTarget(this._Medmustooltip._container);
      }
    }
    return this;
  },

  // @method toggleTooltip(): this
  // Opens or closes the tooltip bound to this layer depending on its current state.
  toggleMedmusTooltip: function (target) {
    if (this._Medmustooltip) {
      if (this._Medmustooltip._map) {
        this.closeMedmusTooltip();
      } else {
        this.openMedmusTooltip(target);
      }
    }
    return this;
  },

  // @method isTooltipOpen(): boolean
  // Returns `true` if the tooltip bound to this layer is currently open.
  isMedmusTooltipOpen: function () {
    if (this._Medmustooltip) {
      return this._Medmustooltip.isOpen();
    }
    else {
      return false;
    }
  },

  // @method setTooltipContent(content: String|HTMLElement|Tooltip): this
  // Sets the content of the tooltip bound to this layer.
  setMedmusTooltipContent: function (content) {
    if (this._Medmustooltip) {
      this._Medmustooltip.setContent(content);
    }
    return this;
  },

  // @method getTooltip(): Tooltip
  // Returns the tooltip bound to this layer.
  getMedmusTooltip: function () {
    return this._Medmustooltip;
  },

  _openMedmusTooltip: function (e) {
    var layer = e.layer || e.target;

    if (!this._Medmustooltip || !this._map) {
      return;
    }
    if (!this.isMedmusTooltipOpen()) {
      this.openMedmusTooltip(layer, this._Medmustooltip.options.sticky ? e.latlng : undefined);
    }
  },

  _moveMedmusTooltip: function (e) {
    // Clear any close timer we have going.
    clearTimeout(this._Medmustooltip._closeTimer);
    // Try not to move the tooltip if the mouse is _inside_ the tooltip
    var latlng = e.latlng, containerPoint, layerPoint, nLatLng;
    if (this._Medmustooltip.options.sticky && e.originalEvent) {
      containerPoint = this._map.mouseEventToContainerPoint(e.originalEvent);
      layerPoint = this._map.containerPointToLayerPoint(containerPoint);
      nLatLng = this._map.layerPointToLatLng(layerPoint);
      // Only move the tooltip if we are not 'near'/within the tooltip itself.
      if (!this._Medmustooltip._latLngBounds || !this._Medmustooltip._latLngBounds.contains(nLatLng)) {
        this._Medmustooltip.setLatLng(nLatLng);
        this._Medmustooltip.onUpdateTooltipContent(this._map);
      }
    }
  }
});

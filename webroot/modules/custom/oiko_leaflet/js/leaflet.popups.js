(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

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
        lFeature.bindTooltip(tooltipText, {direction: 'bottom', opacity: 1, sticky: sticky, permanent: false, interactive: true});
        var tooltip = lFeature.getTooltip();
        tooltip.on('click', function (e) {
          Drupal.oiko.openSidebar(feature.id);
        });
      }

      // Store away the bounds of the feature.
      if (typeof lFeature.getBounds !== 'undefined') {
        featureCache[feature.id] = lFeature.getBounds();
      }
      else if (typeof lFeature.getLatLng !== 'undefined') {
        var center = lFeature.getLatLng();
        featureCache[feature.id] = leafletLatLngToBounds(center, 1000);
      }

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

})(jQuery);

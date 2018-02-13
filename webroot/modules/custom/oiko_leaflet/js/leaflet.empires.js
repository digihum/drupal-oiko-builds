(function ($) {
  'use strict';

  Drupal.oiko.addAppModule('marker-data');
  Drupal.oiko.addAppModule('empire-data');

  var loadedFeatures = [];
  var dataLoadCallback = function (data) {
    loadedFeatures = loadedFeatures.concat(data.features);
    // Check for a more property, and reload if we need to.
    if (typeof data.more !== 'undefined') {
      $.get(data.more).done(dataLoadCallback);
    }
    else {
      // Load all of those features.
      drupalLeaflet.add_features(loadedFeatures);
      Drupal.oiko.appModuleDoneLoading('marker-data');
    }
  };

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    // @TODO: Move this code, it does NOT belong here!
    if (mapDefinition.hasOwnProperty('data-url') && mapDefinition['data-url']) {
      $.get(mapDefinition['data-url']).done(dataLoadCallback);
    }
    else {
      // There's nothing to load, so we're done here.
      Drupal.oiko.appModuleDoneLoading('marker-data');
    }

    if (mapDefinition.hasOwnProperty('empires') && mapDefinition.empires) {
      // We need to enable the empires functionality.

      drupalLeaflet.empires = drupalLeaflet.empires || {};

      // Temporal stuff, we want a layer group to keep track of Leaflet features
      // with temporal data.
      drupalLeaflet.empires.empiresLayerGroup = L.layerGroup();
      drupalLeaflet.empires.empiresLayerGroup.addTo(map);
      drupalLeaflet.empires.empiresLayerHelper = L.temporalLayerHelper(drupalLeaflet.empires.empiresLayerGroup, {visibleInTimelineBrowser: false});
      drupalLeaflet.empires.empiresLayerHelper.addTo(map);

      // Go get the empire data.
      var get = $.get('/oiko_leaflet/empires/list.json');
      get.done(function(data) {
        data.forEach(function (empire) {
          var lFeature = drupalLeaflet.create_feature(empire);
          lFeature.temporal = {
            start: parseInt(empire.temporal.minmin, 10),
            end: parseInt(empire.temporal.maxmax, 10)
          };
          var stripe_options = {
            weight: 4,
            spaceWeight: 4
          };

          if (empire.hasOwnProperty('empire_data')) {
            if (empire.empire_data.hasOwnProperty('color')) {
              stripe_options.color = empire.empire_data.color;
              stripe_options.spaceColor = empire.empire_data.color;
            }
            if (empire.empire_data.hasOwnProperty('opacity')) {
              stripe_options.opacity = empire.empire_data.opacity;
              stripe_options.spaceOpacity = empire.empire_data.opacity * 0.25;
            }
            if (empire.empire_data.hasOwnProperty('label')) {
              stripe_options.angle = hashCode(empire.empire_data.label) % 360;
            }
          }
          var stripes = new L.StripePattern(stripe_options);
          stripes.addTo(map);
          var styleOptions = {
            stroke: false,
            fillPattern: stripes,
            fillOpacity: 1.0
          };
          lFeature.setStyle(styleOptions);
          lFeature.bindTooltip('<div class="leaflet-tooltip--location">' + empire.label + '</div>', {direction: 'bottom', sticky: true, opacity: 1});
          drupalLeaflet.empires.empiresLayerHelper.addLayer(lFeature);
        });
        Drupal.oiko.appModuleDoneLoading('empire-data');
      });
    }
    else {
      // Nothing to do here, so just complete.
      Drupal.oiko.appModuleDoneLoading('empire-data');
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
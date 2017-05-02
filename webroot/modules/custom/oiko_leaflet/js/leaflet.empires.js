(function ($) {
  'use strict';

  Drupal.oiko.addAppModule('marker-data');
  Drupal.oiko.addAppModule('empire-data');

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    // @TODO: Move this code, it does NOT belong here!
    if (mapDefinition.hasOwnProperty('data-url') && mapDefinition['data-url']) {
      var get = $.get(mapDefinition['data-url']);
      get.done(function (data) {
        drupalLeaflet.add_features(data);
        Drupal.oiko.appModuleDoneLoading('marker-data');
      });
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
          var styleOptions = {
            stroke: false
          };
          if (empire.hasOwnProperty('empire_data')) {
            if (empire.empire_data.hasOwnProperty('color')) {
              styleOptions.color = empire.empire_data.color;
            }
            if (empire.empire_data.hasOwnProperty('opacity')) {
              styleOptions.fillOpacity = empire.empire_data.opacity;
            }
          }
          lFeature.setStyle(styleOptions);
          lFeature.bindTooltip(empire.label, {direction: 'bottom', sticky: true, opacity: 1});
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


})(jQuery);
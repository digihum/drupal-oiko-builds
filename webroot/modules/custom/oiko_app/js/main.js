import { setMapState,  setTimeBrowserState, addAppModule, appModuleDoneLoading } from './actions';
import { createOikoApp } from './store';
import $ from './jquery';

// Spin up a new instance of our OikoApp.
const app = createOikoApp();

const store = app.getStore();

Drupal.oiko = Drupal.oiko || {};

Drupal.oiko.addAppModule = (moduleName) => {
  return app.getStore().dispatch(addAppModule(moduleName));
};

Drupal.oiko.appModuleDoneLoading = (moduleName) => {
  return app.getStore().dispatch(appModuleDoneLoading(moduleName));
};

Drupal.oiko.getAppState = () => {
  return app.getStore().getState();
};

// $(() => {
//   $('.js-oiko-app-loader').once('js-oiko-app-loader').each(() => {
//     const $wrapper = $(this);
//     app.addTo($wrapper);
//     $wrapper.data('oikoApp', app);
//   });
// });


// @TODO: Move all of this elsewhere.
//
// Probably a better way to write this.
$(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
  if (mapDefinition.hasOwnProperty('pagestate') && mapDefinition.pagestate) {

    window.drupalLeaflet  =drupalLeaflet;

    const handleMapMove = (e) => {
      let center = map.getCenter();
      let changedNeeded = false;
      const state = store.getState();
      if (e.type === 'zoomend' && (map.getZoom() != state.mapState.level))  {
        changedNeeded = true;
      }
      if (e.type === 'moveend' && (state.mapState.lat != center.lat.toFixed(2) || state.mapState.lng != center.lng.toFixed(2))) {
        changedNeeded = true;
      }

      if (changedNeeded) {
        store.dispatch(setMapState(map.getZoom(), center.lat, center.lng));
      }
    };

    const handleMapStoreStateChange = () => {
      let state = store.getState();
      let changedNeeded = false;

      // If the zoom level of the map is different, change it.
      if (map.getZoom() != state.mapState.level) {
        changedNeeded = true;
      }

      let center = map.getCenter();
      if (!Number.isNaN(state.mapState.lat) && !Number.isNaN(state.mapState.lng)) {
        const mLat = Number.parseFloat(center.lat).toFixed(2);
        const mLng = Number.parseFloat(center.lng).toFixed(2);
        if ((mLat != state.mapState.lat) || (mLng != state.mapState.lng)) {
          changedNeeded = true;
        }
      }

      if (changedNeeded) {
        map.setView({lat: state.mapState.lat, lng: state.mapState.lng}, state.mapState.level, {animate: true});
      }
    };

    $(window).on('oiko.loaded', () => {
      handleMapStoreStateChange();
      // Set up a two way sync of the map zoom and position when needed.
      map.on('zoomend moveend', handleMapMove);
      // Sync from the state store into the map.
      store.subscribe(handleMapStoreStateChange);
    });

    const handleMapTemporalShift = (e) => {
      let needsUpdate = false;
      const state = store.getState();
      const currentTime = drupalLeaflet.timelineControl.getTime();
      const currentVisibleWindow = drupalLeaflet.timelineControl.getWindow();

      // Check to see if the current time was just moved.
      if (e.type == 'temporal.shifted' && currentTime != state.timeBrowserState.current) {
        needsUpdate = true;
      }

      // Check to see if the range window of the timeline has changed.
      if (e.type == 'temporal.visibleWindowChanged' && (state.timeBrowserState.start != currentVisibleWindow.start || state.timeBrowserState.end != currentVisibleWindow.end)) {
        needsUpdate = true;
      }

      if (needsUpdate) {
        store.dispatch(setTimeBrowserState(currentTime, currentVisibleWindow.start, currentVisibleWindow.end));
      }
    };

    const handleMapTemporalStoreStateChange = () => {
      const state = store.getState();
      const currentTime = drupalLeaflet.timelineControl.getTime();
      const currentWindow = drupalLeaflet.timelineControl.getWindow();

      const needsUpdate = (state.timeBrowserState.current != currentTime) || (state.timeBrowserState.start != currentWindow.start || state.timeBrowserState.end != currentWindow.end);

      if (needsUpdate) {
        drupalLeaflet.timelineControl.setTimeAndWindow(state.timeBrowserState.current, state.timeBrowserState.start, state.timeBrowserState.end);
      }
    };
    if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
      $(window).on('oiko.loaded', () => {
        handleMapTemporalStoreStateChange();
        map.on('temporal.shifted temporal.visibleWindowChanged', handleMapTemporalShift);
        store.subscribe(handleMapTemporalStoreStateChange);
      });

    }
  }
});


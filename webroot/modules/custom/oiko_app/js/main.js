import { setMapState,  setTimeBrowserState, addAppModule, appModuleDoneLoading } from './actions';
import { createOikoApp } from './store';
import $ from './jquery';

// Spin up a new instance of our OikoApp.
const app = createOikoApp();

// const store = app.getStore();

Drupal.oiko = Drupal.oiko || {};

Drupal.oiko.addAppModule = (moduleName) => {
  // return store.dispatch(addAppModule(moduleName));
};

Drupal.oiko.appModuleDoneLoading = (moduleName) => {
  // return store.dispatch(appModuleDoneLoading(moduleName));
};

Drupal.oiko.getAppState = () => {
  // return store.getState();
};

$(() => {
  $('.js-oiko-app-loader').once('js-oiko-app-loader').each(() => {
    const $wrapper = $(this);
    app.addTo($wrapper);
    $wrapper.data('oikoApp', app);
  });
});


// @TODO: Move all of this elsewhere.
//
// Probably a better way to write this.
$(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {
  if (mapDefinition.hasOwnProperty('pagestate') && mapDefinition.pagestate) {

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

      // Check to see if the current time was just moved.
      if (e.type == 'temporalBrowserTimeChanged' && currentTime != state.timeBrowserState.current) {
        needsUpdate = true;
      }

      // Check to see if the range window of the timeline has changed.
      if (e.type == 'temporalBrowserRangeChanged' && (state.timeBrowserState.start != drupalLeaflet.rangeStart || state.timeBrowserState.end != drupalLeaflet.rangeEnd)) {
        needsUpdate = true;
      }

      if (needsUpdate) {
        store.dispatch(setTimeBrowserState(currentTime, drupalLeaflet.rangeStart, drupalLeaflet.rangeEnd));
      }
    };

    const handleMapTemporalStoreStateChange = () => {
      let needsUpdate = false;
      let state = store.getState();
      const currentTime = drupalLeaflet.timelineControl.getTime();
      needsUpdate = needsUpdate || (state.timeBrowserState.current != currentTime);

      const currentWindow = drupalLeaflet.timelineControl.getWindow();

      needsUpdate = needsUpdate || (state.timeBrowserState.start != currentWindow.start || state.timeBrowserState.end != currentWindow.end);

      if (needsUpdate) {
        drupalLeaflet.changeTimeAndWindow(state.timeBrowserState.current, state.timeBrowserState.start, state.timeBrowserState.end);
      }
    };
    if (drupalLeaflet.map_definition.hasOwnProperty('timeline') && drupalLeaflet.map_definition.timeline) {
      $(window).on('oiko.loaded', () => {
        handleMapTemporalStoreStateChange();
        map.on('temporalBrowserTimeChanged temporalBrowserRangeChanged', handleMapTemporalShift);
        store.subscribe(handleMapTemporalStoreStateChange);
      });

    }
  }
});



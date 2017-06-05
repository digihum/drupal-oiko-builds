import {
  setMapState, setTimeBrowserState, addAppModule, appModuleDoneLoading,
  setVisualisation, setComparativeTimelines, setTimelinesState,
  setPHSCategories
} from './actions';
import { createOikoApp } from './store';
import $ from './jquery';
import isEqual from 'is-equal'
import watch from 'redux-watch'

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

if (drupalSettings.ajaxPageState.theme === 'oiko') {

// @TODO: START: Move all of this elsewhere.


// Window Visualisation.
  $(document).find('.js-oiko-app--toggle').bind('click', function (e) {
    const {visualisation} = store.getState();
    $(window).trigger('set.oiko.visualisation', visualisation === 'map' ? 'timeline' : 'map');
    e.preventDefault();
    $(this).blur();
  });

  $(window).on('set.oiko.visualisation', (e, visualisation) => {
    const {currentVisualisation} = store.getState();
    if (visualisation === 'map' || visualisation === 'timeline' && currentVisualisation !== visualisation) {
      store.dispatch(setVisualisation(visualisation));
    }
  });


  const visualisationSwitchListener = () => {
    const {visualisation} = store.getState();
    // Toggle a class on the body element to allow for sweeping changes.
    $('body')
      .toggleClass('showing-map', visualisation === 'map')
      .toggleClass('showing-timeline', visualisation !== 'map');

    $(window).trigger('resize.oiko.map_container');
  };

  // Announce the visualisation state on page load.
  $(window).bind('load', () => {
    const {visualisation} = store.getState();
    $(window).trigger('set.oiko.visualisation', visualisation);
  });

  $(window).on('resize.oiko.map_container', () => {
    if (window.drupalLeaflet && window.drupalLeaflet.lMap) {
      window.drupalLeaflet.lMap.invalidateSize();
    }
  });

  $(window).on('orientationchange', () => {
    $(window).trigger('resize.oiko.map_container');
  });

// PHS category filter.
  $(window).bind('set.oiko.categories', (e, categories, internal) => {
    if (internal) {
      store.dispatch(setPHSCategories(categories));
    }
  });
  let PHSCategoryWatch = watch(store.getState, 'PHSCategories', isEqual);
  const PHSCategoryListener = (newVal) => {
    $(window).trigger('set.oiko.categories', [newVal]);
  };


// Timelines on the comparative timeline widget.

  const timelinesListener = () => {
    const {comparativeTimelines} = store.getState();
    const timeline = Drupal.oiko.timeline;


    // Check to see if the timeslines displayed needs to change.
    const timelines = timeline.getTimelines();
    if (comparativeTimelines.length !== timelines.length || comparativeTimelines.every((v, i) => v !== timelines[i])) {
      timeline.setTimelines(comparativeTimelines);
    }

    // Check to see if the visual range of the timeline needs to change.
    const {timelinesState} = store.getState();
    const window = Drupal.oiko.timeline.getVisibleTimeWindow();
    if (timelinesState.start && timelinesState.end && (timelinesState.start != window.start || timelinesState.end != window.end)) {
      Drupal.oiko.timeline.setVisibleTimeWindow(timelinesState.start, timelinesState.end);
    }
  };

  $(window).bind('oiko.loaded', function () {
    $('.oiko-app--loader').hide();

    // Bind to hide/show the correct visualisation.
    store.subscribe(visualisationSwitchListener);
    visualisationSwitchListener();

    // Bind the PHS category listener.
    store.subscribe(PHSCategoryWatch(PHSCategoryListener));
    const {PHSCategories} = store.getState();
    PHSCategoryListener(PHSCategories, PHSCategories, 'PHSCategories');

    $(window).on('oiko.timelines_updated', (e, timelines) => {
      const {comparativeTimelines} = store.getState();
      const timeline = Drupal.oiko.timeline;
      if (!timeline.isLoadingItems() && (comparativeTimelines.length !== timelines.length || comparativeTimelines.every((v, i) => v !== timelines[i]))) {
        store.dispatch(setComparativeTimelines(timelines));
      }
    });

    store.subscribe(timelinesListener);
    timelinesListener();

    // Bind on the range changing on the comparative timeline.
    $(window).on('oiko.timelineRangeChanged', (e) => {
      const {timelinesState} = store.getState();
      const window = Drupal.oiko.timeline.getVisibleTimeWindow();
      if (window.start && window.end && (timelinesState.start != window.start || timelinesState.end != window.end)) {
        store.dispatch(setTimelinesState(window.start, window.end));
      }
    });
  });


// Probably a better way to write this.
  $(document).on('leaflet.map', function (e, mapDefinition, map, drupalLeaflet) {
    if (mapDefinition.hasOwnProperty('pagestate') && mapDefinition.pagestate) {

      window.drupalLeaflet = drupalLeaflet;

      const handleMapMove = (e) => {
        let center = map.getCenter();
        let changedNeeded = false;
        const state = store.getState();
        if (e.type === 'zoomend' && (map.getZoom() != state.mapState.level)) {
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

        if (changedNeeded && state.visualisation === 'map') {
          map.setView({
            lat: state.mapState.lat,
            lng: state.mapState.lng
          }, state.mapState.level, {animate: true});
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

        // Some simple validation, the current time needs to be within the time window.
        if (currentTime > currentVisibleWindow.end || currentTime < currentVisibleWindow.start) {
          needsUpdate = false;
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

// @TODO: END: Move all of this elsewhere.
}

import { combineReducers } from 'redux'
import { UPDATE_LOCATION, locationReducer } from './vendor/redux-history';

import { SET_MAP_STATE, SET_TIME_BROWSER_STATE, SET_VISUALISATION, ADD_APP_MODULE, APP_MODULE_DONE_LOADING, APP_LOADING_BOOT, APP_LOADING_START, APP_LOADING_ADD_TO_DOM, SET_COMPARATIVE_TIMELINES, SET_TIMELINES_STATE, SET_PHS_CATEGORIES } from './actions';
import { REQUEST_CIDOC_ENTITY, RECEIVE_CIDOC_ENTITY, cidocEntityReducer } from './sidebar';

import { QUERYSTRING_VARIABLE_VISUALISATION, QUERYSTRING_VARIABLE_MAP_ZOOM, QUERYSTRING_VARIABLE_MAP_CENTER_LAT, QUERYSTRING_VARIABLE_MAP_CENTER_LNG, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_POSITION, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_START, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_END, QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY, QUERYSTRING_VARIABLE_TIMELINE_ENTITIES, QUERYSTRING_VARIABLE_TIMELINES_START, QUERYSTRING_VARIABLE_TIMELINES_END, QUERYSTRING_VARIABLE_PHS_CATEGORIES } from './querystring-definitions';

import { changeQueryString, fetchQueryStringElements, fetchQueryStringElementsStructured, changeQueryStringStructured } from './plumbing/querystring-helpers';

function mapState(state = {
  level: 1,
  lat: 0,
  lng: 0
}, action) {
  switch (action.type) {
    case SET_MAP_STATE:
      return {
        level: Number.parseInt(action.level, 10),
        lat: Number.parseFloat(action.lat).toFixed(2),
        lng: Number.parseFloat(action.lng).toFixed(2)
      };

    case UPDATE_LOCATION:
      return {
        level: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_MAP_ZOOM, state.level), 10),
        lat: Number.parseFloat(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_MAP_CENTER_LAT, state.lat)).toFixed(2),
        lng: Number.parseFloat(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_MAP_CENTER_LNG, state.lng)).toFixed(2)
      };

    default:
      return {
        level: state.level,
        lat: state.lat,
        lng: state.lng
      };
  }
}

function timeBrowserState(state = {
  current: 0,
  start: 0,
  end: 0
}, action) {
  switch (action.type) {
    case SET_TIME_BROWSER_STATE:
      return {
        current: Number.parseInt(action.current, 10),
        start: Number.parseInt(action.start, 10),
        end: Number.parseInt(action.end, 10)
      };

    case UPDATE_LOCATION:
       return {
        current: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_POSITION, state.current), 10),
        start: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_START, state.start), 10),
        end: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_END, state.end), 10)
      };

    default:
      return {
        current: state.current,
        start: state.start,
        end: state.end
      };
  }
}

function timelinesState(state = {
                            start: 0,
                            end: 0
                          }, action) {
  switch (action.type) {
    case SET_TIMELINES_STATE:
      return {
        start: Number.parseInt(action.start, 10),
        end: Number.parseInt(action.end, 10)
      };

    case UPDATE_LOCATION:
      return {
        start: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_TIMELINES_START, state.start), 10),
        end: Number.parseInt(fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_TIMELINES_END, state.end), 10)
      };

    default:
      return {
        start: state.start,
        end: state.end
      };
  }
}

function PHSCategories(state = [], action) {
  switch (action.type) {
    case SET_PHS_CATEGORIES:
      return action.categories;

    case UPDATE_LOCATION:
      return fetchQueryStringElementsStructured(action.payload, QUERYSTRING_VARIABLE_PHS_CATEGORIES, state);

    default:
      return state;
  }
}

function visualisation(state = 'map', action) {
  switch (action.type) {
    case SET_VISUALISATION:
      return action.view;

    case UPDATE_LOCATION:
      return fetchQueryStringElements(action.payload, QUERYSTRING_VARIABLE_VISUALISATION, state);

    default:
      return state;
  }
}

function appLoading(state = APP_LOADING_BOOT, action) {
  switch (action.type) {
    case APP_LOADING_BOOT:
    case APP_LOADING_START:
    case APP_LOADING_ADD_TO_DOM:
      return Math.max(state, action.type);

    default:
      return state;
  }
}

/**
 * Reduce the app modules state slice.
 *
 * We keep track of modules that have registered and loaded.
 *
 * @param state
 * @param action
 * @returns {*}
 */
function appModules(state = {}, action) {
  switch (action.type) {
    case ADD_APP_MODULE:
      return Object.assign({}, state, {[action.name]: false});
    case APP_MODULE_DONE_LOADING:
      return Object.assign({}, state, {[action.name]: true});
    default:
      return state;
  }
}

/**
 * Reduce the state for the comparative timelines.
 *
 * @param state
 * @param action
 * @returns {*}
 */
function comparativeTimelines(state = [], action) {
  switch (action.type) {
    case SET_COMPARATIVE_TIMELINES:
      return action.timelines;

    case UPDATE_LOCATION:
      return fetchQueryStringElementsStructured(action.payload, QUERYSTRING_VARIABLE_TIMELINE_ENTITIES, state);

    default:
      return state;
  }
}

const initialState = {

  pathname: null,
  search: null,
  hash: null,
  state: null,
  action: null,
  key: null

};

/**
 * Reduce the state of the location element.
 *
 * This is where we update the querystring with our values.
 *
 * @param locationReducerFunction
 * @returns {function(*=, *=)}
 */
function oikoLocation(locationReducerFunction) {
  return (state = initialState, action) => {
    const new_state = locationReducerFunction(state, action);

    // Update the query string appropriately.
    switch (action.type) {
      case SET_VISUALISATION:
        return changeQueryString(new_state, {[QUERYSTRING_VARIABLE_VISUALISATION]: action.view});

      case SET_MAP_STATE:
        return changeQueryString(new_state, {[QUERYSTRING_VARIABLE_MAP_ZOOM]: action.level, [QUERYSTRING_VARIABLE_MAP_CENTER_LAT]: action.lat, [QUERYSTRING_VARIABLE_MAP_CENTER_LNG]: action.lng});

      case SET_TIME_BROWSER_STATE:
        return changeQueryString(new_state, {[QUERYSTRING_VARIABLE_TIMELINE_BROWSER_POSITION]: action.current, [QUERYSTRING_VARIABLE_TIMELINE_BROWSER_START]: action.start, [QUERYSTRING_VARIABLE_TIMELINE_BROWSER_END]: action.end});

      case SET_TIMELINES_STATE:
        return changeQueryString(new_state, {[QUERYSTRING_VARIABLE_TIMELINES_START]: action.start, [QUERYSTRING_VARIABLE_TIMELINES_END]: action.end});

      case RECEIVE_CIDOC_ENTITY:
        return changeQueryString(new_state, {[QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY]: action.id}, false);

      case SET_COMPARATIVE_TIMELINES:
        return changeQueryStringStructured(new_state, {[QUERYSTRING_VARIABLE_TIMELINE_ENTITIES]: action.timelines}, false);

      case SET_PHS_CATEGORIES:
        return changeQueryStringStructured(new_state, {[QUERYSTRING_VARIABLE_PHS_CATEGORIES]: action.categories});

      default:
        return new_state;
    }
  }
}

const oikoAppReducers = combineReducers({
  appLoading,
  visualisation,
  mapState,
  timeBrowserState,
  timelinesState,
  comparativeTimelines,
  PHSCategories,
  appModules,
  cidocEntity : cidocEntityReducer,
  location: oikoLocation(locationReducer)
});


export default oikoAppReducers;

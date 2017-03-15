import { combineReducers } from 'redux'
import { UPDATE_LOCATION, locationReducer } from './redux-history';

import { SET_MAP_STATE, SET_TIME_BROWSER_STATE, SET_VISUALISATION, ADD_APP_MODULE, APP_MODULE_DONE_LOADING } from './actions';
import { REQUEST_CIDOC_ENTITY, RECEIVE_CIDOC_ENTITY, cidocEntityReducer } from './sidebar';

import { QUERYSTRING_VARIABLE_VISUALISATION, QUERYSTRING_VARIABLE_MAP_ZOOM, QUERYSTRING_VARIABLE_MAP_CENTER_LAT, QUERYSTRING_VARIABLE_MAP_CENTER_LNG, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_POSITION, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_START, QUERYSTRING_VARIABLE_TIMELINE_BROWSER_END, QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY } from './querystring-definitions';

import { changeQueryString, fetchQueryStringElements } from './plumbing/querystring-helpers';

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

const initialState = {

  pathname: null,
  search: null,
  hash: null,
  state: null,
  action: null,
  key: null

};

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

      case RECEIVE_CIDOC_ENTITY:
        return changeQueryString(new_state, {[QUERYSTRING_VARIABLE_SIDEBAR_CIDOC_ENTITY]: action.id}, false);

      default:
        return new_state;
    }
  }
}

const oikoAppReducers = combineReducers({
  visualisation,
  mapState,
  timeBrowserState,
  appModules,
  cidocEntity : cidocEntityReducer,
  location: oikoLocation(locationReducer)
});


export default oikoAppReducers;
